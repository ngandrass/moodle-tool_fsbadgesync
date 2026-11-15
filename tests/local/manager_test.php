<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for the manager class
 *
 * @package   tool_fsbadgesync
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fsbadgesync\local;

use core\event\course_completed;
use core\event\course_viewed;
use tool_fsbadgesync\task\award_fs_badge;

/**
 * Tests for the manager class
 */
final class manager_test extends \advanced_testcase {
    /**
     * This method is called before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Enable and fully configure the plugin by default.
        set_config('enable', true, 'tool_fsbadgesync');
        set_config('apiurl', 'https://example.org', 'tool_fsbadgesync');
        set_config('apitoken', 'secret-token', 'tool_fsbadgesync');
        set_config('coursefield', 'fsbadgeid', 'tool_fsbadgesync');
        set_config('userfield', 'fsid', 'tool_fsbadgesync');
    }

    /**
     * Creates a new course with a custom field named fsbadgeid and stores the given value in it.
     *
     * @param mixed $value Value to store in the fsbadgeid field or null to store nothing
     * @param string $type Custom field type of the fsbadgeid field
     * @return \stdClass The created course
     * @throws \coding_exception
     */
    private function create_course_with_fsbadgeid($value, string $type = 'text'): \stdClass {
        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $field = $generator->create_field([
            'categoryid' => $generator->create_category()->get('id'),
            'type' => $type,
            'shortname' => 'fsbadgeid',
        ]);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        if ($value !== null) {
            $generator->add_instance_data($field, $course->id, $value);
        }

        return $course;
    }

    /**
     * Creates a new user with a custom profile field named fsid and stores the given value in it.
     *
     * @param string|null $value Value to store in the fsid field or null to store nothing
     * @return \stdClass The created user
     */
    private function create_user_with_fsid(?string $value): \stdClass {
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'fsid',
            'name' => 'Foodsharing ID',
        ]);

        return $this->getDataGenerator()->create_user($value !== null ? ['profile_field_fsid' => $value] : []);
    }

    /**
     * Completes a course for a user and returns the captured course_completed event.
     *
     * @param \stdClass $course Course to complete
     * @param \stdClass $user User completing the course
     * @return course_completed The captured event
     * @throws \coding_exception
     */
    private function create_course_completed_event(\stdClass $course, \stdClass $user): \core\event\course_completed {
        global $CFG;
        require_once($CFG->dirroot . '/completion/completion_completion.php');

        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Complete the course via the core completion API and capture the triggered event without running observers.
        $sink = $this->redirectEvents();
        (new \completion_completion(['course' => $course->id, 'userid' => $user->id]))->mark_complete();
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\course_completed);
        $sink->close();

        $this->assertCount(1, $events, 'Exactly one course_completed event should have been triggered');
        return reset($events);
    }

    /**
     * Creates an arbitrary event that is not handled by the plugin.
     *
     * @return course_viewed The created event
     * @throws \coding_exception
     */
    private function create_unhandled_event(): \core\event\course_viewed {
        $course = $this->getDataGenerator()->create_course();
        return \core\event\course_viewed::create(['context' => \context_course::instance($course->id)]);
    }

    /**
     * Tests extraction of the fsid from a user profile field
     *
     * @covers       \tool_fsbadgesync\local\manager
     * @dataProvider text_field_value_provider
     *
     * @param string|null $value Value stored in the user profile field
     * @param int|null $expected Expected fsid
     * @return void
     * @throws \dml_exception
     */
    public function test_get_fsid(?string $value, ?int $expected): void {
        $user = $this->create_user_with_fsid($value);

        $this->assertSame($expected, (new manager())->get_fsid($user->id));
    }

    /**
     * Data provider for test_get_fsid()
     *
     * @return array[] Test cases of [value, expected result]
     */
    public static function text_field_value_provider(): array {
        return [
            'Positive integer' => ['42', 42],
            'Surrounding whitespace' => [' 7 ', 7],
            'Zero' => ['0', null],
            'Negative integer' => ['-1', null],
            'Decimal' => ['1.5', null],
            'Non-numeric' => ['abc', null],
            'Empty' => ['', null],
            'No value' => [null, null],
        ];
    }

    /**
     * Tests that no fsid is returned if the user profile field is not or wrongly configured
     *
     * @covers \tool_fsbadgesync\local\manager
     *
     * @return void
     * @throws \dml_exception
     */
    public function test_get_fsid_unconfigured(): void {
        $user = $this->create_user_with_fsid('42');

        set_config('userfield', '', 'tool_fsbadgesync');
        $this->assertNull((new manager())->get_fsid($user->id), 'Unconfigured field should yield null');

        set_config('userfield', 'doesnotexist', 'tool_fsbadgesync');
        $this->assertNull((new manager())->get_fsid($user->id), 'Non-existing field should yield null');
    }

    /**
     * Tests extraction of the fsbadgeid from a course field
     *
     * @covers       \tool_fsbadgesync\local\manager
     * @dataProvider fsbadgeid_provider
     *
     * @param string $type Custom field type of the fsbadgeid field
     * @param mixed $value Value stored in the course field
     * @param int|null $expected Expected fsbadgeid
     * @return void
     * @throws \coding_exception
     */
    public function test_get_fsbadgeid(string $type, $value, ?int $expected): void {
        $course = $this->create_course_with_fsbadgeid($value, $type);

        $this->assertSame($expected, (new manager())->get_fsbadgeid($course->id));
    }

    /**
     * Data provider for test_get_fsbadgeid()
     *
     * @return array[] Test cases of [field type, value, expected result]
     */
    public static function fsbadgeid_provider(): array {
        $cases = [];
        foreach (self::text_field_value_provider() as $name => [$value, $expected]) {
            $cases["Text: {$name}"] = ['text', $value, $expected];
        }

        return $cases + [
                'Number: Positive integer' => ['number', 42, 42],
                'Number: Zero' => ['number', 0, null],
                'Number: Negative integer' => ['number', -3, null],
                'Number: Decimal' => ['number', 1.5, null],
                'Number: No value' => ['number', null, null],
            ];
    }

    /**
     * Tests that no fsbadgeid is returned if the course field is not or wrongly configured
     *
     * @covers \tool_fsbadgesync\local\manager
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_get_fsbadgeid_unconfigured(): void {
        $course = $this->create_course_with_fsbadgeid('42');

        set_config('coursefield', '', 'tool_fsbadgesync');
        $this->assertNull((new manager())->get_fsbadgeid($course->id), 'Unconfigured field should yield null');

        set_config('coursefield', 'doesnotexist', 'tool_fsbadgesync');
        $this->assertNull((new manager())->get_fsbadgeid($course->id), 'Non-existing field should yield null');
    }

    /**
     * Tests that events are not handled when the plugin is disabled
     *
     * @covers \tool_fsbadgesync\local\manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_dispatch_event_skips_if_disabled(): void {
        set_config('enable', false, 'tool_fsbadgesync');

        manager::dispatch_event($this->create_unhandled_event());

        $this->assertDebuggingCalled('Plugin is disabled. Skipping event handling.');
    }

    /**
     * Tests that unsupported events are ignored
     *
     * @covers \tool_fsbadgesync\local\manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_dispatch_event_ignores_unhandled_event(): void {
        $event = $this->create_unhandled_event();

        manager::dispatch_event($event);

        $this->assertDebuggingCalled('Unhandled event: ' . $event->get_name());
    }

    /**
     * Tests that badge awards are only queued for applicable courses and users with a configured API
     *
     * @covers       \tool_fsbadgesync\local\manager
     * @dataProvider handle_course_completed_provider
     *
     * @param string $fsbadgeid Value of the course fsbadgeid field
     * @param string $fsid Value of the user fsid field
     * @param array $config Plugin config overrides as [name => value]
     * @param string $expected Expected debugging message with {courseid} and {userid} placeholders
     * @param bool $expecttask Whether an award_fs_badge task is expected to be queued
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_handle_course_completed(
        string $fsbadgeid,
        string $fsid,
        array $config,
        string $expected,
        bool $expecttask
    ): void {
        foreach ($config as $name => $value) {
            set_config($name, $value, 'tool_fsbadgesync');
        }
        $course = $this->create_course_with_fsbadgeid($fsbadgeid);
        $user = $this->create_user_with_fsid($fsid);

        $event = $this->create_course_completed_event($course, $user);
        manager::dispatch_event($event);

        $this->assertDebuggingCalledCount(2, [
            'Event received: ' . $event->get_name() . ' for user ID: ' . $user->id,
            str_replace(['{courseid}', '{userid}'], [$course->id, $user->id], $expected),
        ]);

        $tasks = \core\task\manager::get_adhoc_tasks(award_fs_badge::class);
        if (!$expecttask) {
            $this->assertEmpty($tasks, 'No task should be queued');
            return;
        }

        $this->assertCount(1, $tasks, 'Exactly one task should be queued');
        $task = reset($tasks);
        $this->assertSame(4, (int) $task->get_attempts_available(), 'Task should allow 3 retries');
        $this->assertEquals((object) [
            'fsbadgeid' => 13,
            'fsid' => 37,
            'courseid' => (int) $course->id,
            'userid' => (int) $user->id,
        ], $task->get_custom_data());
    }

    /**
     * Data provider for test_handle_course_completed()
     *
     * @return array[] Test cases of [fsbadgeid, fsid, config overrides, expected debugging message, expect queued task]
     */
    public static function handle_course_completed_provider(): array {
        return [
            'Applicable course and user' => ['13', '37', [], 'Queued awarding of fsbadgeid 13 to fsid 37', true],
            'Course without fsbadgeid' => ['0', '37', [], 'No fsbadgeid found for course ID {courseid}. Skipping.', false],
            'User without fsid' => ['13', '', [], 'No fsid found for user ID {userid}. Skipping.', false],
            'API token missing' => ['13', '37', ['apitoken' => ''], 'Foodsharing API not configured. Skipping.', false],
            'API URL missing' => ['13', '37', ['apiurl' => ''], 'Foodsharing API not configured. Skipping.', false],
        ];
    }
}

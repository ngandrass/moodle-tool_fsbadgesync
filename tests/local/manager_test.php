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


/**
 * Tests for the manager class
 */
final class manager_test extends \advanced_testcase {
    /**
     * This method is called before each test.
     */
    protected function setUp(): void {
        parent::setUp();

        // Enable plugin by default.
        set_config('enable', true, 'tool_fsbadgesync');
    }

    /**
     * Completes a new course for a new user and returns the captured course_completed event.
     *
     * @return \core\event\course_completed The created event
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function create_course_completed_event(): \core\event\course_completed {
        global $CFG;
        require_once($CFG->dirroot . '/completion/completion_completion.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
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
     * Tests that is_enabled() reflects the plugin enable setting
     *
     * @covers \tool_fsbadgesync\local\manager
     *
     * @return void
     * @throws \dml_exception
     */
    public function test_is_enabled(): void {
        $this->resetAfterTest();

        $manager = new manager();
        $this->assertTrue($manager->is_enabled(), 'Manager should report enabled plugin');

        set_config('enable', false, 'tool_fsbadgesync');
        $manager = new manager();
        $this->assertFalse($manager->is_enabled(), 'Manager should report disabled plugin');
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
        $this->resetAfterTest();
        set_config('enable', false, 'tool_fsbadgesync');

        $event = $this->create_course_completed_event();
        manager::dispatch_event($event);

        $this->assertDebuggingCalled('Plugin is disabled. Skipping event handling.');
    }

    /**
     * Tests that course_completed events are dispatched to the course completion handler
     *
     * @covers \tool_fsbadgesync\local\manager
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_dispatch_event_handles_course_completed(): void {
        $this->resetAfterTest();

        $event = $this->create_course_completed_event();
        manager::dispatch_event($event);

        $this->assertDebuggingCalledCount(2, [
            'Event received: ' . $event->get_name() . ' for user ID: ' . $event->relateduserid,
            'TODO',
        ]);
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
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $event = \core\event\course_viewed::create(['context' => \context_course::instance($course->id)]);
        manager::dispatch_event($event);

        $this->assertDebuggingCalled('Unhandled event: ' . $event->get_name());
    }
}

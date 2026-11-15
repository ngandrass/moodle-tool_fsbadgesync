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
 * Tests for the award_fs_badge adhoc task
 *
 * @package   tool_fsbadgesync
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fsbadgesync\task;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for the award_fs_badge adhoc task
 */
final class award_fs_badge_test extends \advanced_testcase {
    /** @var MockHandler Mocked HTTP handler to queue API responses in */
    private MockHandler $mock;

    /** @var array History of all HTTP requests sent */
    private array $history = [];

    /**
     * This method is called before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        set_config('enable', true, 'tool_fsbadgesync');
        set_config('apiurl', 'https://example.org', 'tool_fsbadgesync');
        set_config('apitoken', 'secret-token', 'tool_fsbadgesync');

        $this->history = [];
        $this->mock = $this->get_mocked_http_client($this->history)['mock'];
    }

    /**
     * Creates a new award_fs_badge task for fsbadgeid 13 and fsid 37
     *
     * @return award_fs_badge The created task
     */
    private function create_task(): award_fs_badge {
        $task = new award_fs_badge();
        $task->set_custom_data(['fsbadgeid' => 13, 'fsid' => 37, 'courseid' => 2, 'userid' => 3]);

        return $task;
    }

    /**
     * Tests that a successful API call sends the expected request
     *
     * @covers       \tool_fsbadgesync\task\award_fs_badge
     * @covers       \tool_fsbadgesync\local\manager
     * @dataProvider base_url_provider
     *
     * @param string $baseurl Configured API base URL
     * @return void
     * @throws GuzzleException
     */
    public function test_execute_success(string $baseurl): void {
        set_config('apiurl', $baseurl, 'tool_fsbadgesync');
        $this->mock->append(new Response(200));

        $this->expectOutputRegex('/Awarded badge 13 to FS-ID 37/');
        $this->create_task()->execute();

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://example.org/api/moodle/achievements/13/users/37', (string) $request->getUri());
        $this->assertSame('secret-token', $request->getHeaderLine('X-Moodle-Token'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    /**
     * Data provider for test_execute_success()
     *
     * @return array[] Test cases of [base URL]
     */
    public static function base_url_provider(): array {
        return [
            'Without trailing slash' => ['https://example.org'],
            'With trailing slash' => ['https://example.org/'],
        ];
    }

    /**
     * Tests that API failures are rethrown to trigger a task retry
     *
     * @covers \tool_fsbadgesync\task\award_fs_badge
     * @covers \tool_fsbadgesync\local\manager
     *
     * @return void
     */
    public function test_execute_failure_is_rethrown(): void {
        $this->mock->append(new Response(500));

        $this->expectException(GuzzleException::class);
        $this->create_task()->execute();
    }

    /**
     * Tests that the task fails without sending a request if the API is not configured
     *
     * @covers \tool_fsbadgesync\task\award_fs_badge
     * @covers \tool_fsbadgesync\local\manager
     *
     * @return void
     * @throws GuzzleException
     */
    public function test_execute_unconfigured(): void {
        set_config('apitoken', '', 'tool_fsbadgesync');

        try {
            $this->create_task()->execute();
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_api_not_configured', $e->errorcode);
        }
        $this->assertEmpty($this->history, 'No request should be sent');
    }
}

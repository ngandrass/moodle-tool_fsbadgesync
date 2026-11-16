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
 * Adhoc task to award a badge on the foodsharing platform.
 *
 * @package     tool_fsbadgesync
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fsbadgesync\task;

use tool_fsbadgesync\local\manager;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Awards a badge to a user on the foodsharing platform via its API.
 *
 * Expected custom data: fsbadgeid, fsid, courseid, userid. API failures are
 * rethrown to make use of the adhoc task retry mechanism. Authentication and
 * authorization errors (HTTP 401 / 403) are not retried since they will not
 * be resolved by sole retrying.
 */
class award_fs_badge extends \core\task\adhoc_task {
    /**
     * Returns the name of this task
     *
     * @return string Task name
     * @throws \coding_exception
     */
    public function get_name(): string {
        return get_string('task_award_fs_badge', 'tool_fsbadgesync');
    }

    /**
     * Awards the badge
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException On API failures to trigger a retry
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $fsbadgeid = (int) $data->fsbadgeid;
        $fsid = (int) $data->fsid;

        $manager = new manager();
        try {
            $manager->award_fs_badge($fsbadgeid, $fsid);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Authentication / authorization errors will not resolve by retrying. Fail permanently.
            $status = $e->getResponse()->getStatusCode();
            if (in_array($status, [401, 403], true)) {
                $this->set_attempts_available(1); // Core decrements this to 0 on failure, preventing further retries.
                mtrace("Foodsharing API rejected the request with HTTP {$status}. Not retrying.");
            }
            throw $e;
        }

        mtrace("Awarded badge {$fsbadgeid} to FS-ID {$fsid}.");
    }
}

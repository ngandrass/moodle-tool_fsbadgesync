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
 * Main manager class for the tool_fsbadgesync plugin.
 *
 * @package     tool_fsbadgesync
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fsbadgesync\local;

use tool_fsbadgesync\task\award_fs_badge;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Handles incoming Moodle events and triggers the badge synchronization process.
 */
class manager {
    /** @var \stdClass Moodle config object for this plugin */
    protected \stdClass $config;

    /**
     * Creates a new manager instance
     *
     * @return void
     * @throws \dml_exception
     */
    public function __construct() {
        $this->config = get_config('tool_fsbadgesync');
    }

    /**
     * Checks if the plugin is enabled.
     *
     * @return bool True if enabled, false otherwise.
     */
    public function is_enabled(): bool {
        return !empty($this->config->enable);
    }

    /**
     * Checks if the foodsharing API connection is configured.
     *
     * @return bool True if both API URL and API token are set, false otherwise.
     */
    public function is_api_configured(): bool {
        return !empty($this->config->apiurl) && !empty($this->config->apitoken);
    }

    /**
     * Awards a badge (achievement) to a user on the foodsharing platform.
     *
     * @param int $fsbadgeid ID of the badge to award
     * @param int $fsid ID of the user on the foodsharing platform
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException On HTTP errors or connection problems
     */
    public function award_fs_badge(int $fsbadgeid, int $fsid): void {
        // Only issue badges if the foodsharing API is configured.
        if (!$this->is_api_configured()) {
            debugging('Foodsharing API not configured. Skipping.');
            return;
        }

        // Call the foodsharing API. Throws on error.
        $url = rtrim($this->config->apiurl, '/') . "/api/moodle/achievements/{$fsbadgeid}/users/{$fsid}";
        \core\di::get(\core\http_client::class)->post($url, [
            'headers' => [
                'X-Moodle-Token' => $this->config->apitoken,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Dispatches incoming Moodle events
     *
     * @param \core\event\base $event The event object
     * @return void
     * @throws \dml_exception
     */
    public static function dispatch_event(\core\event\base $event): void {
        // Only handle events if the plugin is enabled.
        $manager = new self();
        if (!$manager->is_enabled()) {
            debugging('Plugin is disabled. Skipping event handling.');
            return;
        }

        // Dispatch event.
        if ($event instanceof \core\event\course_completed) {
            debugging('Event received: ' . $event->get_name() . ' for user ID: ' . $event->relateduserid);
            $manager->handle_course_completed($event);
        } else {
            debugging('Unhandled event: ' . $event->get_name());
        }
    }

    /**
     * Issues a badge to an applicable user that completed an applicable course.
     *
     * @param \core\event\course_completed $event The course completed event object
     * @return void
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public function handle_course_completed(\core\event\course_completed $event): void {
        // Only issue badges for courses that have a valid FS badge associated with it.
        $fsbadgeid = $this->get_fsbadgeid($event->courseid);
        if ($fsbadgeid === null) {
            debugging("No fsbadgeid found for course ID {$event->courseid}. Skipping.");
            return;
        }

        // Only issue badged for users that are linked by their FS-ID.
        $fsid = $this->get_fsid($event->relateduserid);
        if ($fsid === null) {
            debugging("No fsid found for user ID {$event->relateduserid}. Skipping.");
            return;
        }

        // Defer the API call to an adhoc task to not block course completion and to allow retries.
        $task = new award_fs_badge();
        $task->set_custom_data([
            'fsbadgeid' => $fsbadgeid,
            'fsid' => $fsid,
            'courseid' => $event->courseid,
            'userid' => $event->relateduserid,
        ]);
        $task->set_attempts_available(4); // Initial attempt plus 3 retries.
        \core\task\manager::queue_adhoc_task($task, true);

        debugging("Queued awarding of fsbadgeid {$fsbadgeid} to fsid {$fsid}");
    }

    /**
     * Retrieves the fsbadgeid (ID of the badge to award on the foodsharing
     * platform) of a course.
     *
     * @param int $courseid ID of the course to look up
     * @return int|null The fsbadgeid or null if the course field is not
     * configured or the course holds no positive non-zero integer within it
     */
    public function get_fsbadgeid(int $courseid): ?int {
        // Ensure valid config.
        if (empty($this->config->coursefield)) {
            return null;
        }

        // Get target course field.
        $field = null;
        foreach (\core_course\customfield\course_handler::create()->get_instance_data($courseid, true) as $fields) {
            if ($fields->get_field()->get('shortname') === $this->config->coursefield) {
                $field = $fields;
                break;
            }
        }
        if (!$field) {
            return null;
        }

        // Retrieve and sanitize value.
        // Number fields can store decimals (e.g., "42.0000000000"). Casting to float lets whole numbers pass the int check below.
        $value = $field->get_value();
        if ($field->get_field()->get('type') === 'number') {
            $value = (float) $value;
        }

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    }

    /**
     * Retrieves the fsid (ID of the user on the foodsharing platform) of a user.
     *
     * @param int $userid ID of the user to look up
     * @return int|null The fsid or null if the user profile field is not configured
     * or the user holds no positive non-zero integer within it
     * @throws \dml_exception
     */
    public function get_fsid(int $userid): ?int {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        // Ensure valid config.
        if (empty($this->config->userfield)) {
            return null;
        }

        // Retrieve and sanitize value.
        $value = profile_user_record($userid, false)->{$this->config->userfield} ?? null;
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    }
}

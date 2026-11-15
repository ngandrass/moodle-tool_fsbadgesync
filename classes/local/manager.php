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

namespace tool_fsbadgesync;

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
     * Issues a badge to an applicable user that completed an applicable course.
     *
     * @param \core\event\course_completed $event The course completed event object
     * @return void
     */
    public function handle_course_completed(\core\event\course_completed $event): void {
        // Handle the course completed event.
        mtrace('TODO');
    }

    /**
     * Dispatches incoming Moodle events
     *
     * @param \core\event\base $event The event object
     * @return void
     */
    public static function dispatch_event(\core\event\base $event): void {
        // Only handle events if the plugin is enabled.
        $manager = new self();
        if (!$manager->is_enabled()) {
            mtrace('Plugin is disabled. Skipping event handling.');
            return;
        }

        // Dispatch event.
        if ($event instanceof \core\event\course_completed) {
            mtrace('Event received: ' . $event->get_name() . ' for user ID: ' . $event->relateduserid);
            $manager->handle_course_completed($event);
        } else {
            mtrace('Unhandled event: ' . $event->get_name());
        }
    }
}

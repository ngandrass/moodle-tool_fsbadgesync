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
 * Privacy provider for the tool_fsbadgesync plugin.
 *
 * @package     tool_fsbadgesync
 * @category    privacy
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fsbadgesync\privacy;

use core_privacy\local\metadata\collection;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Privacy provider for the tool_fsbadgesync plugin.
 *
 * The plugin stores no personal data itself but transmits data to the
 * foodsharing platform.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\data_provider {
    /**
     * Returns metadata about the data exported to external locations.
     *
     * @param collection $collection The initialised collection to add items to
     * @return collection The updated collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('foodsharing', [
            'fsid' => 'privacy:metadata:foodsharing:fsid',
            'fsbadgeid' => 'privacy:metadata:foodsharing:fsbadgeid',
        ], 'privacy:metadata:foodsharing');

        return $collection;
    }
}

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
 * Plugin strings are defined here.
 *
 * @package     tool_fsbadgesync
 * @category    string
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @codingStandardsIgnoreFile

defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

$string['pluginname'] = 'Foodsharing Badge Sync';
$string['privacy:metadata:foodsharing'] = 'When a user completes a course that is linked to a foodsharing badge, the plugin sends data to the foodsharing platform to award the badge to the user.';
$string['privacy:metadata:foodsharing:fsbadgeid'] = 'The ID of the foodsharing badge to award.';
$string['privacy:metadata:foodsharing:fsid'] = 'The ID of the user on the foodsharing platform (FS-ID).';
$string['setting_apitoken'] = 'API token';
$string['setting_apitoken_desc'] = 'Service token used to authenticate against the foodsharing API. It must match the <code>MOODLE_SERVICE_TOKEN</code> configured on the foodsharing platform.';
$string['setting_apiurl'] = 'API base URL';
$string['setting_apiurl_desc'] = 'Base URL of the foodsharing platform (e.g., <code>https://foodsharing.de</code>). The API path is added automatically.';
$string['setting_coursefield'] = 'Course field: FS Badge ID';
$string['setting_coursefield_desc'] = 'Shortname of the custom course field that holds the ID of the foodsharing badge to award upon course completion. Only courses with a positive integer value in this field are processed.';
$string['setting_enable'] = 'Enable Badge Sync';
$string['setting_enable_desc'] = 'If enabled, the plugin will automatically issue badges to applicable users when completing courses. If disabled, no new badges will be issued.';
$string['setting_plugin_desc'] = 'This plugin automatically issues badges to foodsharing users when they complete certain courses. It is designed to work with the Foodsharing platform and its badge system.';
$string['setting_userfield'] = 'User profile field: FS-ID';
$string['setting_userfield_desc'] = 'Shortname of the custom user profile field that holds the ID of the user on the foodsharing platform (FS-ID). Only users with a positive integer value in this field are processed.';
$string['task_award_fs_badge'] = 'Award foodsharing badge';

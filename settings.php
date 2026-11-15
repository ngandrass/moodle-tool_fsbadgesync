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
 * Plugin administration pages are defined here.
 *
 * @package     tool_fsbadgesync
 * @category    admin
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_fsbadgesync', new lang_string('pluginname', 'tool_fsbadgesync'));

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // Header description text.
        $settings->add(new admin_setting_heading(
            'tool_fsbadgesync/header',
            "",
            get_string('setting_plugin_desc', 'tool_fsbadgesync')
        ));

        // Enable plugin globally.
        $settings->add(new admin_setting_configcheckbox(
            'tool_fsbadgesync/enable',
            get_string('setting_enable', 'tool_fsbadgesync'),
            get_string('setting_enable_desc', 'tool_fsbadgesync'),
            '1'
        ));

        // Course custom field holding the ID of the foodsharing badge to award.
        $settings->add(new admin_setting_configtext(
            'tool_fsbadgesync/coursefield',
            get_string('setting_coursefield', 'tool_fsbadgesync'),
            get_string('setting_coursefield_desc', 'tool_fsbadgesync'),
            'fsbadgeid',
            PARAM_ALPHANUMEXT
        ));

        // User profile field holding the FS-ID of the user.
        $settings->add(new admin_setting_configtext(
            'tool_fsbadgesync/userfield',
            get_string('setting_userfield', 'tool_fsbadgesync'),
            get_string('setting_userfield_desc', 'tool_fsbadgesync'),
            'fsid',
            PARAM_ALPHANUMEXT
        ));
    }

    $ADMIN->add('tools', $settings);
}

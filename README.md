# Foodsharing Badge Sync for Moodle

A small Moodle plugin that grants defined badges on the foodsharing platform when applicable users complete certain
courses on Moodle.


## How it works

The plugin listens for Moodle's `\core\event\course_completed` events. A badge is only awarded if **all** of the
following apply:

- The plugin is enabled and the API connection is configured.
- The completed course has a *FS Badge ID* custom course field set to a valid foodsharing badge ID.
- The user has their *FS-ID* (user ID on the foodsharing platform) stored in a respective custom user profile field.

If any of these conditions is not met, the event is skipped silently. Courses and users are therefore opted in simply
by filling in the respective fields.

The actual API call is executed asynchronously via the ad-hoc task `award_fs_badge`, so course completion is never
blocked. Failed API calls are retried automatically (up to 11 retries with exponential backoff, covering roughly 34
hours). This way outages of up to a day do not cause loss of badge awards.


## Configuration

### 1. Custom course field (FS Badge ID)

1. Go to *Site administration > Courses > Default settings > Course custom fields*.
2. Add a field of type *Number* with the shortname `fsbadgeid`.
3. Restrict who can edit / see the field as desired (e.g., *Locked* and visible to *Teachers*).
4. In each course that should award a badge, set this field to the ID of the respective foodsharing badge.

### 2. Custom user profile field (FS-ID)

1. Go to *Site administration > Users > Accounts > User profile fields*.
2. Add a field of type *Text input* with the shortname `fsid`.
3. Set it to *Locked* and *Visible to user, teachers, and admins* or as desired.
4. The field must hold the user's ID on the foodsharing platform.

If you want to use different shortnames, adjust them in the plugin settings (see below).

### 3. Plugin settings and API connection

Go to *Site administration > Plugins > Admin tools > Foodsharing Badge Sync* and configure:

| Setting                       | Default                  | Description                                                                         |
|-------------------------------|--------------------------|-------------------------------------------------------------------------------------|
| Enable Badge Sync             | enabled                  | Globally enables / disables awarding of badges.                                     |
| Course field: FS Badge ID     | `fsbadgeid`              | Shortname of the custom course field holding the badge ID.                          |
| User profile field: FS-ID     | `fsid`                   | Shortname of the custom user profile field holding the FS-ID.                       |
| API base URL                  | `https://foodsharing.de` | Base URL of the foodsharing platform. The API path is appended automatically.       |
| API token                     | *(empty)*                | Service token. Must match `MOODLE_SERVICE_TOKEN` configured on foodsharing.         |

Badges are awarded via `POST {apiurl}/api/moodle/achievements/{fsbadgeid}/users/{fsid}`, authenticated by the
`X-Moodle-Token` header. Make sure the target host is not blocked by Moodle's HTTP security settings
(*Site administration > General > Security > HTTP security*).


## Troubleshooting

- Check *Site administration > Server > Tasks > Ad hoc tasks* for queued or failing `award_fs_badge` tasks.
- Check *Site administration > Server > Tasks > Task logs* for logs of previously executed tasks.
- Ensure that the Moodle cron runs regularly (e.g., every minute) to process ad-hoc tasks.
- Enable developer debugging to see why a course completion was skipped (e.g., missing field values).


## License

Copyright (C) 2026 Niels Gandraß <niels@gandrass.de>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.

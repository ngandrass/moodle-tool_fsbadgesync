# Changelog

## Version X.Y.Z (YYYYMMDDNN)

- Extend exponential badge award task backoff to 11 retries (roughly 34 hours) before failing an award task.
- Fail badge award tasks immediately without retries if the foodsharing API responds with HTTP 401 or 403.
- Drop db install/upgrade/uninstall scripts for now
 

## Version 0.1.0 (2026111503)

- Initial beta release

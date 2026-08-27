# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [v0.2.0] - 2026-08-27

First release of the fork under `github.com/uaiblaine`, targeting Moodle 5.1 and
5.2. Moodle 4.5 stays on `MOODLE_405_STABLE` and Moodle 3.9 on
`MOODLE_39_STABLE`.

### Fixed

- **Adding a quiz no longer fails on PostgreSQL.** On the add path core sets
  `coursemodule` to the empty string, which was passed straight into a bigint
  comparison; PostgreSQL rejected the bind and the quiz settings page died with
  a `dml_read_exception`. MySQL coerced the same value to `0`, which is why the
  defect only showed on half the supported databases.
- **An unrelated quiz update no longer resets a stored Hide back to Show.** The
  post-actions callback treated a missing form property as "Show". Core's own
  `update_module()` API builds a `moduleinfo` without it, so any tool using that
  API silently undid the teacher's setting.
- **Rows are no longer leaked forever.** Nothing ever deleted a row, so the
  table grew by one row per quiz and a reused course module id could make a new
  quiz inherit a deleted quiz's setting.
- **Restore is idempotent.** It inserted unconditionally into a table with a
  unique index on `cmid`, so restoring into a course module that already had a
  row aborted the whole restore.

### Added

- Observer on `\core\event\course_module_deleted` removing the stored option
  with its quiz.
- Daily scheduled task sweeping rows whose course module no longer exists. This
  covers course deletion, *restore and delete the current contents* and course
  import, none of which fire the module deletion event.
- `\local_quiz_summary_option\event\summary_option_updated`, logged whenever the
  stored value actually changes, so removing a submission safeguard from an
  assessed activity leaves an audit trail.
- Brazilian Portuguese language pack, kept in lockstep with English.
- PHPUnit suite covering the form callbacks, the storage layer, the request-time
  decision, the hook registration, the observer, the cleanup task and a full
  backup/restore round trip.
- `CLAUDE.md` recording the plugin's architecture and its non-obvious traps.

### Changed

- **Requires Moodle 5.1**; `$plugin->supported = [501, 502]` is now declared.
- The attempt-processing behaviour moved from the legacy `after_config` lib
  callback to `\core\hook\after_config`. The hook path has neither the legacy
  path's installed-plugin filter nor its exception containment, so the callback
  now checks that the plugin is installed and contains its own exceptions —
  without which a plugin copied onto the web nodes before the upgrade was run
  would break every quiz submission.
  The two course module form callbacks stay in `lib.php`: they have no Hooks API
  replacement on any supported branch.
- Form elements are frankenstyle-prefixed (`local_quiz_summary_option`), so they
  cannot be bound by a same-named quiz admin setting through
  `apply_admin_defaults()`.
- The global `SUMMARY_OPTION_SHOW` / `SUMMARY_OPTION_HIDE` constants became
  `\local_quiz_summary_option\local\option::SHOW` / `::HIDE`.
- Backup skips non-quiz modules instead of querying once per course module of
  every type; restore names its path element with `get_namefor()`.
- The help string and the README now state what **Hide** removes (the submission
  confirmation, the unanswered-question warning and *Return to attempt*) and
  that the option does not apply in the Moodle app.
- CI moved from the Catalyst reusable workflow to the moodle-an-hochschulen one,
  with a job per supported branch. The previous workflow gated every job behind
  a `pre_job` eligibility check that no branch of this fork satisfies on push,
  so pushes ran zero checks and still reported success. `phpdoc` and `behat`
  are no longer disabled, and a moodle.org release workflow was added.

### Removed

- The unused `configsummaryoption` language string, left over from a settings
  page that was never built.

## [v0.1] - 2021-06-09

Initial release by Catalyst IT.

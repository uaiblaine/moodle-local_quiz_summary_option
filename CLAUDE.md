# Claude instructions for `local_quiz_summary_option`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin.

Plugin context: a Moodle **local** plugin ("Quiz summary option") that adds a
per-quiz *Summary page* setting and, when it is set to Hide, makes
*Finish attempt ...* submit the attempt immediately instead of routing the
student through mod_quiz's summary of attempt page. It owns one table,
`local_quiz_summary_option` (`cmid`, `show_summary`, unique on `cmid`), holds no
personal data, and depends on mod_quiz's request vocabulary rather than on any
API mod_quiz publishes. Supports Moodle **5.1 through 5.2**
(`$plugin->requires = 2025100600`, `$plugin->supported = [501, 502]`). CI is the
moodle-an-hochschulen reusable workflow, one job per supported branch in
`.github/workflows/ci.yml` — **update those jobs when `supported` changes**.
Mounted into m501 and m502 at `local/quiz_summary_option`
(see `~/dev/moodle-dev/plugins.conf`).

This is a fork of `catalyst/moodle-local_quiz_summary_option`. Inherited files
keep Catalyst's copyright alongside the fork's; `MOODLE_405_STABLE` and
`MOODLE_39_STABLE` remain as the older-branch releases and must not be rebased
onto `main`.

## Agent orchestration budget (fleet rule, repeated here on purpose)

This is section 6 of `~/dev/CLAUDE.md`, mirrored into every repo of the fleet.
It is the one fleet rule these files are allowed to duplicate: a session opened
inside a plugin directory does not always carry the fleet file in context, and
the cost of missing this rule is paid immediately, in tokens, before anyone
notices it was missing.

**Every `Agent` call and every `agent()` inside a Workflow sets `model`
explicitly.** An omitted `model` runs that subagent on the session model — the
most expensive one — and is a defect, not a default:

- `sonnet` — readers, graders, refuters, verifiers, measurers, stale-reference
  sweeps, mechanical renames, test files written against a stated contract.
- `opus` — implementers of non-trivial code, ADR and documentation drafters,
  consolidators, critics, estimators. The alias means the **newest Opus**: since
  2026-09-22 that is Claude Opus 5.5 (`claude-opus-5-5`), measured by asking a
  subagent launched with `model: 'opus'` which model it runs on. Never pin
  `claude-opus-5` or any older Opus id. The `Agent` tool accepts aliases only
  (`sonnet`, `opus`, `haiku`, `fable`); `agent()` in a Workflow accepts an explicit
  id as well, but the alias is what to write — it follows the newest Opus without
  an edit here.
- the session model — only for work done inline in the main loop, never for a
  subagent.
- `effort` is set beside `model` on every call, never inherited: `high` for
  verifiers, readers and refuters, `xhigh` for implementers and fixers (the
  owner's rule of 2026-09-17). An omitted effort inherits the session's, and on
  Opus 5.5 an explicit one matters twice over — that model's own default is
  `medium`, one level below Opus 5.

Multi-agent workflows stay opt-in and lean whatever mode is on: size the fan-out
to the question (roughly 10 to 25 agents), one refuter per finding and only for
blocking findings, no open-ended "investigate every gap" rounds. Stop and resume
with `resumeFromRunId` rather than relaunching, so completed agents stay cached.
State which model each role got when reporting a launch.

Measured 2026-09-02 on the hub category-context gap analysis: 7 lenses x 2
refuters x 2 measurers plus a critic round, every one of them on the session
model, had to be interrupted for cost — 36 agents with the refuters on Sonnet
produced the same verified result. The rule has been restated three times
(2026-09-01, 2026-09-02, 2026-09-04), the last time over implementers launched
without `model` while the reviewers around them were correctly downgraded.

## Commands

```sh
mdl ci moodle-local_quiz_summary_option           # full CI locally before any push
mdl ci moodle-local_quiz_summary_option --matrix  # every leg GitHub runs
mdl phpunit m502 local_quiz_summary_option        # targeted tests
mdl purge m502                                    # after changing db/hooks.php or db/events.php
```

Any change to `db/hooks.php`, `db/events.php` or `db/tasks.php` needs a
`version.php` bump or the registration never takes effect.

## Code layout

```
lib.php                              Only the two course module form callbacks.
classes/local/option.php             Storage: is_shown/set/delete/purge_orphans + SHOW/HIDE.
classes/local/summary_page.php       The request-time decision. Read its comments before editing.
classes/hook_callbacks.php           \core\hook\after_config entry point.
classes/observer.php                 course_module_deleted -> option::delete().
classes/task/cleanup_orphans.php     Daily sweep for rows whose cm has gone.
classes/event/summary_option_updated.php
classes/tests/test_form.php          moodleform_mod stub for the form callback tests.
backup/moodle2/                      Module-level backup and restore of the stored flag.
```

## Architecture gotchas

**The `nextpage == -1` guard in `summary_page::maybe_skip()` is a safety
mechanism, not an accident.** `mod/quiz/attempt.php` stamps `nextpage = -1` into
the attempt form only when `is_last_page()` is true, so it is the plugin's only
proof that the student is on the last page — nothing else is available that
early in the request. Replacing it with core's own `$page` computation (which
would also match `thispage == -1`) looks like a bug fix and is not: the
navigation block's *Finish attempt ...* link is hijacked by
`mod/quiz/module.js` into `nav_to_page(-1)` from **any** page, so the wider
predicate would force `finishattempt` on a student sitting on page 1 of 10.
`processattempt.php` skips its own out-of-sequence check once `finishattempt` is
set, so unopened pages would be submitted unanswered with no warning. Core
deliberately sends that click to the summary page. Leave the guard alone.

**`coursemodule` is the EMPTY STRING on the add path, not null or 0.**
`prepare_new_moduleinfo_data()` sets `$data->coursemodule = ''`, and the value
reaches the DML layer untouched (`where_clause()` special-cases only NULL). A
bigint comparison against `''` makes PostgreSQL throw `dml_read_exception` and
the quiz settings page dies, while MySQL coerces it to `0` and looks fine. Cast
before any lookup. `tests/lib_test.php::test_add_form_handles_empty_string_coursemodule`
is the regression test, and the stub's default is `''` for that reason.

**An absent form property means "not submitted", never "Show".** Core's public
`update_module()` API builds a `moduleinfo` carrying only `modulename`, `scale`
and `type`, and it does set `modulename` to `quiz`, so the plugin's early return
does not save it. Defaulting an absent property silently reset every teacher's
Hide whenever any tool touched the quiz for another reason.

**Form element names are frankenstyle-prefixed on purpose.** They are injected
into mod_quiz's own form namespace, and `moodleform_mod::apply_admin_defaults()`
binds any `quiz` admin setting whose name matches an element — its one attempted
exclusion has its `strpos()` arguments reversed and never skips anything. The
value also rides into `quiz_add_instance()` and is discarded only by DML column
filtering. The element name and the `$moduleinfo` property read in
`local_quiz_summary_option_coursemodule_edit_post_actions()` must be renamed
together, or the absent-property guard starts firing for every save.

**The hook path is not a drop-in for the legacy `after_config` callback.**
`\core\hook\manager` enumerates `db/hooks.php` off disk with
`core_component::get_plugin_list()` — no installed-plugin filter — and
`dispatch()` has no try/catch, unlike
`\core\hook\after_config::process_legacy_callbacks()`. Both protections are
re-implemented in `hook_callbacks::after_config()`; without them a plugin copied
onto the web nodes before the upgrade is run would throw out of `config.php` and
no attempt on the site could be finished. Do not "simplify" either guard away.

**`remove_course_contents()` fires no `course_module_deleted` event.** The
observer covers deleting a single activity; course deletion, *restore and delete
the current contents* (`restore_dbops::delete_course_content()`) and course
import all go through `remove_course_contents()` instead, which just deletes the
`course_modules` rows. That is why the scheduled task exists, and why an
observer-only cleanup would leak on the highest-volume deletion path on a site.

**The form section must open itself when Hide is stored, and the stored value must
be published on `$current`.** Two separate core contracts, both invisible until
broken. formslib collapses every header after the first that holds no required or
errored element (`lib/formslib.php` — the branch is guarded by
`!isset($this->_collapsibleElements[$headername])`, which is why an explicit
`setExpanded()` from the plugin wins), so without it a quiz with Hide looks
identical to one without it. And `moodleform_mod::apply_admin_locked_flags()`
decides whether to freeze a locked element by comparing the site value against
`$this->current->$name`, treating an **absent** property as "matches" — so a site
that locked `quiz/local_quiz_summary_option` through mod_quiz's admin defaults
would freeze the field to the site value and the next save would silently
overwrite the teacher's choice. Publish it on the edit path only: on the add path
the property would override the site default `apply_admin_defaults()` is entitled
to set. Both are mutation-covered in `tests/lib_test.php`.

**`classes/tests/test_form.php` inherits `$current` and `get_current()` from
`moodleform_mod` on purpose.** `moodleform_mod` declares `protected $current` and
its own getter, so redeclaring the property as `private` is a PHP fatal, and
overriding `get_current()` to build a fresh object each call would make the stub
disagree with the real contract — a callback that writes onto the current data
could not be tested at all.

**Two known limitations, both deliberate and both documented in the help string
and README.** The option is browser-only: `mod_quiz_process_attempt` finishes
attempts for the Moodle app without loading `processattempt.php`, and mod_quiz
ships no `db/mobile.php` for a local plugin to hook. And an attempt that goes
overdue is redirected to the summary page by `processattempt.php` itself
whatever the setting says.

**Deliberately not implemented, with the reasoning — do not revisit these
without a new fact.**

*No capability, settled by evidence.* `grep -n capability mod/quiz/mod_form.php`
returns nothing: core gates strictly more student-impacting quiz settings with
none — `navmethod = sequential` bars returning to any earlier page, `attempts = 1`
makes every attempt final, `browsersecurity` locks the browser down. All of
mod_quiz's own capabilities answer "who may *do* this", never "which value may
this setting take"; `mod/quiz:manage` is the single undivided right to edit quiz
settings. A new capability would be `CAP_ALLOW` for editingteacher and manager by
default, so it would change nothing on any default site. Revisit only if the
plugin ever gains a setting that changes what a *student may do* in an attempt —
that is the boundary mod_quiz's capabilities actually draw.

*No site-level default.* Nobody has asked in five years across three branches, and
the only safe form is an add-form default that does not touch existing quizzes,
which is not what an admin would expect. The inherited `configsummaryoption` lang
string was the description half of a `settings.php` upstream never wrote; it was
deleted rather than resurrected, and the current core convention is `<name>_desc`
anyway. If one is ever added it must be applied in *both* `option::is_shown()` and
the form default in the same commit, or the settings page will lie — the runtime
path returns on a missing row before any default is consulted.

*No confirmation dialogue yet.* Recovering the submission confirmation without the
summary page is feasible and is the one genuine feature left: bind a plugin-owned
AMD module to the attempt page's finish button through
`\core\hook\output\before_footer_html_generation`. Three things decide the
design. It must be a **third value of the select**, never a change to existing
Hide rows, or an upgrade would alter behaviour on live exams. The re-submit must
use `button.click()`, never `form.submit()` — the latter drops the submitter's
`next` field, `summary_page::maybe_skip()` then falls through, and the student is
silently routed to the summary page while the dialogue makes everything look
right. And it needs a re-entrancy flag, or the re-click loops. Core's own
`mod_quiz/submission_confirmation` cannot be reused: it is bound to the summary
page's `.btn-finishattempt button` / `form#frm-finishattempt`. The cost is the
plugin's first AMD source, tracked `amd/build`, grunt, eslint and Behat.

## Testing notes

- The storage layer logs an event against the module context, so tests need a
  **real** course module. `option::set()` on a fabricated cmid silently skips
  the event (`IGNORE_MISSING`), which would hide a mistake — create a quiz.
- `resetAfterTest()` does not restore `$_GET`, `$_POST` or `$SCRIPT`.
  `summary_page_test` and `hook_callbacks_test` save and restore them in
  `setUp`/`tearDown`; without that, tests pass or fail depending on order.
- Every negative assertion in this suite carries a **control** — the same
  arrangement with one field flipped, asserted to fire. The suite this replaced
  had two tests that returned at the second guard and asserted nothing.
- `tests/backup_restore_test.php` copies core's `backup_and_restore()` helper
  shape: `MODE_IMPORT` on the backup side leaves the result unzipped so the
  restore controller can read it back directly.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it matches no existing shape,
re-examine the approach.

moodle-local_quiz_summary_option
================================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-local_quiz_summary_option/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-local_quiz_summary_option/actions/workflows/ci.yml?query=branch%3Amain)

Adds a **Summary page** setting to every quiz, so a teacher can send students
straight to their review page when they finish an attempt instead of through
the summary of attempt page.

The setting appears in the quiz settings form under *Summary page option*, and
stores one row per quiz. Choosing **Hide** makes the plugin rewrite the
attempt-processing request so that selecting *Finish attempt ...* submits the
attempt immediately.

What Hide changes for students
------------------------------

The summary of attempt page is not only a waypoint — it carries three things
that go with it, and this is worth knowing before turning the option on:

- the **submission confirmation** dialogue,
- the **warning about unanswered questions**,
- the **Return to attempt** button.

With **Hide**, one click on *Finish attempt ...* submits and grades the attempt
with none of those. On a graded, single-attempt quiz that is not recoverable.
Anyone who can edit the activity can set this, so treat it as a decision about
assessment design rather than a cosmetic tweak.

Two behaviours are deliberately left to core:

- **Selecting *Finish attempt ...* in the navigation block from an earlier page**
  still goes to the summary page. That click is core's own "I want to review
  before submitting" path, and honouring the option there would submit pages the
  student never opened, with no warning.
- **An attempt that goes overdue** is redirected to the summary page by
  mod_quiz itself, whatever this setting says.

Limitations
-----------

The option applies to the **web interface only**. The Moodle app and any client
of the official mobile service finish attempts through the
`mod_quiz_process_attempt` web service, which never loads
`mod/quiz/processattempt.php`, so the plugin's code does not run for them. This
cannot be fixed from a local plugin; it needs a change in mod_quiz or the app.

The setting is a navigation convenience, not an anti-cheat control: the request
fields it reads are supplied by the browser.

Requirements
------------

- Moodle 5.1 or later (tested up to Moodle 5.2)

For Moodle 4.5 use the `MOODLE_405_STABLE` branch; for Moodle 3.9 use
`MOODLE_39_STABLE`.

| Moodle version | Branch            | PHP  |
|----------------|-------------------|------|
| Moodle 5.1+    | main              | 8.2+ |
| Moodle 4.5     | MOODLE_405_STABLE | 8.1  |
| Moodle 3.9     | MOODLE_39_STABLE  | 7.2  |

Installation
------------

Install the plugin like any other plugin to folder `/local/quiz_summary_option`.

See http://docs.moodle.org/en/Installing_plugins for details on installing
Moodle plugins.

Installing via git:

```sh
git clone https://github.com/uaiblaine/moodle-local_quiz_summary_option.git local/quiz_summary_option
```

Usage
-----

1. Go to the course holding the quiz and turn editing on.
2. Create or edit a quiz activity.
3. Open the **Summary page option** section.
4. Choose **Show** (the default) or **Hide**, and save.

No configuration is needed. Quizzes with no stored setting show the summary
page, so installing the plugin changes nothing until a teacher opts in.

Data and privacy
----------------

The plugin owns one table, `local_quiz_summary_option`, holding a course module
id and a display flag. Nothing in it identifies a user, so the privacy provider
is a `null_provider`.

Rows are removed when their quiz is deleted, through an observer on
`\core\event\course_module_deleted`. Core's `remove_course_contents()` does not
fire that event, so course deletion, *restore and delete the current contents*
and course import are covered instead by a daily scheduled task that sweeps rows
whose course module has gone.

The setting travels with the activity through backup, restore and course
import.

Credits
-------

Originally written by **Christina Roperto** at
[Catalyst IT Australia](https://www.catalyst-au.net/) and published as
[catalyst/moodle-local_quiz_summary_option](https://github.com/catalyst/moodle-local_quiz_summary_option).
This fork continues that work for Moodle 5.1 and 5.2. Copyright on the inherited
files stays with Catalyst IT.

Issues for this fork belong in
[its own tracker](https://github.com/uaiblaine/moodle-local_quiz_summary_option/issues);
please do not send them to Catalyst.

License
-------

2021 Catalyst IT, 2026 Anderson Blaine

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see https://www.gnu.org/licenses/.

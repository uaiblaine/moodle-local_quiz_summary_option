<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings.
 *
 * @package    local_quiz_summary_option
 * @copyright  2021 Catalyst IT
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['event_summary_option_updated'] = 'Quiz summary page option updated';
$string['pluginname'] = 'Quiz summary option';
$string['privacy:metadata'] = 'The Quiz summary option plugin stores one display setting per quiz and does not store any personal data.';
$string['summaryoption'] = 'Summary page';
$string['summaryoption_help'] = 'Choose whether students see the summary of attempt page when they finish a quiz attempt.

**Show** keeps the standard behaviour. The student reaches a page listing every question, where unanswered questions
are flagged, the attempt can be resumed with *Return to attempt*, and the submission has to be confirmed.

**Hide** submits the attempt as soon as the student selects *Finish attempt ...* on the last page. Because the
summary page is skipped, the submission confirmation, the warning about unanswered questions and the *Return to
attempt* button are not shown. A submitted attempt cannot be reopened, so on a quiz that allows a single attempt
an accidental click can only be undone by a teacher deleting the attempt.

Selecting *Finish attempt ...* in the navigation block from an earlier page still opens the summary page, and this
setting applies to the web interface only. Attempts finished in the Moodle app are not affected.';
$string['summaryoption_hide'] = 'Hide';
$string['summaryoption_show'] = 'Show';
$string['summarypageoption'] = 'Summary page option';
$string['task_cleanup_orphans'] = 'Remove summary page options for deleted quizzes';

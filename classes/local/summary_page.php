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

namespace local_quiz_summary_option\local;

/**
 * Decides, very early in the request, whether to skip the quiz summary of attempt page.
 *
 * mod_quiz offers no extension point around the attempt-processing flow, so the only
 * lever available is to set the finishattempt parameter before
 * mod/quiz/processattempt.php reads it. That script includes config.php as its first
 * statement, and core dispatches \core\hook\after_config from lib/setup.php once
 * $SCRIPT has been resolved and long before the target script reads its parameters.
 *
 * @package    local_quiz_summary_option
 * @copyright  2021 Catalyst IT
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary_page {
    /** @var string The only script this plugin ever acts on. */
    public const PROCESS_ATTEMPT_SCRIPT = '/mod/quiz/processattempt.php';

    /**
     * Force the attempt to finish immediately when the summary page is hidden.
     *
     * Every guard below is deliberate; read the comments before relaxing one.
     *
     * @return bool True when the request was rewritten to finish the attempt.
     */
    public static function maybe_skip(): bool {
        global $SCRIPT;

        /* Browser flow only. Attempts finished through mod_quiz_process_attempt (the
           Moodle app and any client of the official mobile service) reach
           quiz_attempt::process_attempt() without loading this script, so the option
           has no effect there. That limitation is documented in the help string and
           in README.md; it cannot be closed from a local plugin. */
        if (!isset($SCRIPT) || $SCRIPT !== self::PROCESS_ATTEMPT_SCRIPT) {
            return false;
        }

        /* nextpage is a hidden field stamped at render time, and mod/quiz/attempt.php
           sets it to -1 only when is_last_page() is true. It is therefore the plugin's
           only proof that the student is looking at the last page of the attempt, and
           it is what stops a mid-attempt "Finish attempt ..." click from submitting
           pages the student never opened. Widening this guard to cover the navigation
           block link from earlier pages would trade a minor navigation surprise for
           blind submission of an unfinished graded attempt — core deliberately sends
           that click to the summary page, where the confirmation dialog and the
           unanswered-question count live. Leave it alone. */
        if (optional_param('nextpage', 0, PARAM_INT) !== -1) {
            return false;
        }

        /* From the last page the summary can be requested two ways: the "Finish
           attempt ..." submit button, which posts next; and the navigation block
           link, which mod/quiz/module.js rewrites to thispage = -1 after blanking
           the submit button's name, so next is absent. Anything else reaching this
           point — the timer auto-submit, a plain "Previous page" — must be left to
           core. */
        $next = optional_param('next', null, PARAM_TEXT);
        if ($next === null && optional_param('thispage', 0, PARAM_INT) !== -1) {
            return false;
        }

        /* cmid rides on the attempt form's action and on the summary page's own
           button. It is advisory to core, which authenticates the request from the
           attempt id instead, so a missing or unknown value simply means "no stored
           option" and the summary page is shown. PARAM_INT also normalises the empty
           string to 0, which keeps the lookup off the database entirely. */
        if (option::is_shown(optional_param('cmid', 0, PARAM_INT))) {
            return false;
        }

        /* processattempt.php reads finishattempt through optional_param, which
           consults $_POST and then $_GET, after config.php has returned. */
        $_GET['finishattempt'] = 1;

        return true;
    }
}

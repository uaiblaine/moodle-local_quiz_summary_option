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
 * Callbacks core looks for in a plugin's lib.php.
 *
 * The two course-module form callbacks have no Hooks API replacement on any
 * supported branch — course/moodleform_mod.php and course/modlib.php still
 * dispatch them through get_plugins_with_function() without the
 * migrated-to-hook flag — so lib.php remains their correct home.
 *
 * @package    local_quiz_summary_option
 * @copyright  2021 Catalyst IT
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_quiz_summary_option\local\option;

/**
 * Adds the summary page option to the quiz settings form.
 *
 * @param moodleform_mod $formwrapper The course module form being built.
 * @param MoodleQuickForm $mform The form to add elements to.
 * @return void
 */
function local_quiz_summary_option_coursemodule_standard_elements(moodleform_mod $formwrapper, MoodleQuickForm $mform) {
    $current = $formwrapper->get_current();
    if (empty($current->modulename) || $current->modulename !== 'quiz') {
        return;
    }

    /* On the add path core sets coursemodule to the EMPTY STRING
       (prepare_new_moduleinfo_data()), not to null or zero. Passing that straight
       into a bigint comparison makes PostgreSQL reject the query and the settings
       page dies, while MySQL silently coerces it to 0 — so the cast is what keeps
       "Add an activity -> Quiz" working, and it fails on only half the CI matrix
       if it is removed. */
    $cmid = (int) ($current->coursemodule ?? 0);

    $mform->addElement(
        'header',
        'local_quiz_summary_optionhdr',
        get_string('summarypageoption', 'local_quiz_summary_option')
    );
    $mform->addElement(
        'select',
        'local_quiz_summary_option',
        get_string('summaryoption', 'local_quiz_summary_option'),
        [
            option::SHOW => get_string('summaryoption_show', 'local_quiz_summary_option'),
            option::HIDE => get_string('summaryoption_hide', 'local_quiz_summary_option'),
        ]
    );
    $mform->setDefault('local_quiz_summary_option', option::is_shown($cmid) ? option::SHOW : option::HIDE);
    $mform->addHelpButton('local_quiz_summary_option', 'summaryoption', 'local_quiz_summary_option');
}

/**
 * Stores the submitted summary page option after a quiz is created or updated.
 *
 * @param stdClass $moduleinfo The module data just saved by core.
 * @param stdClass $course The course the module belongs to.
 * @return stdClass The unmodified module data, as core expects it back.
 */
function local_quiz_summary_option_coursemodule_edit_post_actions($moduleinfo, $course) {
    if (empty($moduleinfo->modulename) || $moduleinfo->modulename !== 'quiz') {
        return $moduleinfo;
    }

    /* An absent property means "not submitted through the settings form", not "show".
       Core's own update_module() API builds a moduleinfo carrying only modulename,
       scale and type, so treating absence as the default would silently reset a
       teacher's Hide back to Show every time an unrelated tool updated the quiz.
       The element name is frankenstyle-prefixed because it is added into mod_quiz's
       own form namespace, where apply_admin_defaults() binds any quiz admin setting
       whose name matches an element. */
    if (!property_exists($moduleinfo, 'local_quiz_summary_option')) {
        return $moduleinfo;
    }

    option::set(
        (int) $moduleinfo->coursemodule,
        $moduleinfo->local_quiz_summary_option !== option::HIDE
    );

    return $moduleinfo;
}

/**
 * Skips the summary of attempt page when the option is set to hide it.
 *
 * @return void
 */
function local_quiz_summary_option_after_config() {
    global $SCRIPT;

    if (!isset($SCRIPT) || $SCRIPT !== '/mod/quiz/processattempt.php') {
        return;
    }

    if (optional_param('nextpage', 0, PARAM_INT) !== -1) {
        return;
    }

    $next = optional_param('next', null, PARAM_TEXT);
    if ($next === null && optional_param('thispage', 0, PARAM_INT) !== -1) {
        return;
    }

    if (option::is_shown(optional_param('cmid', 0, PARAM_INT))) {
        return;
    }

    $_GET['finishattempt'] = 1;
}

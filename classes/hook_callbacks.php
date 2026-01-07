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

namespace local_quiz_summary_option;

/**
 * Hook callbacks for local_quiz_summary_option.
 *
 * @package   local_quiz_summary_option
 * @author    2026 Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Runs after config has been set.
     *
     * @param \core\hook\after_config $hook
     * @return void|null
     */
    public static function after_config(\core\hook\after_config $hook) {
        global $CFG;

        if (during_initial_install() || isset($CFG->upgraderunning)) {
            return;
        }

        // Handles edge case during upgrade & install where this callback doesn't have the lib loaded.
        if (!function_exists('local_quiz_summary_option_after_config')) {
            require_once($CFG->dirroot . '/local/quiz_summary_option/lib.php');
        }

        local_quiz_summary_option_after_config();
    }
}

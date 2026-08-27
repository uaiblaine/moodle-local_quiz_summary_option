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

use local_quiz_summary_option\local\summary_page;

/**
 * Hook callbacks.
 *
 * @package    local_quiz_summary_option
 * @copyright  2026 Catalyst IT
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Runs as soon as core has finished setting up its configuration.
     *
     * @param \core\hook\after_config $hook The hook instance, unused.
     * @return void
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG;

        if (during_initial_install() || isset($CFG->upgraderunning)) {
            return;
        }

        /* The hook manager registers db/hooks.php callbacks for every plugin present
           on disk (\core\hook\manager::get_hook_callbacks() lists them with
           core_component::get_plugin_list()), whereas the legacy callback path this
           replaces skipped plugins that were not installed yet. Without this check a
           plugin copied onto the web nodes before the upgrade is run would query a
           table that does not exist, on every last-page quiz submission. */
        if (!get_config('local_quiz_summary_option', 'version')) {
            return;
        }

        /* \core\hook\manager::dispatch() calls callbacks without a try/catch, unlike
           \core\hook\after_config::process_legacy_callbacks(). An exception escaping
           here would abort the request inside config.php, losing the student's
           answers, so contain it the way the legacy path did. */
        try {
            summary_page::maybe_skip();
        } catch (\Throwable $e) {
            debugging(
                'local_quiz_summary_option after_config failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER,
                $e->getTrace()
            );
        }
    }
}

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

namespace local_quiz_summary_option\task;

use local_quiz_summary_option\local\option;

/**
 * Removes stored options whose quiz no longer exists.
 *
 * @package    local_quiz_summary_option
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_orphans extends \core\task\scheduled_task {
    /**
     * Localised task name shown in the scheduled tasks report.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_orphans', 'local_quiz_summary_option');
    }

    /**
     * Delete every row whose course module has gone.
     *
     * @return void
     */
    public function execute(): void {
        $removed = option::purge_orphans();

        mtrace("local_quiz_summary_option: removed $removed orphaned row(s).");
    }
}

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

use local_quiz_summary_option\local\option;

/**
 * Event observers.
 *
 * @package    local_quiz_summary_option
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Drop the stored option when its course module is deleted.
     *
     * Deleting by course module id unconditionally rather than only for quizzes: the
     * write is a single indexed delete, and keying it on the module name would leak
     * rows for good if the name in the event ever disagreed with what was stored.
     *
     * @param \core\event\course_module_deleted $event The event, whose objectid is the cmid.
     * @return void
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        option::delete((int) $event->objectid);
    }
}

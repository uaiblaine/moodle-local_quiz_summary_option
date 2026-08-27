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

use local_quiz_summary_option\local\option;

/**
 * Restore steps for the quiz summary page option.
 *
 * @package    local_quiz_summary_option
 * @category   backup
 * @copyright  2021 Catalyst IT
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores the stored summary page option onto the newly created quiz.
 */
class restore_local_quiz_summary_option_plugin extends restore_local_plugin {
    /**
     * Declare the element this plugin restores from a module backup.
     *
     * The element name comes from get_namefor() so it matches the processing method
     * core derives from it ('process_' . name) and cannot collide with another
     * plugin's element at the same connection point.
     *
     * @return array The restore path elements to register.
     */
    protected function define_module_plugin_structure() {
        return [
            new restore_path_element($this->get_namefor(''), $this->get_pathfor('')),
        ];
    }

    /**
     * Store the restored option against the new course module.
     *
     * Core dispatches this with the chunk's tags as an ARRAY, not an object.
     * The write goes through option::set() so that restoring into a course module
     * that already has a row updates it instead of hitting the unique index on cmid
     * and aborting the whole restore.
     *
     * @param array $data The backed up element, holding show_summary.
     * @return void
     */
    public function process_local_quiz_summary_option($data) {
        option::set((int) $this->task->get_moduleid(), !empty($data['show_summary']));
    }
}

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
 * Backup steps for the quiz summary page option.
 *
 * @package    local_quiz_summary_option
 * @category   backup
 * @copyright  2021 Catalyst IT
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the stored summary page option to a quiz activity backup.
 */
class backup_local_quiz_summary_option_plugin extends backup_local_plugin {
    /**
     * Attach this plugin's data to the module element being backed up.
     *
     * The return value is ignored by backup_plugin::define_plugin_structure(); the
     * connection happens as a side effect of get_plugin_element(). Returning before
     * that call is therefore how a plugin opts out of a module entirely.
     *
     * @return backup_plugin_element|null The plugin element, or null for non-quiz modules.
     */
    protected function define_module_plugin_structure() {
        // Every module type reaches this callback, so skip the rest without a query.
        if ($this->task->get_modulename() !== 'quiz') {
            return null;
        }

        $sqlcmid = backup_helper::is_sqlparam($this->get_setting_value(backup::VAR_MODID));
        $quizsummary = new backup_nested_element($this->get_recommended_name(), [], ['show_summary']);
        $quizsummary->set_source_table('local_quiz_summary_option', ['cmid' => $sqlcmid]);

        $plugin = $this->get_plugin_element();
        $plugin->add_child($quizsummary);

        return $plugin;
    }
}

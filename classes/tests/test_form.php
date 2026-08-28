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

namespace local_quiz_summary_option\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Minimal moodleform_mod stand-in for the course module form callback tests.
 *
 * The real form cannot be constructed without a course, a module and a section, and the
 * callback under test reads nothing but get_current(), so the parent constructor is
 * deliberately skipped. Everything else is inherited: $current is moodleform_mod's own
 * protected property and get_current() is its own getter, so a callback that writes a
 * property onto the current data is exercised against the real contract rather than
 * against a stub that hands out a fresh object each call.
 *
 * @package    local_quiz_summary_option
 * @category   test
 * @copyright  2021 Catalyst IT
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_form extends \moodleform_mod {
    /**
     * Build the stub without invoking moodleform_mod's constructor.
     *
     * @param string $modulename Module name to report.
     * @param mixed $coursemodule Course module id to report. Pass '' for the add path,
     *                            which is what prepare_new_moduleinfo_data() supplies.
     */
    public function __construct($modulename = 'quiz', $coursemodule = '') {
        $this->current = (object) [
            'modulename' => $modulename,
            'coursemodule' => $coursemodule,
        ];
    }

    /**
     * Form definition. Nothing is needed for these tests.
     *
     * @return void
     */
    protected function definition() {
    }
}

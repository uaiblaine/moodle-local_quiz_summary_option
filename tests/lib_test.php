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
use local_quiz_summary_option\tests\test_form;
use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Tests for the course module form callbacks in lib.php.
 *
 * @package    local_quiz_summary_option
 * @category   test
 * @copyright  2021 Catalyst IT
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('local_quiz_summary_option_coursemodule_standard_elements')]
#[CoversFunction('local_quiz_summary_option_coursemodule_edit_post_actions')]
final class lib_test extends \advanced_testcase {
    /**
     * Create a real quiz and return its course module id.
     *
     * A genuine course module is required because storing the option logs an event
     * against the module context.
     *
     * @return int
     */
    private function create_quiz_cmid(): int {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        return (int) $quiz->cmid;
    }

    /**
     * Build the module data core hands to the post-actions callback.
     *
     * @param int $cmid Course module id.
     * @param string|null $value Submitted option, or null to omit the property entirely.
     * @param string $modulename Module name.
     * @return \stdClass
     */
    private function moduleinfo(int $cmid, ?string $value, string $modulename = 'quiz'): \stdClass {
        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = $modulename;
        $moduleinfo->coursemodule = $cmid;
        if ($value !== null) {
            $moduleinfo->local_quiz_summary_option = $value;
        }

        return $moduleinfo;
    }

    /**
     * The select is added to the quiz settings form.
     *
     * @return void
     */
    public function test_element_is_added_for_quiz(): void {
        $this->resetAfterTest();

        $mform = new \MoodleQuickForm('test', 'POST', 'test');
        local_quiz_summary_option_coursemodule_standard_elements(new test_form(), $mform);

        $this->assertInstanceOf(\MoodleQuickForm_select::class, $mform->getElement('local_quiz_summary_option'));
    }

    /**
     * Nothing is added to the settings form of any other module type.
     *
     * @return void
     */
    public function test_element_is_not_added_for_other_modules(): void {
        $this->resetAfterTest();

        $mform = new \MoodleQuickForm('test', 'POST', 'test');
        local_quiz_summary_option_coursemodule_standard_elements(new test_form('forum'), $mform);

        $this->assertFalse($mform->elementExists('local_quiz_summary_option'));
    }

    /**
     * The add-a-quiz form works, where core supplies the EMPTY STRING as the cmid.
     *
     * prepare_new_moduleinfo_data() sets coursemodule to '' rather than to null or 0.
     * Feeding that into a bigint comparison makes PostgreSQL abort the request, so
     * this test fails on the pgsql legs the moment the cast in lib.php is removed —
     * and passes on MySQL either way, which is why the defect survived for years.
     *
     * @return void
     */
    public function test_add_form_handles_empty_string_coursemodule(): void {
        $this->resetAfterTest();

        $mform = new \MoodleQuickForm('test', 'POST', 'test');
        local_quiz_summary_option_coursemodule_standard_elements(new test_form('quiz', ''), $mform);

        $this->assertSame(option::SHOW, $mform->_defaultValues['local_quiz_summary_option']);
    }

    /**
     * The select defaults to whichever value is stored for the quiz.
     *
     * Both directions are asserted in one test so that neither can pass by the
     * callback failing to run at all.
     *
     * @return void
     */
    public function test_edit_form_defaults_to_the_stored_value(): void {
        $this->resetAfterTest();
        $cmid = $this->create_quiz_cmid();

        $mform = new \MoodleQuickForm('test', 'POST', 'test');
        local_quiz_summary_option_coursemodule_standard_elements(new test_form('quiz', $cmid), $mform);
        $this->assertSame(option::SHOW, $mform->_defaultValues['local_quiz_summary_option']);

        option::set($cmid, false);
        $mform = new \MoodleQuickForm('test', 'POST', 'test');
        local_quiz_summary_option_coursemodule_standard_elements(new test_form('quiz', $cmid), $mform);
        $this->assertSame(option::HIDE, $mform->_defaultValues['local_quiz_summary_option']);

        option::set($cmid, true);
        $mform = new \MoodleQuickForm('test', 'POST', 'test');
        local_quiz_summary_option_coursemodule_standard_elements(new test_form('quiz', $cmid), $mform);
        $this->assertSame(option::SHOW, $mform->_defaultValues['local_quiz_summary_option']);
    }

    /**
     * Saving the form stores both possible values, including the one the plugin exists for.
     *
     * @return void
     */
    public function test_post_actions_stores_the_submitted_value(): void {
        global $DB;
        $this->resetAfterTest();
        $cmid = $this->create_quiz_cmid();

        local_quiz_summary_option_coursemodule_edit_post_actions($this->moduleinfo($cmid, option::HIDE), null);
        $this->assertSame(0, (int) $DB->get_field('local_quiz_summary_option', 'show_summary', ['cmid' => $cmid]));

        local_quiz_summary_option_coursemodule_edit_post_actions($this->moduleinfo($cmid, option::SHOW), null);
        $this->assertSame(1, (int) $DB->get_field('local_quiz_summary_option', 'show_summary', ['cmid' => $cmid]));
        $this->assertSame(1, $DB->count_records('local_quiz_summary_option', ['cmid' => $cmid]));
    }

    /**
     * A module that is not a quiz never gets a row.
     *
     * The quiz control proves the callback would otherwise have written one.
     *
     * @return void
     */
    public function test_post_actions_ignores_other_modules(): void {
        global $DB;
        $this->resetAfterTest();
        $cmid = $this->create_quiz_cmid();

        local_quiz_summary_option_coursemodule_edit_post_actions($this->moduleinfo($cmid, option::HIDE, 'forum'), null);
        $this->assertFalse($DB->record_exists('local_quiz_summary_option', ['cmid' => $cmid]));

        local_quiz_summary_option_coursemodule_edit_post_actions($this->moduleinfo($cmid, option::HIDE), null);
        $this->assertTrue($DB->record_exists('local_quiz_summary_option', ['cmid' => $cmid]));
    }

    /**
     * A moduleinfo without the property leaves the stored value alone.
     *
     * Core's own update_module() API builds a moduleinfo carrying only modulename,
     * scale and type, so treating an absent property as "show" would silently undo
     * a teacher's setting whenever any tool updated the quiz for another reason.
     *
     * @return void
     */
    public function test_post_actions_keeps_the_stored_value_when_not_submitted(): void {
        global $DB;
        $this->resetAfterTest();
        $cmid = $this->create_quiz_cmid();
        option::set($cmid, false);

        local_quiz_summary_option_coursemodule_edit_post_actions($this->moduleinfo($cmid, null), null);
        $this->assertSame(0, (int) $DB->get_field('local_quiz_summary_option', 'show_summary', ['cmid' => $cmid]));

        // Control: the same call WITH the property does rewrite the value.
        local_quiz_summary_option_coursemodule_edit_post_actions($this->moduleinfo($cmid, option::SHOW), null);
        $this->assertSame(1, (int) $DB->get_field('local_quiz_summary_option', 'show_summary', ['cmid' => $cmid]));
    }

    /**
     * The callback returns the module data core passed in, as core requires.
     *
     * @return void
     */
    public function test_post_actions_returns_the_moduleinfo(): void {
        $this->resetAfterTest();
        $cmid = $this->create_quiz_cmid();
        $moduleinfo = $this->moduleinfo($cmid, option::HIDE);

        $this->assertSame($moduleinfo, local_quiz_summary_option_coursemodule_edit_post_actions($moduleinfo, null));

        $other = $this->moduleinfo($cmid, option::HIDE, 'forum');
        $this->assertSame($other, local_quiz_summary_option_coursemodule_edit_post_actions($other, null));
    }
}

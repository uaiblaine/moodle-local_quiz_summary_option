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

use backup;
use backup_controller;
use backup_setting;
use local_quiz_summary_option\local\option;
use restore_controller;
use restore_dbops;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Round trip tests for the module level backup and restore steps.
 *
 * @package    local_quiz_summary_option
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\backup_local_quiz_summary_option_plugin::class)]
#[CoversClass(\restore_local_quiz_summary_option_plugin::class)]
final class backup_restore_test extends \advanced_testcase {
    /** @var int Counter keeping restored course shortnames unique within one test. */
    private $copycount = 0;

    /**
     * Back a course up and restore it into a brand new course.
     *
     * MODE_IMPORT on the backup side leaves the result as a directory rather than a
     * zip, which is what lets the restore controller read it straight back; this
     * mirrors core's own backup_and_restore() helper in
     * backup/moodle2/tests/moodle2_test.php.
     *
     * @param \stdClass $course The course to copy.
     * @return int Id of the new course.
     */
    private function backup_and_restore(\stdClass $course): int {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(false);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_copy' . (++$this->copycount),
            $course->category
        );
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(false);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * The course module id of the single quiz in a course.
     *
     * @param int $courseid Course id.
     * @param string $name Quiz name to look for.
     * @return int
     */
    private function find_quiz_cmid(int $courseid, string $name): int {
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            if ($cm->name === $name) {
                return (int) $cm->id;
            }
        }
        $this->fail("No quiz called $name in course $courseid.");
    }

    /**
     * A hidden summary page survives a backup and restore onto a new course module.
     *
     * The second quiz, left at the default, is the control: it proves the restored
     * value came from the backup rather than from every quiz getting a row.
     *
     * @return void
     */
    public function test_the_stored_option_survives_a_round_trip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $hidden = $this->getDataGenerator()->create_module(
            'quiz',
            ['course' => $course->id, 'name' => 'Hidden summary']
        );
        $this->getDataGenerator()->create_module(
            'quiz',
            ['course' => $course->id, 'name' => 'Default summary']
        );
        option::set((int) $hidden->cmid, false);

        $newcourseid = $this->backup_and_restore($course);

        $restoredhidden = $this->find_quiz_cmid($newcourseid, 'Hidden summary');
        $restoreddefault = $this->find_quiz_cmid($newcourseid, 'Default summary');

        $this->assertNotSame((int) $hidden->cmid, $restoredhidden, 'The restore must create a new module.');
        $this->assertFalse(option::is_shown($restoredhidden));
        $this->assertTrue(option::is_shown($restoreddefault));

        // The original course is untouched.
        $this->assertFalse(option::is_shown((int) $hidden->cmid));
    }

    /**
     * Restoring twice into the same course does not collide on the unique cmid index.
     *
     * @return void
     */
    public function test_restoring_the_same_course_twice_is_safe(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module(
            'quiz',
            ['course' => $course->id, 'name' => 'Hidden summary']
        );
        option::set((int) $quiz->cmid, false);

        $first = $this->backup_and_restore($course);
        $second = $this->backup_and_restore($course);

        $this->assertFalse(option::is_shown($this->find_quiz_cmid($first, 'Hidden summary')));
        $this->assertFalse(option::is_shown($this->find_quiz_cmid($second, 'Hidden summary')));
    }
}

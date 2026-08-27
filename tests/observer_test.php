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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the course module deletion observer.
 *
 * @package    local_quiz_summary_option
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(observer::class)]
final class observer_test extends \advanced_testcase {
    /**
     * Delete a course module through whichever core API the branch offers.
     *
     * Moodle 5.2 moved this into core_courseformat and deprecated the global
     * function, whose debugging() notice would fail the suite; Moodle 5.1 has no
     * cmactions::delete() at all. Both paths fire \core\event\course_module_deleted,
     * which is what the observer is registered for.
     *
     * @param int $courseid Course the module belongs to.
     * @param int $cmid Course module id.
     * @return void
     */
    private function delete_module(int $courseid, int $cmid): void {
        if (method_exists(\core_courseformat\local\cmactions::class, 'delete')) {
            \core_courseformat\formatactions::cm($courseid)->delete($cmid);
            return;
        }

        course_delete_module($cmid);
    }

    /**
     * Deleting a quiz through core removes its stored option and nothing else.
     *
     * The second quiz is the control: if the observer deleted indiscriminately, or
     * if the test passed because nothing was ever stored, it would go red.
     *
     * @return void
     */
    public function test_deleting_a_quiz_removes_its_stored_option(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $deleted = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $kept = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        option::set((int) $deleted->cmid, false);
        option::set((int) $kept->cmid, false);

        $this->assertSame(2, $DB->count_records(option::TABLE));

        $this->delete_module((int) $course->id, (int) $deleted->cmid);

        $this->assertFalse($DB->record_exists(option::TABLE, ['cmid' => $deleted->cmid]));
        $this->assertTrue($DB->record_exists(option::TABLE, ['cmid' => $kept->cmid]));
    }

    /**
     * db/events.php really registers the observer, so core will call it.
     *
     * @return void
     */
    public function test_the_observer_is_registered_for_the_event(): void {
        global $CFG;

        $observers = [];
        require($CFG->dirroot . '/local/quiz_summary_option/db/events.php');

        $registered = array_column($observers, 'callback', 'eventname');
        $this->assertArrayHasKey('\core\event\course_module_deleted', $registered);
        $this->assertSame(
            '\local_quiz_summary_option\observer::course_module_deleted',
            $registered['\core\event\course_module_deleted']
        );
        $this->assertTrue(is_callable([observer::class, 'course_module_deleted']));
    }
}

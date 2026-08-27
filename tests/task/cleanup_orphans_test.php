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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the orphan cleanup task.
 *
 * @package    local_quiz_summary_option
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(cleanup_orphans::class)]
final class cleanup_orphans_test extends \advanced_testcase {
    /**
     * Deleting a whole course leaves orphans behind, and the task clears them.
     *
     * remove_course_contents() never fires course_module_deleted, so the observer
     * cannot see this path — which is exactly why the task exists. The first block
     * of assertions is what proves the orphans were really there to remove.
     *
     * @return void
     */
    public function test_the_task_clears_rows_left_by_a_course_deletion(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $doomed = $this->getDataGenerator()->create_course();
        $keeper = $this->getDataGenerator()->create_course();
        $goingaway = $this->getDataGenerator()->create_module('quiz', ['course' => $doomed->id]);
        $staying = $this->getDataGenerator()->create_module('quiz', ['course' => $keeper->id]);
        option::set((int) $goingaway->cmid, false);
        option::set((int) $staying->cmid, false);

        delete_course($doomed, false);

        // The orphan really does survive core's own deletion — this is the premise.
        $this->assertTrue($DB->record_exists(option::TABLE, ['cmid' => $goingaway->cmid]));
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $goingaway->cmid]));

        ob_start();
        (new cleanup_orphans())->execute();
        $output = ob_get_clean();

        $this->assertFalse($DB->record_exists(option::TABLE, ['cmid' => $goingaway->cmid]));
        $this->assertTrue($DB->record_exists(option::TABLE, ['cmid' => $staying->cmid]));
        $this->assertStringContainsString('removed 1 orphaned row', $output);
    }
}

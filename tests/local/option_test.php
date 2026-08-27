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

namespace local_quiz_summary_option\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the storage layer.
 *
 * @package    local_quiz_summary_option
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(option::class)]
final class option_test extends \advanced_testcase {
    /**
     * Create a real quiz and return its course module id.
     *
     * @return int
     */
    private function create_quiz_cmid(): int {
        $course = $this->getDataGenerator()->create_course();

        return (int) $this->getDataGenerator()->create_module('quiz', ['course' => $course->id])->cmid;
    }

    /**
     * A quiz with no stored row shows the summary page, and so does an unsaved module.
     *
     * @return void
     */
    public function test_is_shown_defaults_to_true(): void {
        $this->resetAfterTest();
        $cmid = $this->create_quiz_cmid();

        $this->assertTrue(option::is_shown($cmid));
        $this->assertTrue(option::is_shown(0));
        $this->assertTrue(option::is_shown(-1));

        // Control: a stored Hide really does come back as false through the same call.
        option::set($cmid, false);
        $this->assertFalse(option::is_shown($cmid));
    }

    /**
     * Writing inserts once and then updates in place.
     *
     * @return void
     */
    public function test_set_inserts_then_updates(): void {
        global $DB;
        $this->resetAfterTest();
        $cmid = $this->create_quiz_cmid();

        option::set($cmid, false);
        $this->assertSame(1, $DB->count_records(option::TABLE, ['cmid' => $cmid]));
        $this->assertSame(0, (int) $DB->get_field(option::TABLE, 'show_summary', ['cmid' => $cmid]));

        option::set($cmid, true);
        $this->assertSame(1, $DB->count_records(option::TABLE, ['cmid' => $cmid]));
        $this->assertSame(1, (int) $DB->get_field(option::TABLE, 'show_summary', ['cmid' => $cmid]));
    }


    /**
     * Deleting removes only the row asked for.
     *
     * @return void
     */
    public function test_delete_is_scoped_to_one_module(): void {
        $this->resetAfterTest();
        $first = $this->create_quiz_cmid();
        $second = $this->create_quiz_cmid();
        option::set($first, false);
        option::set($second, false);

        option::delete($first);

        $this->assertTrue(option::is_shown($first));
        $this->assertFalse(option::is_shown($second));
    }
}

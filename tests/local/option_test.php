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

use local_quiz_summary_option\event\summary_option_updated;
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
     * A change is logged, and writing the same value again is not.
     *
     * @return void
     */
    public function test_set_logs_only_real_changes(): void {
        $this->resetAfterTest();
        $cmid = $this->create_quiz_cmid();

        $sink = $this->redirectEvents();
        option::set($cmid, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(summary_option_updated::class, $events[0]);
        $this->assertSame(0, (int) $events[0]->other['showsummary']);
        $this->assertSame(\context_module::instance($cmid)->id, (int) $events[0]->contextid);

        $sink = $this->redirectEvents();
        option::set($cmid, false);
        $this->assertCount(0, $sink->get_events(), 'Re-writing the same value must not log a change.');

        // Control: a genuine change through the same sink still logs.
        option::set($cmid, true);
        $this->assertCount(1, $sink->get_events());
        $sink->close();
    }

    /**
     * Restore writes without logging, because the change belongs to the restore.
     *
     * @return void
     */
    public function test_set_can_suppress_the_event(): void {
        $this->resetAfterTest();
        $cmid = $this->create_quiz_cmid();

        $sink = $this->redirectEvents();
        option::set($cmid, false, false);
        $this->assertCount(0, $sink->get_events());
        $sink->close();

        $this->assertFalse(option::is_shown($cmid), 'The value must still have been written.');
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

    /**
     * The sweep removes rows whose course module has gone and keeps the rest.
     *
     * @return void
     */
    public function test_purge_orphans_removes_only_orphans(): void {
        global $DB;
        $this->resetAfterTest();
        $live = $this->create_quiz_cmid();
        option::set($live, false);

        // Two rows pointing at course modules that do not exist.
        $orphans = [];
        foreach ([$this->create_quiz_cmid(), $this->create_quiz_cmid()] as $cmid) {
            option::set($cmid, false);
            $DB->delete_records('course_modules', ['id' => $cmid]);
            $orphans[] = $cmid;
        }

        $this->assertSame(2, option::purge_orphans());

        foreach ($orphans as $cmid) {
            $this->assertFalse($DB->record_exists(option::TABLE, ['cmid' => $cmid]));
        }
        $this->assertTrue($DB->record_exists(option::TABLE, ['cmid' => $live]));
        $this->assertSame(0, option::purge_orphans(), 'A second sweep must find nothing.');
    }

    /**
     * The sweep keeps going past its batch size.
     *
     * @return void
     */
    public function test_purge_orphans_handles_more_rows_than_one_batch(): void {
        global $DB;
        $this->resetAfterTest();

        for ($i = 1; $i <= 5; $i++) {
            $DB->insert_record(option::TABLE, ['cmid' => 9000000 + $i, 'show_summary' => 0]);
        }

        $this->assertSame(5, option::purge_orphans(2));
        $this->assertSame(0, $DB->count_records(option::TABLE));
    }
}

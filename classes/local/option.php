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

use context_module;
use local_quiz_summary_option\event\summary_option_updated;

/**
 * Storage and lookup for the per-quiz summary page option.
 *
 * The plugin owns one row per quiz course module. A missing row means "show the
 * summary page", so nothing has to be written for the default behaviour.
 *
 * @package    local_quiz_summary_option
 * @copyright  2021 Catalyst IT
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class option {
    /** @var string Form value meaning the summary of attempt page is shown. */
    public const SHOW = 'SHOW';

    /** @var string Form value meaning the summary of attempt page is skipped. */
    public const HIDE = 'HIDE';

    /** @var string The table owned by this plugin. */
    public const TABLE = 'local_quiz_summary_option';

    /**
     * Whether the summary of attempt page should be shown for a course module.
     *
     * @param int $cmid Course module id. Zero or negative means "not saved yet".
     * @return bool True when the summary page is shown, which is also the default.
     */
    public static function is_shown(int $cmid): bool {
        global $DB;

        if ($cmid <= 0) {
            return true;
        }

        $row = $DB->get_record(self::TABLE, ['cmid' => $cmid], 'show_summary');

        return $row ? (bool) $row->show_summary : true;
    }

    /**
     * Store the option for a course module, inserting or updating as needed.
     *
     * Writing the same value again is a no-op and fires no event, so callers may
     * call this unconditionally.
     *
     * @param int $cmid Course module id.
     * @param bool $show True to show the summary page, false to skip it.
     * @param bool $triggerevent Whether to log the change. Restore passes false.
     * @return void
     */
    public static function set(int $cmid, bool $show, bool $triggerevent = true): void {
        global $DB;

        if ($cmid <= 0) {
            return;
        }

        $value = $show ? 1 : 0;
        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid], 'id, show_summary');

        /* Check-then-act against the unique index on cmid. Two concurrent saves of the
           same module form would make the insert fail loudly rather than corrupt data,
           and catching that here is not an option: on PostgreSQL a failed write poisons
           the surrounding transaction, so the recovery would be worse than the race. */
        if ($existing) {
            if ((int) $existing->show_summary === $value) {
                return;
            }
            $DB->update_record(self::TABLE, ['id' => $existing->id, 'show_summary' => $value]);
            $id = (int) $existing->id;
        } else {
            $id = (int) $DB->insert_record(self::TABLE, ['cmid' => $cmid, 'show_summary' => $value]);
        }

        if ($triggerevent) {
            self::trigger_updated_event($cmid, $id, $value);
        }
    }

    /**
     * Remove the stored option for a course module.
     *
     * @param int $cmid Course module id.
     * @return void
     */
    public static function delete(int $cmid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * Delete every row whose course module no longer exists.
     *
     * The observer on course_module_deleted covers a single activity being removed,
     * but remove_course_contents() never fires that event, so course deletion,
     * "restore and delete the current contents" and course import all leak rows.
     * This sweep is the backstop that covers every deletion path.
     *
     * @param int $batchsize How many rows to delete per statement.
     * @return int Number of rows removed.
     */
    public static function purge_orphans(int $batchsize = 500): int {
        global $DB;

        /* Deleted in batches through an explicit id list rather than one correlated
           DELETE: MySQL and MariaDB refuse a subquery that names the table being
           deleted from, so the single-statement form is not portable. */
        $sql = "SELECT o.id
                  FROM {" . self::TABLE . "} o
             LEFT JOIN {course_modules} cm ON cm.id = o.cmid
                 WHERE cm.id IS NULL";

        $removed = 0;
        while ($rows = $DB->get_records_sql($sql, null, 0, $batchsize)) {
            $DB->delete_records_list(self::TABLE, 'id', array_keys($rows));
            $removed += count($rows);
        }

        return $removed;
    }

    /**
     * Log a change to the stored option.
     *
     * @param int $cmid Course module id.
     * @param int $id Id of the row that was written.
     * @param int $value The stored show_summary value.
     * @return void
     */
    protected static function trigger_updated_event(int $cmid, int $id, int $value): void {
        $context = context_module::instance($cmid, IGNORE_MISSING);
        if (!$context) {
            // No module context yet (or already gone) — nothing to attach the event to.
            return;
        }

        $event = summary_option_updated::create([
            'context' => $context,
            'objectid' => $id,
            'other' => ['showsummary' => $value],
        ]);
        $event->trigger();
    }
}

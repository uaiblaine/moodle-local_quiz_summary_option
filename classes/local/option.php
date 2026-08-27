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
     * Writing the same value again is a no-op, so callers may call this
     * unconditionally.
     *
     * @param int $cmid Course module id.
     * @param bool $show True to show the summary page, false to skip it.
     * @return void
     */
    public static function set(int $cmid, bool $show): void {
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
            return;
        }

        $DB->insert_record(self::TABLE, ['cmid' => $cmid, 'show_summary' => $value], false);
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
}

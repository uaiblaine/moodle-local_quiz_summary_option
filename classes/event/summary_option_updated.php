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

namespace local_quiz_summary_option\event;

/**
 * Fired when the summary page option stored for a quiz changes.
 *
 * @package    local_quiz_summary_option
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 *     Extra information about the event.
 *
 *     - int showsummary: 1 when the summary page is shown, 0 when it is skipped.
 * }
 */
class summary_option_updated extends \core\event\base {
    /**
     * Set the basic properties of the event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['objecttable'] = 'local_quiz_summary_option';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_summary_option_updated', 'local_quiz_summary_option');
    }

    /**
     * Non-localised description for administrators.
     *
     * @return string
     */
    public function get_description(): string {
        $state = empty($this->other['showsummary']) ? 'hidden' : 'shown';

        return "The user with id '$this->userid' set the quiz summary page to $state for the " .
            "course module with id '$this->contextinstanceid'.";
    }

    /**
     * Validate the custom data supplied to create().
     *
     * @return void
     * @throws \coding_exception When showsummary is missing.
     */
    protected function validate_data(): void {
        parent::validate_data();

        if (!isset($this->other['showsummary'])) {
            throw new \coding_exception('The \'showsummary\' value must be set in other.');
        }
    }

    /**
     * Mapping used when this event is restored into another site.
     *
     * The plugin's own rows are re-created by its restore step from the new course
     * module id, so the logged object id cannot be mapped.
     *
     * @return bool
     */
    public static function get_objectid_mapping(): bool {
        return self::NOT_MAPPED;
    }
}

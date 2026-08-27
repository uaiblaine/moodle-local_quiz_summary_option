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
use local_quiz_summary_option\local\summary_page;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the after_config hook callback.
 *
 * @package    local_quiz_summary_option
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
final class hook_callbacks_test extends \advanced_testcase {
    /** @var array Superglobals as they were before the test touched them. */
    private $saved = [];

    /**
     * Isolate the request superglobals, which resetAfterTest() does not restore.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        global $SCRIPT;
        $this->saved = ['get' => $_GET, 'post' => $_POST, 'script' => $SCRIPT ?? null];
        $_GET = [];
        $_POST = [];
    }

    /**
     * Put the superglobals back.
     *
     * @return void
     */
    protected function tearDown(): void {
        global $SCRIPT;
        $_GET = $this->saved['get'];
        $_POST = $this->saved['post'];
        $SCRIPT = $this->saved['script'];

        parent::tearDown();
    }

    /**
     * Arrange a last-page finish request for a quiz whose summary page is hidden.
     *
     * @return int The course module id.
     */
    private function arrange_hidden_finish_request(): int {
        global $SCRIPT;

        $course = $this->getDataGenerator()->create_course();
        $cmid = (int) $this->getDataGenerator()->create_module('quiz', ['course' => $course->id])->cmid;
        option::set($cmid, false);

        $SCRIPT = summary_page::PROCESS_ATTEMPT_SCRIPT;
        $_POST = ['cmid' => $cmid, 'nextpage' => -1, 'thispage' => 0, 'next' => 'Finish attempt ...'];

        return $cmid;
    }

    /**
     * The registered callback reaches the decision and applies it.
     *
     * @return void
     */
    public function test_the_callback_applies_the_stored_option(): void {
        $this->resetAfterTest();
        $this->arrange_hidden_finish_request();

        hook_callbacks::after_config(new \core\hook\after_config());

        $this->assertSame(1, $_GET['finishattempt']);
    }

    /**
     * db/hooks.php really registers the callback, so core will call it.
     *
     * Reading the file is the only way to catch a rename or a typo in the callback
     * string, which would otherwise disable the whole plugin silently.
     *
     * @return void
     */
    public function test_the_callback_is_registered_for_the_hook(): void {
        global $CFG;

        $callbacks = [];
        require($CFG->dirroot . '/local/quiz_summary_option/db/hooks.php');

        $registered = array_column($callbacks, 'callback', 'hook');
        $this->assertArrayHasKey(\core\hook\after_config::class, $registered);
        $this->assertSame(
            '\local_quiz_summary_option\hook_callbacks::after_config',
            $registered[\core\hook\after_config::class]
        );
        $this->assertTrue(is_callable([hook_callbacks::class, 'after_config']));
    }

    /**
     * Nothing happens while an upgrade is running.
     *
     * The plugin's tables may not exist yet at that point, and core dispatches the
     * hook for plugins that are on disk but not installed.
     *
     * @return void
     */
    public function test_the_callback_stands_down_during_an_upgrade(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->arrange_hidden_finish_request();

        $CFG->upgraderunning = time() + 300;
        hook_callbacks::after_config(new \core\hook\after_config());
        $this->assertArrayNotHasKey('finishattempt', $_GET);

        // Control: with the upgrade flag gone, the identical request is rewritten.
        unset($CFG->upgraderunning);
        hook_callbacks::after_config(new \core\hook\after_config());
        $this->assertSame(1, $_GET['finishattempt']);
    }

    /**
     * Nothing happens while the plugin itself is not installed.
     *
     * @return void
     */
    public function test_the_callback_stands_down_before_the_plugin_is_installed(): void {
        $this->resetAfterTest();
        $this->arrange_hidden_finish_request();

        unset_config('version', 'local_quiz_summary_option');
        hook_callbacks::after_config(new \core\hook\after_config());
        $this->assertArrayNotHasKey('finishattempt', $_GET);

        // Control: restoring the version marker restores the behaviour.
        set_config('version', 2026082700, 'local_quiz_summary_option');
        hook_callbacks::after_config(new \core\hook\after_config());
        $this->assertSame(1, $_GET['finishattempt']);
    }
}

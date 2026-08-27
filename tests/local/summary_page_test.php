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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the summary page decision made during after_config.
 *
 * @package    local_quiz_summary_option
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(summary_page::class)]
final class summary_page_test extends \advanced_testcase {
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
     * Put the superglobals back so test order cannot change another test's result.
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
     * Create a real quiz with the summary page hidden and return its cmid.
     *
     * @return int
     */
    private function create_hidden_quiz(): int {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        option::set((int) $quiz->cmid, false);

        return (int) $quiz->cmid;
    }

    /**
     * The POST a student's browser sends when finishing an attempt from the last page.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    private function base_request(int $cmid): array {
        return [
            'cmid' => $cmid,
            'nextpage' => -1,
            'thispage' => 0,
            'next' => 'Finish attempt ...',
        ];
    }

    /**
     * Install a request into the superglobals optional_param() reads.
     *
     * @param array $params Request parameters. A null value removes the parameter.
     * @param string $script Value of the $SCRIPT global.
     * @return void
     */
    private function arrange(array $params, string $script = summary_page::PROCESS_ATTEMPT_SCRIPT): void {
        global $SCRIPT;

        $SCRIPT = $script;
        $_GET = [];
        $_POST = array_filter($params, static function ($value) {
            return $value !== null;
        });
    }

    /**
     * Requests that must, and must not, be rewritten to finish the attempt.
     *
     * Each case is the base last-page submit with one field changed, so the two
     * expected-true rows are the controls for every expected-false row: if the
     * arrangement ever stopped reaching the decision at all, those two go red.
     *
     * @return array
     */
    public static function request_provider(): array {
        return [
            'last page finish button' => [[], true],
            'nav block link from the last page' => [['next' => null, 'thispage' => -1], true],
            'next page mid attempt' => [['nextpage' => 2], false],
            'nav block link from an earlier page' => [['next' => null, 'nextpage' => 2, 'thispage' => -1], false],
            'timer submit from the last page' => [['next' => null, 'thispage' => 0], false],
            'previous page' => [['next' => null, 'previous' => 1, 'thispage' => 3, 'nextpage' => 4], false],
            'summary page own finish button' => [['next' => null, 'nextpage' => null, 'thispage' => null], false],
            'no cmid supplied' => [['cmid' => null], false],
        ];
    }

    /**
     * Only a last-page finish request on a hidden quiz is rewritten.
     *
     * @param array $overrides Fields to change in the base request; null removes one.
     * @param bool $expected Whether the request should be rewritten.
     * @return void
     */
    #[DataProvider('request_provider')]
    public function test_only_a_last_page_finish_is_rewritten(array $overrides, bool $expected): void {
        $this->resetAfterTest();
        $cmid = $this->create_hidden_quiz();

        $this->arrange(array_merge($this->base_request($cmid), $overrides));

        $this->assertSame($expected, summary_page::maybe_skip());
        $this->assertSame($expected, array_key_exists('finishattempt', $_GET));
    }

    /**
     * The stored value is what decides, on an otherwise identical request.
     *
     * @return void
     */
    public function test_the_stored_option_decides(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cmid = (int) $quiz->cmid;

        $this->arrange($this->base_request($cmid));
        $this->assertFalse(summary_page::maybe_skip(), 'No stored row must mean the summary page is shown.');

        option::set($cmid, true);
        $this->assertFalse(summary_page::maybe_skip(), 'An explicit Show must leave the request alone.');
        $this->assertArrayNotHasKey('finishattempt', $_GET);

        option::set($cmid, false);
        $this->assertTrue(summary_page::maybe_skip(), 'Hide must rewrite this exact request.');
        $this->assertSame(1, $_GET['finishattempt']);
    }

    /**
     * A cmid that has no row of its own is not affected by another quiz's setting.
     *
     * @return void
     */
    public function test_the_option_is_scoped_to_its_own_quiz(): void {
        $this->resetAfterTest();
        $hidden = $this->create_hidden_quiz();
        $course = $this->getDataGenerator()->create_course();
        $other = (int) $this->getDataGenerator()->create_module('quiz', ['course' => $course->id])->cmid;

        $this->arrange($this->base_request($other));
        $this->assertFalse(summary_page::maybe_skip());

        $this->arrange($this->base_request($hidden));
        $this->assertTrue(summary_page::maybe_skip());
    }

    /**
     * The plugin acts on the quiz attempt-processing script and on nothing else.
     *
     * The web service entry point is the case that matters: mod_quiz_process_attempt
     * finishes attempts for the Moodle app without ever loading processattempt.php,
     * so the option is deliberately browser-only and this pins that boundary.
     *
     * @return void
     */
    public function test_other_scripts_are_left_alone(): void {
        $this->resetAfterTest();
        $cmid = $this->create_hidden_quiz();
        $request = $this->base_request($cmid);

        foreach (['/webservice/rest/server.php', '/mod/quiz/summary.php', '/course/view.php'] as $script) {
            $this->arrange($request, $script);
            $this->assertFalse(summary_page::maybe_skip(), "$script must not be rewritten.");
            $this->assertArrayNotHasKey('finishattempt', $_GET);
        }

        // Control: the same request on the attempt-processing script is rewritten.
        $this->arrange($request);
        $this->assertTrue(summary_page::maybe_skip());
    }
}

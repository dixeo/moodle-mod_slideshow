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

/**
 * Tests that AJAX mutations skip events when persistence fails.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/ajax_failing_db_proxy.php');

/**
 * Tests that AJAX mutations skip events when persistence fails.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::slideshow_process_ajax_action
 */
final class ajax_mutation_events_test extends \advanced_testcase {
    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Build slideshow, context, and two slides for reorder/delete tests.
     *
     * @return array{0: \stdClass, 1: \context_module, 2: \stdClass, 3: \stdClass}
     */
    private function create_fixture_with_slides(): array {
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_slideshow');

        $slidea = (object) [
            'slideshow' => $slideshow->id,
            'name' => 'Slide A',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'sortorder' => 0,
            'timemodified' => time(),
        ];
        $slidea->id = $generator->create_slide($slidea);

        $slideb = (object) [
            'slideshow' => $slideshow->id,
            'name' => 'Slide B',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'sortorder' => 1,
            'timemodified' => time(),
        ];
        $slideb->id = $generator->create_slide($slideb);

        return [$slideshow, $context, $slidea, $slideb];
    }

    /**
     * Install a failing DB proxy for the duration of a callback.
     *
     * @param ajax_failing_db_proxy $proxy Proxy database.
     * @param callable $callback Callback receiving no arguments.
     * @return mixed Callback return value.
     */
    private function with_db_proxy(ajax_failing_db_proxy $proxy, callable $callback) {
        global $DB;
        $real = $DB;
        $DB = $proxy;
        try {
            return $callback();
        } finally {
            $DB = $real;
        }
    }

    /**
     * Failed reorder updates must not emit slides_reordered.
     */
    public function test_failed_reorder_skips_slides_reordered_event(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slideshow, $context, $slidea] = $this->create_fixture_with_slides();
        $proxy = new ajax_failing_db_proxy($DB, true, false, false);

        $sink = $this->redirectEvents();
        $response = $this->with_db_proxy($proxy, function () use ($slidea, $slideshow, $context) {
            return slideshow_process_ajax_action('reorder', $slidea, $slideshow, $context, 0, 1);
        });

        $this->assertFalse($response['result']);
        $events = array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \mod_slideshow\event\slides_reordered
        );
        $this->assertCount(0, $events);
    }

    /**
     * Failed delete must not emit slide_deleted.
     */
    public function test_failed_delete_skips_slide_deleted_event(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slideshow, $context, $slidea] = $this->create_fixture_with_slides();
        $proxy = new ajax_failing_db_proxy($DB, false, true, false);

        $sink = $this->redirectEvents();
        $response = $this->with_db_proxy($proxy, function () use ($slidea, $slideshow, $context) {
            return slideshow_process_ajax_action('delete', $slidea, $slideshow, $context);
        });

        $this->assertFalse($response['result']);
        $events = array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \mod_slideshow\event\slide_deleted
        );
        $this->assertCount(0, $events);
        $this->assertTrue($DB->record_exists('slideshow_slide', ['id' => $slidea->id]));
    }

    /**
     * Failed visibility update must not emit slide_visibility_updated.
     */
    public function test_failed_visibility_update_skips_event(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slideshow, $context, $slidea] = $this->create_fixture_with_slides();
        $proxy = new ajax_failing_db_proxy($DB, true, false, false);

        $sink = $this->redirectEvents();
        $response = $this->with_db_proxy($proxy, function () use ($slidea, $slideshow, $context) {
            return slideshow_process_ajax_action('hide', $slidea, $slideshow, $context);
        });

        $this->assertFalse($response['result']);
        $events = array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \mod_slideshow\event\slide_visibility_updated
        );
        $this->assertCount(0, $events);
        $this->assertEquals(0, (int) $DB->get_field('slideshow_slide', 'hidden', ['id' => $slidea->id]));
    }
}

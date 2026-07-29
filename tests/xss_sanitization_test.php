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

namespace mod_slideshow;

/**
 * Tests that slide HTML is cleaned for display (SLS-SEC-001 remediation).
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class xss_sanitization_test extends \advanced_testcase {

    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Editor options must not bypass Moodle HTML cleaning.
     */
    public function test_editor_options_do_not_enable_noclean(): void {
        global $CFG;

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');
        $options = slideshow_get_editor_options($context);

        $this->assertArrayNotHasKey('noclean', $options);
    }

    /**
     * Slide rendering must not preserve active HTML handlers from untrusted markup.
     */
    public function test_slide_content_format_strips_active_content(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);

        $payload = '<img src="x" onerror="alert(1)">';
        $formatoptions = (object) [
            'overflowdiv' => true,
            'context' => $context,
        ];
        $formatted = format_text($payload, FORMAT_HTML, $formatoptions);
        $formatted = slideshow_balance_slide_html($formatted, 1);

        $this->assertStringNotContainsString('onerror', $formatted);
        $this->assertStringNotContainsString('alert(1)', $formatted);
    }
}

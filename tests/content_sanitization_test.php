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
 * Hostile HTML and Unicode matrix for slide content sanitization and display.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class content_sanitization_test extends \advanced_testcase {
    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Dangerous HTML payloads that must not survive storage sanitization.
     *
     * @return array<string, array{string, string}>
     */
    public static function dangerous_html_payloads_provider(): array {
        return [
            'script_tag' => ['<script>alert(1)</script>', 'alert(1)'],
            'img_onerror' => ['<img src="x" onerror="alert(1)">', 'onerror'],
            'javascript_href' => ['<a href="javascript:alert(1)">click</a>', 'javascript:'],
            'svg_onload' => ['<svg onload="alert(1)"></svg>', 'onload'],
            'malformed_open_tag' => ['<<script>alert(1)</script>', 'alert(1)'],
            'unclosed_div' => ['<div onclick="alert(1)"><p>text', 'onclick'],
        ];
    }

    /**
     * Canonical sanitizer must strip active content from hostile payloads.
     *
     * @param string $payload Raw HTML.
     * @param string $forbidden Substring that must not appear in cleaned output.
     * @dataProvider dangerous_html_payloads_provider
     * @covers ::slideshow_sanitize_slide_content
     */
    public function test_sanitize_slide_content_removes_dangerous_markup(string $payload, string $forbidden): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $clean = slideshow_sanitize_slide_content($payload, FORMAT_HTML);

        $this->assertStringNotContainsStringIgnoringCase($forbidden, $clean);
    }

    /**
     * Display formatting (view.php path) must not preserve active handlers.
     *
     * @param string $payload Raw HTML.
     * @param string $forbidden Substring that must not appear in formatted output.
     * @dataProvider dangerous_html_payloads_provider
     * @covers ::slideshow_balance_slide_html
     */
    public function test_display_format_removes_dangerous_markup(string $payload, string $forbidden): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);

        $formatted = $this->format_slide_for_display($payload, $context, 1);

        $this->assertStringNotContainsStringIgnoringCase($forbidden, $formatted);
    }

    /**
     * Unicode direction override and null bytes must not bypass cleaning.
     *
     * @covers ::slideshow_sanitize_slide_content
     */
    public function test_sanitize_slide_content_handles_unicode_tricks(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $rtl = "\u{202E}<img src=x onerror=alert(1)>";
        $cleanrtl = slideshow_sanitize_slide_content($rtl, FORMAT_HTML);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $cleanrtl);

        $nullbyte = "<scr\0ipt>alert(1)</script>";
        $cleannull = slideshow_sanitize_slide_content($nullbyte, FORMAT_HTML);
        $this->assertStringNotContainsStringIgnoringCase('alert(1)', $cleannull);
    }

    /**
     * Malformed nested markup must not leave executable handlers after sanitization.
     *
     * @covers ::slideshow_sanitize_slide_content
     */
    public function test_sanitize_slide_content_handles_nested_malformed_markup(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $payload = '<div><svg><g onload="alert(1)"></g></svg></div>';
        $clean = slideshow_sanitize_slide_content($payload, FORMAT_HTML);

        $this->assertStringNotContainsStringIgnoringCase('onload', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
    }

    /**
     * Simulated edit.php save path must persist sanitized HTML in the database.
     *
     * @covers ::slideshow_sanitize_slide_content
     */
    public function test_edit_save_path_stores_sanitized_content(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_slideshow');
        $slideid = $generator->create_slide([
            'slideshow' => $slideshow->id,
            'content' => 'Initial',
        ]);

        $dirty = '<script>alert(1)</script><p>Safe text</p>';
        $content = slideshow_sanitize_slide_content($dirty, FORMAT_HTML);
        $DB->set_field('slideshow_slide', 'content', $content, ['id' => $slideid]);

        $stored = $DB->get_field('slideshow_slide', 'content', ['id' => $slideid]);
        $this->assertStringNotContainsString('<script>', $stored);
        $this->assertStringNotContainsString('alert(1)', $stored);
        $this->assertStringContainsString('Safe text', $stored);
    }

    /**
     * prepare_slide_save_record must not copy tampered content fields on update.
     *
     * @covers ::slideshow_prepare_slide_save_record
     */
    public function test_prepare_slide_save_record_does_not_copy_content_on_update(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $fromform = (object) [
            'id' => 10,
            'name' => 'Slide title',
            'hidden' => 0,
            'content' => '<script>alert(1)</script>',
            'contentformat' => FORMAT_HTML,
            'slideshow' => 9999,
        ];

        $record = slideshow_prepare_slide_save_record($fromform, 5, false);

        $this->assertObjectNotHasProperty('content', $record);
        $this->assertObjectNotHasProperty('contentformat', $record);
        $this->assertEquals(5, $record->slideshow);
    }

    /**
     * Balance helper must return safe HTML when parsing hostile fragments.
     *
     * @covers ::slideshow_balance_slide_html
     */
    public function test_balance_slide_html_does_not_restore_handlers(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);

        $payload = '<div></div><img src="x" onerror="alert(1)">';
        $formatted = $this->format_slide_for_display($payload, $context, 2);
        $balanced = slideshow_balance_slide_html($formatted, 2);

        $this->assertStringNotContainsStringIgnoringCase('onerror', $balanced);
        $this->assertStringNotContainsString('alert(1)', $balanced);
    }

    /**
     * Format slide HTML the same way as view.php before balance_slide_html.
     *
     * @param string $html Raw slide HTML.
     * @param \context $context Module context.
     * @param int $slideid Slide row id.
     * @return string Formatted HTML.
     */
    private function format_slide_for_display(string $html, \context $context, int $slideid): string {
        $formatoptions = new \stdClass();
        $formatoptions->overflowdiv = true;
        $formatoptions->context = $context;
        $content = format_text($html, FORMAT_HTML, $formatoptions);
        return slideshow_balance_slide_html($content, $slideid);
    }
}

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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

/**
 * Tests for the new slide row builder shared by every untrusted insert path.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::slideshow_build_new_slide_record
 */
final class slide_record_builder_test extends \advanced_testcase {
    /**
     * Name and content are cleaned, and only the slide columns are returned.
     */
    public function test_cleans_name_and_content(): void {
        $record = slideshow_build_new_slide_record(7, [
            'name' => '<b>Intro</b><script>alert(1)</script>',
            'content' => '<p onclick="alert(1)">Body</p><script>alert(2)</script>',
            'contentformat' => FORMAT_HTML,
            'layout' => MOD_SLIDESHOW_LAYOUT_CONTENTLEFT,
            'hidetitle' => 1,
            'hidden' => 5,
            'id' => 99,
            'extra' => 'ignored',
        ], 3);

        $this->assertSame(7, $record->slideshow);
        $this->assertStringNotContainsString('<', $record->name);
        $this->assertStringContainsString('Intro', $record->name);
        $this->assertStringNotContainsString('<script', $record->content);
        $this->assertStringNotContainsString('onclick', $record->content);
        $this->assertStringContainsString('Body', $record->content);
        $this->assertSame(MOD_SLIDESHOW_LAYOUT_CONTENTLEFT, $record->layout);
        $this->assertSame(1, $record->hidetitle);
        $this->assertSame(1, $record->hidden);
        $this->assertSame(3, $record->sortorder);
        $this->assertGreaterThan(0, $record->timemodified);
        $this->assertSame(
            ['slideshow', 'name', 'hidetitle', 'content', 'contentformat', 'layout', 'hidden', 'sortorder', 'timemodified'],
            array_keys((array) $record)
        );
    }

    /**
     * An unknown format falls back to HTML and an unknown layout to background.
     */
    public function test_whitelists_format_and_layout(): void {
        $record = slideshow_build_new_slide_record(1, [
            'name' => 'Slide',
            'contentformat' => 999,
            'layout' => 'unknown',
        ], 0);

        $this->assertSame((int) FORMAT_HTML, $record->contentformat);
        $this->assertSame(MOD_SLIDESHOW_LAYOUT_BACKGROUND, $record->layout);
        $this->assertSame(0, $record->hidden);
        $this->assertSame('', $record->content);
    }

    /**
     * Title only always shows its title, so the hide flag is not kept.
     */
    public function test_titleonly_forces_title_visible(): void {
        $record = slideshow_build_new_slide_record(1, [
            'name' => 'Title',
            'layout' => MOD_SLIDESHOW_LAYOUT_TITLEONLY,
            'hidetitle' => 1,
        ], 0);

        $this->assertSame(0, $record->hidetitle);
    }
}

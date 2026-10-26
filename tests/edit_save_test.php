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
 * Tests for slide save record preparation (mass-assignment hardening).
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class edit_save_test extends \advanced_testcase {
    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tampered slideshow id on form data must not override the course module instance id.
     *
     * @covers ::slideshow_prepare_slide_save_record
     */
    public function test_prepare_slide_save_record_ignores_tampered_slideshow(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $fromform = (object) [
            'id' => 42,
            'name' => 'Slide title',
            'hidden' => 0,
            'slideshow' => 9999,
        ];

        $record = slideshow_prepare_slide_save_record($fromform, 7, false);

        $this->assertEquals(42, $record->id);
        $this->assertEquals(7, $record->slideshow);
        $this->assertEquals('Slide title', $record->name);
        $this->assertObjectNotHasProperty('sortorder', $record);
    }

    /**
     * New slide records include only whitelisted columns for insert.
     *
     * @covers ::slideshow_prepare_slide_save_record
     */
    public function test_prepare_slide_save_record_new_slide_fields(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $fromform = (object) [
            'name' => 'New slide',
            'hidden' => 1,
            'slideshow' => 9999,
        ];

        $record = slideshow_prepare_slide_save_record($fromform, 3, true, 2);

        $this->assertEquals(3, $record->slideshow);
        $this->assertEquals(2, $record->sortorder);
        $this->assertEquals('', $record->content);
        $this->assertEquals(FORMAT_HTML, $record->contentformat);
        $this->assertEquals(MOD_SLIDESHOW_LAYOUT_BACKGROUND, $record->layout);
        $this->assertEquals(0, $record->hidetitle);
        $this->assertObjectNotHasProperty('id', $record);
    }

    /**
     * The hide-title flag is stored, and title only clears it.
     *
     * @covers ::slideshow_prepare_slide_save_record
     */
    public function test_prepare_slide_save_record_stores_hide_title_except_title_only(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $hidden = slideshow_prepare_slide_save_record((object) [
            'name' => 'Slide',
            'hidden' => 0,
            'layout' => MOD_SLIDESHOW_LAYOUT_CONTENTTOP,
            'hidetitle' => 1,
        ], 3, true, 1);
        $this->assertEquals(1, $hidden->hidetitle);

        $titleonly = slideshow_prepare_slide_save_record((object) [
            'id' => 4,
            'name' => 'Slide',
            'hidden' => 0,
            'layout' => MOD_SLIDESHOW_LAYOUT_TITLEONLY,
            'hidetitle' => 1,
        ], 3, false);
        $this->assertEquals(0, $titleonly->hidetitle);
    }

    /**
     * Unknown layout values are stored as background. Known values are kept.
     *
     * @covers ::slideshow_prepare_slide_save_record
     * @covers ::slideshow_normalize_layout
     */
    public function test_prepare_slide_save_record_normalizes_layout(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $fromform = (object) [
            'name' => 'New slide',
            'hidden' => 0,
            'layout' => 'not-a-layout',
        ];

        $record = slideshow_prepare_slide_save_record($fromform, 3, true, 1);
        $this->assertEquals(MOD_SLIDESHOW_LAYOUT_BACKGROUND, $record->layout);

        $fromform->layout = MOD_SLIDESHOW_LAYOUT_CONTENTLEFT;
        $record = slideshow_prepare_slide_save_record($fromform, 3, true, 1);
        $this->assertEquals(MOD_SLIDESHOW_LAYOUT_CONTENTLEFT, $record->layout);

        $update = (object) [
            'id' => 9,
            'name' => 'Slide',
            'hidden' => 0,
            'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND,
        ];
        $record = slideshow_prepare_slide_save_record($update, 4, false);
        $this->assertEquals(9, $record->id);
        $this->assertEquals(MOD_SLIDESHOW_LAYOUT_BACKGROUND, $record->layout);
        $this->assertObjectNotHasProperty('content', $record);
    }

    /**
     * Content is required for every layout except title only.
     *
     * @covers ::slideshow_validate_slide_submission
     * @covers ::slideshow_slide_content_is_present
     */
    public function test_validate_slide_submission_requires_content_unless_title_only(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $empty = ['content_editor' => ['text' => '<p></p>', 'format' => FORMAT_HTML]];

        $background = slideshow_validate_slide_submission($empty + [
            'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND,
        ]);
        $this->assertArrayHasKey('content_editor', $background);
        $this->assertArrayNotHasKey('layoutgroup', $background);

        $titleonly = slideshow_validate_slide_submission($empty + [
            'layout' => MOD_SLIDESHOW_LAYOUT_TITLEONLY,
        ]);
        $this->assertSame([], $titleonly);

        $withtext = slideshow_validate_slide_submission([
            'layout' => MOD_SLIDESHOW_LAYOUT_CONTENTRIGHT,
            'content_editor' => ['text' => '<p>Body</p>', 'format' => FORMAT_HTML],
        ]);
        $this->assertSame([], $withtext);

        $mediaonly = slideshow_validate_slide_submission([
            'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND,
            'content_editor' => ['text' => '<p><img src="x" alt=""></p>', 'format' => FORMAT_HTML],
        ]);
        $this->assertSame([], $mediaonly);

        $unknown = slideshow_validate_slide_submission([
            'layout' => 'stacked',
            'content_editor' => ['text' => '<p>Body</p>', 'format' => FORMAT_HTML],
        ]);
        $this->assertArrayHasKey('layoutgroup', $unknown);
        $this->assertArrayNotHasKey('content_editor', $unknown);
    }
}

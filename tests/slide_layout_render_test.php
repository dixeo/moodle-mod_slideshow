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
 * Tests for slide layout markup in the player.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class slide_layout_render_test extends \advanced_testcase {
    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Title only centers the title and drops the body and the image.
     *
     * @covers ::slideshow_render_slide_html
     */
    public function test_titleonly_omits_body_and_image(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $html = slideshow_render_slide_html(
            (object) ['id' => 5, 'name' => 'Only title', 'layout' => MOD_SLIDESHOW_LAYOUT_TITLEONLY],
            '<p>Hidden body</p>',
            'https://example.com/slide.png',
            true
        );

        $this->assertStringContainsString('layout-titleonly', $html);
        $this->assertStringContainsString('Only title', $html);
        $this->assertStringContainsString(' hidden', $html);
        $this->assertStringNotContainsString('Hidden body', $html);
        $this->assertStringNotContainsString('slide-body', $html);
        $this->assertStringNotContainsString('slide.png', $html);
    }

    /**
     * A background slide with an image puts that image behind a scrim hook.
     *
     * @covers ::slideshow_render_slide_html
     */
    public function test_background_with_image_uses_backdrop(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $html = slideshow_render_slide_html(
            (object) ['id' => 6, 'name' => 'Cover', 'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND],
            '<p>On the image</p>',
            'https://example.com/cover.png',
            false
        );

        $this->assertStringContainsString('layout-background has-image', $html);
        $this->assertStringContainsString('slide-backdrop', $html);
        $this->assertStringContainsString('cover.png', $html);
        $this->assertStringContainsString('On the image', $html);
        $this->assertStringContainsString('Cover', $html);
    }

    /**
     * A background slide with no image stays on the normal slide colors.
     *
     * @covers ::slideshow_render_slide_html
     */
    public function test_background_without_image_has_no_scrim_hook(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $html = slideshow_render_slide_html(
            (object) ['id' => 7, 'name' => 'Plain', 'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND],
            '<p>Readable</p>',
            null,
            false
        );

        $this->assertStringContainsString('layout-background', $html);
        $this->assertStringNotContainsString('has-image', $html);
        $this->assertStringNotContainsString('slide-backdrop', $html);
        $this->assertStringContainsString('Readable', $html);
    }

    /**
     * Content right places the image box before the text. Content left uses the reverse class.
     *
     * @covers ::slideshow_render_slide_html
     */
    public function test_split_layouts_place_the_image_box(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $right = slideshow_render_slide_html(
            (object) ['id' => 8, 'name' => 'Right', 'layout' => MOD_SLIDESHOW_LAYOUT_CONTENTRIGHT],
            '<p>Text</p>',
            'https://example.com/left.png',
            false
        );
        $this->assertStringContainsString('layout-contentright', $right);
        $this->assertLessThan(strpos($right, 'slide-body'), strpos($right, 'slide-media'));
        $this->assertStringContainsString('left.png', $right);

        $left = slideshow_render_slide_html(
            (object) ['id' => 9, 'name' => 'Left', 'layout' => MOD_SLIDESHOW_LAYOUT_CONTENTLEFT],
            '<p>Text</p>',
            null,
            false
        );
        $this->assertStringContainsString('layout-contentleft', $left);
        $this->assertStringContainsString('slide-media', $left);
        $this->assertStringNotContainsString('background-image', $left);
        $this->assertStringContainsString('<p>Text</p>', $left);
    }

    /**
     * A missing or unknown layout is a background slide with no image, and the title is escaped.
     *
     * @covers ::slideshow_render_slide_html
     * @covers ::slideshow_normalize_layout
     */
    public function test_unknown_layout_is_background_without_image_and_title_is_escaped(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $html = slideshow_render_slide_html(
            (object) ['id' => 10, 'name' => '<script>alert(1)</script>', 'layout' => 'fullwidth'],
            '<p>Body</p>',
            null,
            false
        );

        $this->assertStringContainsString('layout-background', $html);
        $this->assertStringContainsString('data-layout="background"', $html);
        $this->assertStringNotContainsString('has-image', $html);
        $this->assertStringNotContainsString('slide-backdrop', $html);
        $this->assertStringContainsString('<p>Body</p>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('alert(1)', $html);
    }

    /**
     * The slides-page thumbnail uses player markup and the cached image preview.
     *
     * @covers ::slideshow_get_slide_list_preview_html
     */
    public function test_list_preview_uses_player_markup_and_thumb_url(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_slideshow');
        $titleonly = $DB->get_record('slideshow_slide', ['id' => $generator->create_slide([
            'slideshow' => $slideshow->id,
            'name' => 'Title slide',
            'layout' => MOD_SLIDESHOW_LAYOUT_TITLEONLY,
            'content' => '<p>Kept body</p>',
        ])], '*', MUST_EXIST);
        $titlehtml = slideshow_get_slide_list_preview_html($titleonly, $context);
        $this->assertStringContainsString('slide-list-thumb', $titlehtml);
        $this->assertStringContainsString('layout-titleonly', $titlehtml);
        $this->assertStringNotContainsString('slide-body', $titlehtml);
        $this->assertStringNotContainsString('preview=thumb', $titlehtml);

        $slide = $DB->get_record('slideshow_slide', ['id' => $generator->create_slide([
            'slideshow' => $slideshow->id,
            'name' => 'Photo slide',
            'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND,
            'content' => '<p>Setbacks</p>',
        ])], '*', MUST_EXIST);
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_slideshow',
            'filearea' => 'image',
            'itemid' => $slide->id,
            'filepath' => '/',
            'filename' => 'cover.png',
        ], 'png');

        $html = slideshow_get_slide_list_preview_html($slide, $context);
        $this->assertStringContainsString('layout-background', $html);
        $this->assertStringContainsString('has-image', $html);
        $this->assertStringContainsString('slide-backdrop', $html);
        $this->assertStringContainsString('preview=thumb', $html);
        $this->assertStringContainsString('Setbacks', $html);
    }
}

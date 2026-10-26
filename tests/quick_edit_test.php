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
 * Tests for the player quick-edit save.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quick_edit_test extends \advanced_testcase {
    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Title and layout are stored, and the body is left as it was.
     *
     * @covers ::slideshow_save_quick_edit
     */
    public function test_save_title_and_layout_keeps_content(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slide, $context] = $this->create_slide('<p>Keep this body</p>');
        $this->setAdminUser();

        $result = slideshow_save_quick_edit($slide, $context, (object) [
            'name' => 'Updated title',
            'layout' => MOD_SLIDESHOW_LAYOUT_CONTENTLEFT,
            'image' => 0,
            'content_editor' => $this->editor_payload('<p>Keep this body</p>'),
        ]);

        $updated = $this->reload_slide((int) $slide->id);
        $this->assertSame([], $result['errors']);
        $this->assertSame('Updated title', $updated->name);
        $this->assertSame(MOD_SLIDESHOW_LAYOUT_CONTENTLEFT, $updated->layout);
        $this->assertSame('<p>Keep this body</p>', $updated->content);
        $this->assertStringContainsString('Updated title', $result['html']);
        $this->assertStringContainsString('data-layout="contentleft"', $result['html']);
    }

    /**
     * A new draft file replaces the stored slide image.
     *
     * @covers ::slideshow_save_quick_edit
     */
    public function test_save_replaces_image(): void {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slide, $context] = $this->create_slide('<p>Body</p>');
        $this->setAdminUser();
        $this->store_image($context->id, (int) $slide->id, 'old.png', 'old');

        $draftid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => 'new.png',
        ], 'new');

        $result = slideshow_save_quick_edit($slide, $context, (object) [
            'name' => 'With image',
            'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND,
            'image' => $draftid,
            'content_editor' => $this->editor_payload('<p>Body</p>'),
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(['new.png'], $this->image_filenames($context->id, (int) $slide->id));
    }

    /**
     * An empty draft removes the stored slide image.
     *
     * @covers ::slideshow_save_quick_edit
     */
    public function test_save_removes_image(): void {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slide, $context] = $this->create_slide('<p>Body</p>');
        $this->setAdminUser();
        $this->store_image($context->id, (int) $slide->id, 'old.png', 'old');

        $draftid = 0;
        file_prepare_draft_area(
            $draftid,
            $context->id,
            'mod_slideshow',
            'image',
            (int) $slide->id,
            slideshow_get_image_file_options()
        );
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'filename', false);
        foreach ($draftfiles as $file) {
            $file->delete();
        }

        $result = slideshow_save_quick_edit($slide, $context, (object) [
            'name' => 'No image',
            'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND,
            'image' => $draftid,
            'content_editor' => $this->editor_payload('<p>Body</p>'),
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame([], $this->image_filenames($context->id, (int) $slide->id));
    }

    /**
     * Title only does not replace the stored image or body.
     *
     * @covers ::slideshow_save_quick_edit
     */
    public function test_title_only_leaves_image_and_content(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slide, $context] = $this->create_slide('<p>Stored body</p>');
        $this->setAdminUser();
        $this->store_image($context->id, (int) $slide->id, 'keep.png', 'keep');

        $result = slideshow_save_quick_edit($slide, $context, (object) [
            'name' => 'Title only',
            'layout' => MOD_SLIDESHOW_LAYOUT_TITLEONLY,
            'image' => 0,
            'content_editor' => $this->editor_payload('<p>Replacement that must not be stored</p>'),
        ]);

        $updated = $this->reload_slide((int) $slide->id);
        $this->assertSame([], $result['errors']);
        $this->assertSame(MOD_SLIDESHOW_LAYOUT_TITLEONLY, $updated->layout);
        $this->assertSame('<p>Stored body</p>', $updated->content);
        $this->assertSame(['keep.png'], $this->image_filenames($context->id, (int) $slide->id));
        $this->assertStringNotContainsString('slide-body', $result['html']);
    }

    /**
     * An unknown layout is rejected and nothing is written.
     *
     * @covers ::slideshow_save_quick_edit
     */
    public function test_rejected_layout_is_not_saved(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slide, $context] = $this->create_slide('<p>Body</p>');
        $this->setAdminUser();

        $result = slideshow_save_quick_edit($slide, $context, (object) [
            'name' => 'New name',
            'layout' => 'nope',
            'image' => 0,
        ]);

        $updated = $this->reload_slide((int) $slide->id);
        $this->assertArrayHasKey('layoutgroup', $result['errors']);
        $this->assertSame('Test slide', $updated->name);
        $this->assertSame(MOD_SLIDESHOW_LAYOUT_BACKGROUND, $updated->layout);
    }

    /**
     * The Moodle editor body is stored for layouts that show content.
     *
     * @covers ::slideshow_save_quick_edit
     */
    public function test_save_stores_editor_content(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slide, $context] = $this->create_slide('<p>Old body</p>');
        $this->setAdminUser();

        $result = slideshow_save_quick_edit($slide, $context, (object) [
            'name' => 'Test slide',
            'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND,
            'image' => 0,
            'content_editor' => $this->editor_payload('<p>New body</p>'),
        ]);

        $updated = $this->reload_slide((int) $slide->id);
        $this->assertSame([], $result['errors']);
        $this->assertStringContainsString('New body', $updated->content);
        $this->assertStringContainsString('New body', $result['html']);
    }

    /**
     * A layout that shows the body rejects an empty editor.
     *
     * @covers ::slideshow_save_quick_edit
     */
    public function test_empty_content_is_rejected(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slide, $context] = $this->create_slide('<p>Keep</p>');
        $this->setAdminUser();

        $result = slideshow_save_quick_edit($slide, $context, (object) [
            'name' => 'Changed',
            'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND,
            'image' => 0,
            'content_editor' => $this->editor_payload('<p></p>'),
        ]);

        $updated = $this->reload_slide((int) $slide->id);
        $this->assertArrayHasKey('content_editor', $result['errors']);
        $this->assertSame('Test slide', $updated->name);
        $this->assertSame('<p>Keep</p>', $updated->content);
    }

    /**
     * A user without mod/slideshow:viewslides cannot save.
     *
     * @covers ::slideshow_save_quick_edit
     */
    public function test_user_without_viewslides_cannot_save(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        [$slide, $context, $course] = $this->create_slide('<p>Body</p>');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        slideshow_save_quick_edit($slide, $context, (object) [
            'name' => 'Nope',
            'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND,
            'image' => 0,
        ]);
    }

    /**
     * Create a course, slideshow, and one slide.
     *
     * @param string $content Stored slide body.
     * @return array{0: \stdClass, 1: \context_module, 2: \stdClass} Slide row, module context, and course.
     */
    private function create_slide(string $content): array {
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $slideid = $this->getDataGenerator()->get_plugin_generator('mod_slideshow')->create_slide([
            'slideshow' => $slideshow->id,
            'name' => 'Test slide',
            'content' => $content,
            'layout' => MOD_SLIDESHOW_LAYOUT_BACKGROUND,
        ]);
        $slide = $this->reload_slide($slideid);
        return [$slide, $context, $course];
    }

    /**
     * Moodle editor payload for a quick-edit save.
     *
     * @param string $text Editor HTML.
     * @return array{text: string, format: int, itemid: int}
     */
    private function editor_payload(string $text): array {
        return [
            'text' => $text,
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];
    }

    /**
     * Reload a slide row.
     *
     * @param int $slideid Slide id.
     * @return \stdClass
     */
    private function reload_slide(int $slideid): \stdClass {
        global $DB;
        return $DB->get_record('slideshow_slide', ['id' => $slideid], '*', MUST_EXIST);
    }

    /**
     * Store one file in the slide image area.
     *
     * @param int $contextid Module context id.
     * @param int $slideid Slide id.
     * @param string $filename File name.
     * @param string $contents File contents.
     */
    private function store_image(int $contextid, int $slideid, string $filename, string $contents): void {
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'mod_slideshow',
            'filearea' => 'image',
            'itemid' => $slideid,
            'filepath' => '/',
            'filename' => $filename,
        ], $contents);
    }

    /**
     * File names stored for a slide image.
     *
     * @param int $contextid Module context id.
     * @param int $slideid Slide id.
     * @return string[]
     */
    private function image_filenames(int $contextid, int $slideid): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_slideshow', 'image', $slideid, 'filename', false);
        $names = [];
        foreach ($files as $file) {
            $names[] = $file->get_filename();
        }
        return $names;
    }
}

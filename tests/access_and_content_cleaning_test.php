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

use core_external\external_api;
use mod_slideshow\external\reorder_slide;
use mod_slideshow\external\save_slide;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/slideshow/locallib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests for slide management permissions, content cleaning, restore and file handling.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_and_content_cleaning_test extends \advanced_testcase {
    /** @var string Stored XSS payload used by several tests. */
    private const PAYLOAD = '<p>Body</p><img src="x" onerror="alert(1)"><script>alert(2)</script>';

    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a course, a slideshow and one slide.
     *
     * @param array $slide Extra slide fields.
     * @return array{0: \stdClass, 1: \stdClass, 2: \context_module, 3: int} Course, slideshow, context, slide id.
     */
    private function create_fixture(array $slide = []): array {
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $slideid = $this->getDataGenerator()->get_plugin_generator('mod_slideshow')->create_slide($slide + [
            'slideshow' => $slideshow->id,
            'name' => 'Slide A',
            'content' => '<p>Body</p>',
            'sortorder' => 0,
        ]);
        return [$course, $slideshow, $context, (int) $slideid];
    }

    /**
     * Enrol an editing teacher whose manageslides is prohibited in the module (viewslides stays allowed).
     *
     * @param \stdClass $course Course.
     * @param \context_module $context Module context.
     * @return \stdClass User.
     */
    private function create_viewslides_only_user(\stdClass $course, \context_module $context): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('mod/slideshow:viewslides', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('mod/slideshow:manageslides', CAP_PROHIBIT, $roleid, $context->id, true);
        return $user;
    }

    /**
     * The save service requires manageslides, not viewslides.
     *
     * @covers \mod_slideshow\external\save_slide::execute
     */
    public function test_save_slide_requires_manageslides(): void {
        [$course, $slideshow, $context, $slideid] = $this->create_fixture();
        $user = $this->create_viewslides_only_user($course, $context);
        $this->setUser($user);
        $this->assertTrue(has_capability('mod/slideshow:viewslides', $context));

        $this->expectException(\required_capability_exception::class);
        save_slide::execute((int) $slideshow->cmid, $slideid, 'Renamed', MOD_SLIDESHOW_LAYOUT_TITLEONLY);
    }

    /**
     * The quick-edit save helper requires manageslides.
     *
     * @covers ::slideshow_save_quick_edit
     */
    public function test_quick_edit_requires_manageslides(): void {
        global $DB;
        [$course, , $context, $slideid] = $this->create_fixture();
        $this->setUser($this->create_viewslides_only_user($course, $context));

        $this->expectException(\required_capability_exception::class);
        slideshow_save_quick_edit($DB->get_record('slideshow_slide', ['id' => $slideid]), $context, (object) [
            'name' => 'Renamed',
            'layout' => MOD_SLIDESHOW_LAYOUT_TITLEONLY,
        ]);
    }

    /**
     * With context freezing, a teacher cannot save slides.
     *
     * @covers \mod_slideshow\external\save_slide::execute
     */
    public function test_save_slide_blocked_in_locked_context(): void {
        global $CFG;
        [$course, $slideshow, $context, $slideid] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $CFG->contextlocking = 1;
        $context->set_locked(true);
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        save_slide::execute((int) $slideshow->cmid, $slideid, 'Renamed', MOD_SLIDESHOW_LAYOUT_TITLEONLY);
    }

    /**
     * The edit form record never takes visibility from the submitted data.
     *
     * @covers ::slideshow_prepare_slide_save_record
     */
    public function test_prepare_record_ignores_submitted_hidden(): void {
        $new = slideshow_prepare_slide_save_record((object) ['name' => 'A', 'hidden' => 1], 3, true, 1);
        $this->assertSame(0, $new->hidden);

        $update = slideshow_prepare_slide_save_record((object) ['id' => 5, 'name' => 'A', 'hidden' => 1], 3, false);
        $this->assertObjectNotHasProperty('hidden', $update);
    }

    /**
     * By default the non-editing teacher can list slides but not manage them.
     *
     * @coversNothing
     */
    public function test_default_archetypes(): void {
        [$course, , $context] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->assertTrue(has_capability('mod/slideshow:viewslides', $context, $teacher));
        $this->assertFalse(has_capability('mod/slideshow:manageslides', $context, $teacher));

        $capinfo = get_capability_info('mod/slideshow:manageslides');
        $this->assertSame('write', $capinfo->captype);
        $this->assertEquals(RISK_XSS | RISK_DATALOSS, $capinfo->riskbitmask & (RISK_XSS | RISK_DATALOSS));
    }

    /**
     * The preview fragment returns cleaned HTML and needs manageslides.
     *
     * @covers ::mod_slideshow_output_fragment_preview
     */
    public function test_preview_fragment_cleans_content(): void {
        [$course, , $context] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $html = mod_slideshow_output_fragment_preview([
            'context' => $context,
            'content' => self::PAYLOAD,
            'format' => FORMAT_HTML,
        ]);
        $this->assertStringContainsString('Body', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<script', $html);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        mod_slideshow_output_fragment_preview([
            'context' => $context,
            'content' => '<p>x</p>',
            'format' => FORMAT_HTML,
        ]);
    }

    /**
     * Slides passed to slideshow_add_instance() are whitelisted and cleaned.
     *
     * @covers ::slideshow_add_instance
     */
    public function test_add_instance_cleans_slides(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', [
            'course' => $course->id,
            'slides' => [['name' => 'Generated', 'content' => self::PAYLOAD, 'id' => 99999, 'slideshow' => 1]],
        ]);
        $slides = $DB->get_records('slideshow_slide', ['slideshow' => $slideshow->id]);
        $this->assertCount(1, $slides);
        $slide = reset($slides);
        $this->assertNotEquals(99999, (int) $slide->id);
        $this->assertStringNotContainsString('onerror', $slide->content);
        $this->assertStringNotContainsString('<script', $slide->content);
    }

    /**
     * Back up one slideshow and restore it into a new course.
     *
     * @param int $cmid Course module id.
     * @return array{0: \stdClass, 1: \context_module} Restored slideshow row and module context.
     */
    private function backup_and_restore(int $cmid): array {
        global $DB, $USER;

        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourse = $this->getDataGenerator()->create_course();
        $rc = new \restore_controller(
            $backupid,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $restored = $DB->get_record('slideshow', ['course' => $newcourse->id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('slideshow', $restored->id, $newcourse->id, false, MUST_EXIST);
        return [$restored, \context_module::instance($cm->id)];
    }

    /**
     * Restore cleans slide HTML and display options and keeps featured images.
     *
     * @covers \restore_slideshow_activity_structure_step
     * @covers \backup_slideshow_activity_structure_step
     */
    public function test_backup_restore_cleans_content_and_keeps_image(): void {
        global $DB;
        $this->setAdminUser();
        [, $slideshow, $context, $slideid] = $this->create_fixture(['content' => self::PAYLOAD]);
        $DB->update_record('slideshow', (object) [
            'id' => $slideshow->id,
            'display' => RESOURCELIB_DISPLAY_POPUP,
            'displayoptions' => serialize(['popupwidth' => "1'),alert(document.cookie),('", 'popupheight' => '300']),
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_slideshow',
            'filearea' => 'image',
            'itemid' => $slideid,
            'filepath' => '/',
            'filename' => 'cover.png',
        ], 'not really a png');

        [$restored, $newcontext] = $this->backup_and_restore((int) $slideshow->cmid);

        $newslide = $DB->get_record('slideshow_slide', ['slideshow' => $restored->id], '*', MUST_EXIST);
        $this->assertStringNotContainsString('onerror', $newslide->content);
        $this->assertStringNotContainsString('<script', $newslide->content);

        $options = unserialize_array($restored->displayoptions);
        $this->assertSame(1, $options['popupwidth']);
        $this->assertSame(300, $options['popupheight']);

        $files = get_file_storage()->get_area_files($newcontext->id, 'mod_slideshow', 'image', $newslide->id, 'id', false);
        $this->assertCount(1, $files);
        $this->assertSame('cover.png', reset($files)->get_filename());
    }

    /**
     * The quick-edit fragment is bound to the validated context.
     *
     * @covers ::mod_slideshow_output_fragment_quickedit
     */
    public function test_quickedit_fragment_rejects_other_context(): void {
        [$course, $slideshow, , $slideid] = $this->create_fixture();
        $other = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        mod_slideshow_output_fragment_quickedit([
            'context' => \context_module::instance($other->cmid),
            'cmid' => (string) $slideshow->cmid,
            'slideid' => (string) $slideid,
        ]);
    }

    /**
     * The quick-edit fragment runs require_login for the module.
     *
     * @covers ::mod_slideshow_output_fragment_quickedit
     */
    public function test_quickedit_fragment_requires_module_access(): void {
        global $DB;
        [$course, $slideshow, $context, $slideid] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        set_coursemodule_visible($slideshow->cmid, 0);
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/course:viewhiddenactivities', CAP_PROHIBIT, $roleid, $context->id, true);
        $this->setUser($teacher);

        $this->expectException(\require_login_exception::class);
        mod_slideshow_output_fragment_quickedit([
            'context' => $context,
            'cmid' => (string) $slideshow->cmid,
            'slideid' => (string) $slideid,
        ]);
    }

    /**
     * The authorised slide is the one that moves and the event records it.
     *
     * @covers \mod_slideshow\external\reorder_slide::execute
     */
    public function test_reorder_moves_authorised_slide(): void {
        global $DB;
        [$course, $slideshow, , $first] = $this->create_fixture(['sortorder' => 1]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_slideshow');
        $second = (int) $generator->create_slide(['slideshow' => $slideshow->id, 'name' => 'B', 'sortorder' => 2]);
        $third = (int) $generator->create_slide(['slideshow' => $slideshow->id, 'name' => 'C', 'sortorder' => 3]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $sink = $this->redirectEvents();
        $result = reorder_slide::execute($third, 0);
        $result = external_api::clean_returnvalue(reorder_slide::execute_returns(), $result);
        $this->assertTrue($result['result']);

        $order = $DB->get_fieldset_select('slideshow_slide', 'id', 'slideshow = ? ORDER BY sortorder', [$slideshow->id]);
        $this->assertEquals([$third, $first, $second], array_map('intval', $order));

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \mod_slideshow\event\slides_reordered
        ));
        $this->assertCount(1, $events);
        $this->assertEquals($third, $events[0]->objectid);
    }

    /**
     * An out-of-range target position is rejected.
     *
     * @covers \mod_slideshow\external\reorder_slide::execute
     */
    public function test_reorder_rejects_out_of_range_position(): void {
        [$course, , , $slideid] = $this->create_fixture();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->expectException(\invalid_parameter_exception::class);
        reorder_slide::execute($slideid, 5);
    }

    /**
     * Deleting a slide also deletes its featured image.
     *
     * @covers ::slideshow_process_ajax_action
     */
    public function test_delete_removes_featured_image(): void {
        global $DB;
        [, $slideshow, $context, $slideid] = $this->create_fixture();
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_slideshow',
            'filearea' => 'image',
            'itemid' => $slideid,
            'filepath' => '/',
            'filename' => 'cover.png',
        ], 'img');

        $slide = $DB->get_record('slideshow_slide', ['id' => $slideid]);
        $slideshowrow = $DB->get_record('slideshow', ['id' => $slideshow->id]);
        $result = slideshow_process_ajax_action('delete', $slide, $slideshowrow, $context);

        $this->assertTrue($result['result']);
        $this->assertTrue($fs->is_area_empty($context->id, 'mod_slideshow', 'image', $slideid, false));
    }

    /**
     * Popup sizes are cast to integers in the course page onclick.
     *
     * @covers ::slideshow_get_coursemodule_info
     */
    public function test_coursemodule_info_casts_popup_options(): void {
        global $DB;
        [, $slideshow] = $this->create_fixture();
        $DB->update_record('slideshow', (object) [
            'id' => $slideshow->id,
            'display' => RESOURCELIB_DISPLAY_POPUP,
            'displayoptions' => serialize(['popupwidth' => "1'),alert(document.cookie),('", 'popupheight' => 'x']),
        ]);
        $cm = get_coursemodule_from_id('slideshow', $slideshow->cmid);
        $cm->showdescription = 0;

        $info = slideshow_get_coursemodule_info($cm);
        $this->assertStringNotContainsString('alert', $info->onclick);
        $this->assertStringContainsString('width=1,height=450,', $info->onclick);
    }

    /**
     * Students do not see files of hidden slides in the exported contents.
     *
     * @covers ::slideshow_export_contents
     */
    public function test_export_contents_skips_hidden_slide_files(): void {
        [$course, $slideshow, $context, $visibleid] = $this->create_fixture();
        $hiddenid = (int) $this->getDataGenerator()->get_plugin_generator('mod_slideshow')->create_slide([
            'slideshow' => $slideshow->id,
            'name' => 'Hidden',
            'hidden' => 1,
            'sortorder' => 1,
        ]);
        $fs = get_file_storage();
        foreach ([$visibleid => 'visible.txt', $hiddenid => 'hidden.txt', 0 => 'legacy.txt'] as $itemid => $name) {
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_slideshow',
                'filearea' => 'content',
                'itemid' => $itemid,
                'filepath' => '/',
                'filename' => $name,
            ], $name);
        }
        $cm = get_coursemodule_from_id('slideshow', $slideshow->cmid);

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $names = array_column(slideshow_export_contents($cm, 'webservice/pluginfile.php'), 'filename');
        $this->assertContains('visible.txt', $names);
        $this->assertNotContains('hidden.txt', $names);
        $this->assertNotContains('legacy.txt', $names);

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $names = array_column(slideshow_export_contents($cm, 'webservice/pluginfile.php'), 'filename');
        $this->assertContains('hidden.txt', $names);
        $this->assertContains('legacy.txt', $names);
    }
}

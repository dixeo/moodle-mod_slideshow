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
 * Tests for hidden slide file access rules.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class slide_file_access_test extends \advanced_testcase {

    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Visible slide files are available to participants with mod/slideshow:view.
     */
    public function test_visible_slide_files_allowed_for_viewers(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $slide = (object) ['hidden' => 0];

        $this->setUser($student);
        $this->assertTrue(slideshow_user_can_view_slide_files($slide, $context));
    }

    /**
     * Hidden slide files are denied to viewers without mod/slideshow:viewslides.
     */
    public function test_hidden_slide_files_denied_for_viewers(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $slide = (object) ['hidden' => 1];

        $this->setUser($student);
        $this->assertFalse(slideshow_user_can_view_slide_files($slide, $context));
    }

    /**
     * Teachers with mod/slideshow:viewslides may load files for hidden slides.
     */
    public function test_hidden_slide_files_allowed_for_slide_managers(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $slide = (object) ['hidden' => 1];

        $this->setUser($teacher);
        $this->assertTrue(slideshow_user_can_view_slide_files($slide, $context));
    }

    /**
     * Legacy shared itemid 0 files are denied to viewers without mod/slideshow:manageslides.
     */
    public function test_legacy_itemid_zero_files_denied_for_viewers(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($student);
        $this->assertFalse(slideshow_user_can_view_slide_files(null, $context));
    }

    /**
     * Teachers with mod/slideshow:manageslides may load legacy shared itemid 0 files.
     */
    public function test_legacy_itemid_zero_files_allowed_for_slide_managers(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);
        $this->assertTrue(slideshow_user_can_view_slide_files(null, $context));
    }

    /**
     * pluginfile denies legacy itemid 0 for students even when a shared file exists.
     */
    public function test_pluginfile_denies_legacy_itemid_zero_for_viewers(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $cm = get_coursemodule_from_id('slideshow', $slideshow->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_slideshow',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'legacy.png',
        ], 'png');

        $this->setUser($student);
        $result = slideshow_pluginfile($course, $cm, $context, 'content', [0, 'legacy.png'], false);
        $this->assertFalse($result);
    }

    /**
     * pluginfile denies hidden slide files for students when requested by slide itemid.
     */
    public function test_pluginfile_denies_hidden_slide_files_for_viewers(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/slideshow/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $cm = get_coursemodule_from_id('slideshow', $slideshow->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_slideshow');
        $slideid = $generator->create_slide([
            'slideshow' => $slideshow->id,
            'hidden' => 1,
        ]);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_slideshow',
            'filearea' => 'content',
            'itemid' => $slideid,
            'filepath' => '/',
            'filename' => 'hidden.png',
        ], 'png');

        $this->setUser($student);
        $result = slideshow_pluginfile($course, $cm, $context, 'content', [$slideid, 'hidden.png'], false);
        $this->assertFalse($result);
    }
}

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
 * External slide mutation services.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow;

use core_external\external_api;
use mod_slideshow\external\delete_slide;
use mod_slideshow\external\reorder_slide;
use mod_slideshow\external\set_slide_visibility;

/**
 * External slide mutation services.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class external_slide_actions_test extends \advanced_testcase {
    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a slideshow with one visible slide and an editing teacher.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: int}
     */
    private function create_slide_for_teacher(): array {
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_slideshow');
        $slideid = $generator->create_slide((object) [
            'slideshow' => $slideshow->id,
            'name' => 'Slide A',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'sortorder' => 0,
            'timemodified' => time(),
        ]);
        $this->setUser($teacher);

        return [$slideshow, $teacher, $slideid];
    }

    /**
     * An editing teacher can hide a slide through the external service.
     *
     * @covers \mod_slideshow\external\set_slide_visibility::execute
     */
    public function test_teacher_can_hide_slide(): void {
        global $DB;
        [, , $slideid] = $this->create_slide_for_teacher();

        $result = set_slide_visibility::execute($slideid, 'hide');
        $result = external_api::clean_returnvalue(set_slide_visibility::execute_returns(), $result);

        $this->assertTrue($result['result']);
        $this->assertEquals('hide', $result['action']);
        $this->assertEquals(1, (int) $DB->get_field('slideshow_slide', 'hidden', ['id' => $slideid]));
    }

    /**
     * A student cannot delete a slide.
     *
     * @covers \mod_slideshow\external\delete_slide::execute
     */
    public function test_student_cannot_delete_slide(): void {
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_slideshow');
        $slideid = $generator->create_slide((object) [
            'slideshow' => $slideshow->id,
            'name' => 'Slide A',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'sortorder' => 0,
            'timemodified' => time(),
        ]);
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        delete_slide::execute($slideid);
    }

    /**
     * Visibility rejects an action other than show or hide.
     *
     * @covers \mod_slideshow\external\set_slide_visibility::execute
     */
    public function test_visibility_rejects_unknown_action(): void {
        [, , $slideid] = $this->create_slide_for_teacher();

        $this->expectException(\invalid_parameter_exception::class);
        set_slide_visibility::execute($slideid, 'move');
    }

    /**
     * An editing teacher can move the first of two slides.
     *
     * @covers \mod_slideshow\external\reorder_slide::execute
     */
    public function test_teacher_can_reorder_slide(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_slideshow');
        $firstid = $generator->create_slide((object) [
            'slideshow' => $slideshow->id,
            'name' => 'Slide A',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'sortorder' => 0,
            'timemodified' => time(),
        ]);
        $secondid = $generator->create_slide((object) [
            'slideshow' => $slideshow->id,
            'name' => 'Slide B',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'sortorder' => 1,
            'timemodified' => time(),
        ]);
        $this->setUser($teacher);

        $result = reorder_slide::execute($firstid, 0, 1);
        $result = external_api::clean_returnvalue(reorder_slide::execute_returns(), $result);

        $this->assertTrue($result['result']);
        $this->assertEquals(1, (int) $DB->get_field('slideshow_slide', 'sortorder', ['id' => $firstid]));
        $this->assertEquals(0, (int) $DB->get_field('slideshow_slide', 'sortorder', ['id' => $secondid]));
    }
}

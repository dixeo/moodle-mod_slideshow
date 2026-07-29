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
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
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
        $this->assertObjectNotHasProperty('id', $record);
    }
}

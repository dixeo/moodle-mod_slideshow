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
 * Slideshow slide editing follows the Dixeo config master switch.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::slideshow_use_dixeo_editor
 */
final class dixeo_editor_gate_test extends \advanced_testcase {
    /**
     * An existing slide stays on the Moodle slide form when Dixeo is off.
     */
    public function test_slide_edit_uses_the_moodle_form_when_dixeo_is_off(): void {
        global $CFG;

        if (!\core_plugin_manager::instance()->get_plugin_info('local_dixeo_editor')) {
            $this->markTestSkipped('local_dixeo_editor is required for this test.');
        }
        if (!class_exists(\local_dixeo\external\service_factory::class)) {
            $this->markTestSkipped('local_dixeo is required for this test.');
        }

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $config = \local_dixeo\external\service_factory::get_settings_config();

        $this->assertTrue(\slideshow_use_dixeo_editor($context));

        $config->set('local_dixeo', 'enabled', \context_system::instance(), '0');
        $this->assertFalse(\slideshow_use_dixeo_editor($context));

        $config->set('local_dixeo', 'enabled', \context_system::instance(), '1');
        $config->set('local_dixeo', 'enabled', \context_course::instance($course->id), '0');
        $this->assertFalse(\slideshow_use_dixeo_editor($context));
    }
}

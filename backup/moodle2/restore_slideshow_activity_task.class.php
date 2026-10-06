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
 * Restore task for mod_slideshow.
 *
 * @package    mod_slideshow
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/slideshow/backup/moodle2/restore_slideshow_stepslib.php');

/**
 * Restore task implementation.
 */
class restore_slideshow_activity_task extends restore_activity_task {
    /**
     * No extra restore settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Single structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_slideshow_activity_structure_step('slideshow_structure', 'slideshow.xml'));
    }

    /**
     * Content fields to pass through the interlink decoder.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('slideshow', ['intro'], 'slideshow');
        $contents[] = new restore_decode_content('slideshow_slide', ['content'], 'slideshow_slide');

        return $contents;
    }

    /**
     * URL rewrite rules for restored links.
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('SLIDESHOWINDEX', '/mod/slideshow/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('SLIDESHOWVIEWBYID', '/mod/slideshow/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('SLIDESHOWVIEWBYP', '/mod/slideshow/view.php?p=$1', 'slideshow');
        $rules[] = new restore_decode_rule('SLIDESHOWSLIDES', '/mod/slideshow/slides.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule(
            'SLIDESHOWEDIT',
            '/mod/slideshow/edit.php?cm=$1&id=$2',
            ['course_module', 'slideshow_slide']
        );

        return $rules;
    }

    /**
     * Activity log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('slideshow', 'add', 'view.php?id={course_module}', '{slideshow}');
        $rules[] = new restore_log_rule('slideshow', 'update', 'view.php?id={course_module}', '{slideshow}');
        $rules[] = new restore_log_rule('slideshow', 'view', 'view.php?id={course_module}', '{slideshow}');

        return $rules;
    }

    /**
     * Course-level log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('slideshow', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}

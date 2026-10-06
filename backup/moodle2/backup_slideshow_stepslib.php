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
 * Backup structure for mod_slideshow.
 *
 * @package    mod_slideshow
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines slideshow + slides + embedded files for moodle2 backup.
 */
class backup_slideshow_activity_structure_step extends backup_activity_structure_step {
    /**
     * Build the nested backup element tree for slideshow and slides.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        $slideshow = new backup_nested_element('slideshow', ['id'], [
            'course', 'name', 'intro', 'introformat', 'display', 'displayoptions',
            'revision', 'timemodified',
        ]);

        $slides = new backup_nested_element('slides');
        $slide = new backup_nested_element('slide', ['id'], [
            'slideshow', 'name', 'content', 'contentformat', 'hidden', 'sortorder', 'timemodified',
        ]);

        $slideshow->add_child($slides);
        $slides->add_child($slide);

        $slideshow->set_source_table('slideshow', ['id' => backup::VAR_ACTIVITYID]);

        $slide->set_source_sql(
            'SELECT * FROM {slideshow_slide} WHERE slideshow = ? ORDER BY sortorder ASC, id ASC',
            [backup::VAR_ACTIVITYID]
        );

        $slideshow->annotate_files('mod_slideshow', 'intro', null);
        $slide->annotate_files('mod_slideshow', 'content', 'id');

        return $this->prepare_activity_structure($slideshow);
    }
}

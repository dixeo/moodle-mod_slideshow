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
 * Restore structure step for mod_slideshow.
 *
 * @package    mod_slideshow
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores slideshow instance, slides, and files.
 */
class restore_slideshow_activity_structure_step extends restore_activity_structure_step {
    /**
     * Declare restore path elements for the activity XML.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('slideshow', '/activity/slideshow');
        $paths[] = new restore_path_element('slideshow_slide', '/activity/slideshow/slides/slide');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Insert the restored slideshow activity row.
     *
     * @param array $data Parsed XML fields.
     */
    protected function process_slideshow($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('slideshow', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Insert one slide row and register id mapping.
     *
     * @param array $data Parsed XML fields.
     */
    protected function process_slideshow_slide($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        unset($data->id);

        $data->slideshow = $this->get_new_parentid('slideshow');
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('slideshow_slide', $data);
        $this->set_mapping('slideshow_slide', $oldid, $newitemid, true);
    }

    /**
     * Attach intro and slide content files after restore.
     */
    protected function after_execute() {
        $this->add_related_files('mod_slideshow', 'intro', null);
        $this->add_related_files('mod_slideshow', 'content', 'slideshow_slide');
    }
}

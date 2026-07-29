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
 * Slideshow module upgrade.
 *
 * @package    mod_slideshow
 * @copyright  2024 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute slideshow upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_slideshow_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2023100906) {
        require_once(__DIR__ . '/../lib.php');
        slideshow_upgrade_migrate_slide_content_files();
        upgrade_mod_savepoint(true, 2023100906, 'slideshow');
    }

    if ($oldversion < 2026032811) {
        // Moodle 2 backup/restore API enabled; no schema change.
        upgrade_mod_savepoint(true, 2026032811, 'slideshow');
    }

    if ($oldversion < 2026032813) {
        // The slideshow_slide.slideshow column must reference slideshow.id ($cm->instance), not course_modules.id.
        $slideshowmoduleid = $DB->get_field('modules', 'id', ['name' => 'slideshow'], IGNORE_MISSING);
        if ($slideshowmoduleid) {
            $slides = $DB->get_records('slideshow_slide', [], 'id ASC');
            foreach ($slides as $slide) {
                $cm = $DB->get_record('course_modules', [
                    'id' => $slide->slideshow,
                    'module' => $slideshowmoduleid,
                ], 'instance', IGNORE_MISSING);
                if ($cm) {
                    $DB->set_field('slideshow_slide', 'slideshow', $cm->instance, ['id' => $slide->id]);
                }
            }
        }
        upgrade_mod_savepoint(true, 2026032813, 'slideshow');
    }

    if ($oldversion < 2026032816) {
        upgrade_set_timeout(3600);
        $slides = $DB->get_recordset('slideshow_slide', null, 'id ASC', 'id, content, contentformat');
        foreach ($slides as $slide) {
            $format = (int) ($slide->contentformat ?? FORMAT_HTML);
            $content = (string) $slide->content;
            $clean = $content === '' ? '' : clean_text($content, $format);
            if ($clean !== $content) {
                $DB->set_field('slideshow_slide', 'content', $clean, ['id' => $slide->id]);
            }
        }
        $slides->close();
        upgrade_mod_savepoint(true, 2026032816, 'slideshow');
    }

    return true;
}

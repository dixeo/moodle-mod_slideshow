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
 * Backup task for one slideshow activity instance.
 *
 * @package    mod_slideshow
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/slideshow/backup/moodle2/backup_slideshow_stepslib.php');

/**
 * Backup task implementation.
 */
class backup_slideshow_activity_task extends backup_activity_task {
    /**
     * Activity-specific backup settings (none).
     */
    protected function define_my_settings() {
    }

    /**
     * Register structure step producing slideshow.xml.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_slideshow_activity_structure_step('slideshow_structure', 'slideshow.xml'));
    }

    /**
     * Encode pluginfile and activity URLs in backed-up content.
     *
     * @param string $content HTML or text that may contain wwwroot links
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = '/(' . $base . '\/mod\/slideshow\/index.php\?id=)([0-9]+)/';
        $content = preg_replace($search, '$@SLIDESHOWINDEX*$2@$', $content);

        $search = '/(' . $base . '\/mod\/slideshow\/view.php\?id=)([0-9]+)/';
        $content = preg_replace($search, '$@SLIDESHOWVIEWBYID*$2@$', $content);

        $search = '/(' . $base . '\/mod\/slideshow\/view.php\?p=)([0-9]+)/';
        $content = preg_replace($search, '$@SLIDESHOWVIEWBYP*$2@$', $content);

        $search = '/(' . $base . '\/mod\/slideshow\/slides.php\?id=)([0-9]+)/';
        $content = preg_replace($search, '$@SLIDESHOWSLIDES*$2@$', $content);

        $search = '/(' . $base . '\/mod\/slideshow\/edit.php\?cm=)([0-9]+)(&amp;|&)id=([0-9]+)/';
        $content = preg_replace($search, '$@SLIDESHOWEDIT*$2*$4@$', $content);

        return $content;
    }
}

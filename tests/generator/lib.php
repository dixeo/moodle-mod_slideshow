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
 * mod_slideshow data generator.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mod_slideshow data generator class.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_slideshow_generator extends testing_module_generator {

    /**
     * Create a slideshow module instance.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass activity record with cmid set
     */
    public function create_instance($record = null, ?array $options = null) {
        global $CFG;
        require_once($CFG->libdir . '/resourcelib.php');

        $record = (array) $record + [
            'display' => RESOURCELIB_DISPLAY_AUTO,
        ];

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Insert a slideshow slide row for tests.
     *
     * @param array|stdClass $record Slide fields (slideshow id required).
     * @return int New slide id.
     */
    public function create_slide($record) {
        global $DB;

        $record = (array) $record;
        if (empty($record['slideshow'])) {
            throw new \coding_exception('slideshow id is required to create a slide');
        }

        $record += [
            'name' => 'Test slide',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'sortorder' => 0,
            'timemodified' => time(),
        ];

        return $DB->insert_record('slideshow_slide', $record);
    }
}

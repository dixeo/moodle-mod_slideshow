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
 * External service to delete a slideshow slide.
 *
 * @package    mod_slideshow
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Delete one slide and renumber the remaining slides.
 */
class delete_slide extends external_api {
    use manage_slide;

    /**
     * Parameters for deleting a slide.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'slideid' => new external_value(PARAM_INT, 'Slide id'),
        ]);
    }

    /**
     * Delete a slide the user is allowed to manage.
     *
     * @param int $slideid Slide id
     * @return array Slide id and whether the slide was deleted
     */
    public static function execute(int $slideid): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'slideid' => $slideid,
        ]);
        [$slide, $slideshow, $context] = self::require_manage_slide($params['slideid']);

        return slideshow_process_ajax_action('delete', $slide, $slideshow, $context);
    }

    /**
     * Return values for a deletion.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'slide' => new external_value(PARAM_INT, 'Slide id'),
            'result' => new external_value(PARAM_BOOL, 'Whether the slide was deleted'),
        ]);
    }
}

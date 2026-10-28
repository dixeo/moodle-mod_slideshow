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
 * External service to reorder a slideshow slide.
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
 * Move one slide from an old sort position to a new one.
 */
class reorder_slide extends external_api {
    use manage_slide;

    /**
     * Parameters for reordering a slide.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'slideid' => new external_value(PARAM_INT, 'Slide id'),
            'oldorder' => new external_value(PARAM_INT, 'Current sort position'),
            'neworder' => new external_value(PARAM_INT, 'Target sort position'),
        ]);
    }

    /**
     * Reorder a slide the user is allowed to manage.
     *
     * @param int $slideid Slide id
     * @param int $oldorder Current sort position
     * @param int $neworder Target sort position
     * @return array Slide id and whether the new order was stored
     */
    public static function execute(int $slideid, int $oldorder, int $neworder): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'slideid' => $slideid,
            'oldorder' => $oldorder,
            'neworder' => $neworder,
        ]);
        [$slide, $slideshow, $context] = self::require_manage_slide($params['slideid']);

        return slideshow_process_ajax_action(
            'reorder',
            $slide,
            $slideshow,
            $context,
            $params['oldorder'],
            $params['neworder']
        );
    }

    /**
     * Return values for a reorder.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'slide' => new external_value(PARAM_INT, 'Slide id'),
            'result' => new external_value(PARAM_BOOL, 'Whether the new order was stored'),
        ]);
    }
}

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
 * External service to show or hide a slideshow slide.
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
 * Show or hide one slide.
 */
class set_slide_visibility extends external_api {
    use manage_slide;

    /**
     * Parameters for changing slide visibility.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'slideid' => new external_value(PARAM_INT, 'Slide id'),
            'action' => new external_value(PARAM_ALPHA, 'show or hide'),
        ]);
    }

    /**
     * Show or hide a slide the user is allowed to manage.
     *
     * @param int $slideid Slide id
     * @param string $action show or hide
     * @return array Slide id, applied action, and whether it was stored
     */
    public static function execute(int $slideid, string $action): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'slideid' => $slideid,
            'action' => $action,
        ]);
        if ($params['action'] !== 'show' && $params['action'] !== 'hide') {
            throw new \invalid_parameter_exception('Invalid visibility action');
        }
        [$slide, $slideshow, $context] = self::require_manage_slide($params['slideid']);

        return slideshow_process_ajax_action($params['action'], $slide, $slideshow, $context);
    }

    /**
     * Return values for a visibility change.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'slide' => new external_value(PARAM_INT, 'Slide id'),
            'action' => new external_value(PARAM_ALPHA, 'Applied action, show or hide'),
            'result' => new external_value(PARAM_BOOL, 'Whether the visibility was stored'),
        ]);
    }
}

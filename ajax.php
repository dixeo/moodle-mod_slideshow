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
 * AJAX endpoints for slide reorder and related actions.
 *
 * @package    mod_slideshow
 * @copyright  2025 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

global $DB;

$slideid = required_param('slideid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$oldorder = optional_param('oldorder', 0, PARAM_INT);
$neworder = optional_param('neworder', 0, PARAM_INT);

if (!$slide = $DB->get_record('slideshow_slide', ['id' => $slideid])) {
    throw new \moodle_exception('invalidaccessparameter');
}

if (!$cm = get_coursemodule_from_instance('slideshow', $slide->slideshow, 0, false)) {
    throw new \moodle_exception('invalidcoursemodule');
}

$slideshow = $DB->get_record('slideshow', ['id' => $slide->slideshow], '*', MUST_EXIST);

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/slideshow:manageslides', $context);

if (!confirm_sesskey()) {
    $error = ['error' => get_string('invalidsesskey', 'error')];
    die(json_encode($error));
}

$response = slideshow_process_ajax_action($action, $slide, $slideshow, $context, $oldorder, $neworder);
if ($response !== []) {
    echo json_encode($response);
}

die;

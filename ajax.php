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
 * @copyright  2025 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once('../../config.php');

global $DB, $PAGE, $OUTPUT;

$slideid = required_param('slideid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$oldorder = optional_param('oldorder', 0, PARAM_INT);
$neworder = optional_param('neworder', 0, PARAM_INT);
$confirm = optional_param('confirm', '', PARAM_ALPHA);

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
require_capability('mod/slideshow:viewslides', $context);

if (!confirm_sesskey()) {
    $error = ['error' => get_string('invalidsesskey', 'error')];
    die(json_encode($error));
}

// Process AJAX request.
switch ($action) {
    case 'reorder':
        $result = true;

        // Update sort order values.
        $records = $DB->get_records('slideshow_slide', ['slideshow' => $slide->slideshow], 'sortorder');
        foreach ($records as $record) {
            if ($record->sortorder == $oldorder) {
                $record->sortorder = $neworder;
            } else {
                if ($neworder > $oldorder) {
                    if ($record->sortorder > $oldorder && $record->sortorder <= $neworder) {
                        $record->sortorder--;
                    }
                } else {
                    if ($record->sortorder >= $neworder && $record->sortorder < $oldorder) {
                        $record->sortorder++;
                    }
                }
            }
            $result += $DB->update_record('slideshow_slide', $record);
        }

        // Fix gaps in sortorder.
        $records = $DB->get_records('slideshow_slide', ['slideshow' => $slide->slideshow], 'sortorder');
        $sortorder = 0;
        foreach ($records as $record) {
            $record->sortorder = $sortorder;
            $sortorder++;
            $result += $DB->update_record('slideshow_slide', $record);
        }

        $movedslide = $DB->get_record('slideshow_slide', ['id' => $slideid], '*', MUST_EXIST);
        $event = \mod_slideshow\event\slides_reordered::create_from_slide($slideshow, $context, $movedslide);
        $event->trigger();

        $response = [
            'slide' => $slideid,
            'result' => $result,
        ];
        echo json_encode($response);

        break;
    case 'delete':
        $event = \mod_slideshow\event\slide_deleted::create_from_slide($slideshow, $context, $slide);
        $event->trigger();

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_slideshow', 'content', $slideid);

        $result = $DB->delete_records('slideshow_slide', ['id' => $slideid]);

        // Renumber sort order after delete.
        $sql = "UPDATE {slideshow_slide} SET sortorder = sortorder -1 WHERE slideshow = ? AND sortorder > ?";
        $result += $DB->execute($sql, [$slide->slideshow, $slide->sortorder]);

        $response = [
            'slide' => $slideid,
            'result' => $result,
        ];
        echo json_encode($response);

        break;
    case 'show':
    case 'hide':
        $slide->hidden = $action == 'hide' ? 1 : 0;
        $result = $DB->update_record('slideshow_slide', $slide);

        $event = \mod_slideshow\event\slide_visibility_updated::create_from_slide($slideshow, $context, $slide);
        $event->trigger();

        $response = [
            'slide' => $slideid,
            'action' => $action,
            'result' => $result,
        ];
        echo json_encode($response);

        break;
    default:
        break;
}

// No matching action; end script.
die;

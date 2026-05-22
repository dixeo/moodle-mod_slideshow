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
 * Teacher slide list and reorder UI.
 *
 * @package    mod_slideshow
 * @copyright  2024 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

$id      = optional_param('id', 0, PARAM_INT); // Course module id.
$p       = optional_param('p', 0, PARAM_INT);  // Slideshow instance id.

if ($p) {
    if (!$slideshow = $DB->get_record('slideshow', ['id' => $p])) {
        throw new \moodle_exception('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('slideshow', $slideshow->id, $slideshow->course, false, MUST_EXIST);
} else {
    if (!$cm = get_coursemodule_from_id('slideshow', $id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    $slideshow = $DB->get_record('slideshow', ['id' => $cm->instance], '*', MUST_EXIST);
}

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

$PAGE->set_pagelayout('admin');

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/slideshow:viewslides', $context);

$PAGE->set_url('/mod/slideshow/slides.php', ['id' => $cm->id]);

$PAGE->add_body_class('limitedwidth');
$PAGE->set_title($course->shortname . ': ' . $slideshow->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($slideshow);
$PAGE->activityheader->set_attrs([
    'hidecompletion' => true,
    'description' => '',
]);

$slippathconfig = [
    'paths' => [
        'mod_slideshow/slip' => $CFG->wwwroot . '/mod/slideshow/js/slip',
    ],
];
$PAGE->requires->js_amd_inline('require.config(' . json_encode($slippathconfig) . ')');

$PAGE->requires->js_call_amd('mod_slideshow/slides', 'init', [$cm->id]);

echo $OUTPUT->header();

$slides = $DB->get_records('slideshow_slide', ['slideshow' => $cm->instance], 'sortorder');

$data = new stdClass();
$data->cmid = $cm->id;
$data->slides = [];
$displayindex = 1;
foreach ($slides as $slide) {
    $data->slides[] = [
        'id' => $slide->id,
        'name' => slideshow_get_slide_list_name($slide),
        'slideshow' => $slide->slideshow,
        'sortorder' => $slide->sortorder,
        'index' => $displayindex,
        'hidden' => $slide->hidden,
    ];
    $displayindex++;
}

echo $OUTPUT->render_from_template('mod_slideshow/slideshow', $data);

$newslideurl = new moodle_url('/mod/slideshow/edit.php', ['cm' => $cm->id]);
echo $OUTPUT->single_button($newslideurl, get_string('addnew', 'slideshow'), 'get', ['class' => 'w-100 mb-3 text-right']);

echo $OUTPUT->footer();

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
 * Learner-facing slideshow player page.
 *
 * @package    mod_slideshow
 * @copyright  2024 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/slideshow/lib.php');
require_once($CFG->dirroot . '/mod/slideshow/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$id      = optional_param('id', 0, PARAM_INT); // Course module id.
$p       = optional_param('p', 0, PARAM_INT);  // Slideshow instance id.
$inpopup = optional_param('inpopup', 0, PARAM_BOOL);

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

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/slideshow:view', $context);

// Completion and trigger events.
slideshow_view($slideshow, $course, $cm, $context);

$PAGE->set_url('/mod/slideshow/view.php', ['id' => $cm->id]);

$PAGE->add_body_class('limitedwidth');
$PAGE->set_title($course->shortname . ': ' . $slideshow->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($slideshow);

$slides = $DB->get_records('slideshow_slide', ['slideshow' => $cm->instance, 'hidden' => 0], 'sortorder');

if ($slides) {
    $jsparams = ['cmid' => $cm->id];

    // Get sharecourse link.
    if (class_exists('\local_sharecourse\sharecourse_helper')) {
        $sharecoursehelper = new \local_sharecourse\sharecourse_helper($DB);
        $courseurl = $sharecoursehelper->get_sharecourse_url($course->id);
        $jsparams['enrolurl'] = $courseurl->out();
    }

    $PAGE->requires->js_call_amd('mod_slideshow/presentation', 'init', [$jsparams]);
}

$slidedata = [];
$firstslide = true;
foreach ($slides as $slide) {
    $content = file_rewrite_pluginfile_urls(
        $slide->content,
        'pluginfile.php',
        $context->id,
        'mod_slideshow',
        'content',
        $slide->id
    );
    $formatoptions = new stdClass();
    $formatoptions->overflowdiv = true;
    $formatoptions->context = $context;
    $content = format_text($content, $slide->contentformat, $formatoptions);
    $content = slideshow_balance_slide_html($content, (int) $slide->id);
    $slidedata[] = [
        'id' => $slide->id,
        'content' => $content,
        'first' => $firstslide,
    ];
    $firstslide = false;
}

$caneditslides = has_capability('mod/slideshow:viewslides', $context);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_slideshow/player', [
    'cmid' => $cm->id,
    'hasslides' => !empty($slides),
    'slides' => $slidedata,
    'slidecount' => count($slides),
    'singleslide' => count($slides) === 1,
    'watermarkurl' => $OUTPUT->image_url('dixeo', 'slideshow')->out(false),
    'caneditslides' => $caneditslides,
    'showqr' => class_exists('\local_sharecourse\sharecourse_helper'),
    'addslideurl' => (new moodle_url('/mod/slideshow/edit.php', ['cm' => $cm->id]))->out(false),
    'previcon' => $OUTPUT->pix_icon('t/collapsed_rtl', get_string('prev', 'slideshow')),
    'nexticon' => $OUTPUT->pix_icon('t/collapsed', get_string('next', 'slideshow')),
    'decreaseicon' => $OUTPUT->pix_icon(
        'e/styleparagraph',
        get_string('decrease', 'slideshow'),
        'core',
        ['class' => 'decrease']
    ),
    'increaseicon' => $OUTPUT->pix_icon(
        'e/styleparagraph',
        get_string('increase', 'slideshow'),
        'core',
        ['class' => 'increase']
    ),
    'fullscreenicon' => $OUTPUT->pix_icon('e/fullscreen', get_string('fullscreen', 'slideshow')),
]);
echo $OUTPUT->footer();

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
 * @copyright  2024 Josemaria Bolanos <admin@mako.digital>
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

    // RequireJS path so presentation loads QR from this plugin (standalone, no local/whatsapp).
    $qrpathconfig = [
        'paths' => [
            'mod_slideshow/qrcode' => $CFG->wwwroot . '/mod/slideshow/js/qrcode-wrapper',
        ],
    ];
    $PAGE->requires->js_amd_inline('require.config(' . json_encode($qrpathconfig) . ')');

    // Get sharecourse link.
    if (class_exists('\local_sharecourse\sharecourse_helper')) {
        $sharecoursehelper = new \local_sharecourse\sharecourse_helper($DB);
        $courseurl = $sharecoursehelper->get_sharecourse_url($course->id);
        $jsparams['enrolurl'] = $courseurl->out();
    }

    $PAGE->requires->js_call_amd('mod_slideshow/presentation', 'init', [$jsparams]);
}

echo $OUTPUT->header();

$slideshtml = '';

if ($slides) {
    // Overlay for enrolment QR code.
    $scantoenrol = html_writer::div(get_string('scantoenrol', 'slideshow'), 'scantoenrol');
    $slideshtml .= html_writer::div($scantoenrol, 'overlay hidden');

    // Prepare each slide.
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
        $formatoptions->noclean = true;
        $formatoptions->overflowdiv = true;
        $formatoptions->context = $context;
        $content = format_text($content, $slide->contentformat, $formatoptions);
        $content = slideshow_balance_slide_html($content, (int) $slide->id);

        $classes = 'slide no-overflow';
        if (!$firstslide) {
            $classes .= ' hidden';
        }
        $firstslide = false;

        $slideshtml .= html_writer::div($content, $classes, ['data-slideid' => $slide->id]);
    }

    // Dixeo logo watermark.
    $logourl = $OUTPUT->image_url('dixeo', 'slideshow');
    $watermark = html_writer::img($logourl, get_string('watermark', 'slideshow'), ['class' => 'watermark']);
    $slideshtml .= html_writer::div($watermark, 'watermark');

    // Navigation buttons.
    $previcon = $OUTPUT->pix_icon('t/collapsed_rtl', get_string('prev', 'slideshow'));
    $prevbutton = html_writer::span($previcon, 'prev disabled');
    $nexticon = $OUTPUT->pix_icon('t/collapsed', get_string('next', 'slideshow'));
    $nextbutton = html_writer::span($nexticon, 'next' . (count($slides) == 1 ? ' disabled' : ''));

    // Current slide indicator.
    $navbuttons = html_writer::span($prevbutton . $nextbutton, 'navbuttons');
    $currentslide = html_writer::span('1/' . count($slides), 'currentslide');

    // Font size controls.
    $fontsize = $OUTPUT->pix_icon('e/styleparagraph', get_string('decrease', 'slideshow'), 'core', ['class' => 'decrease']);
    $fontsize .= html_writer::tag(
        'input',
        '',
        [
            'id' => 'fontsize-slider',
            'class' => 'fontsize',
            'type' => 'range',
            'min' => '10',
            'max' => '500',
            'step' => '5',
            'value' => '125',
        ]
    );
    $fontsize .= $OUTPUT->pix_icon('e/styleparagraph', get_string('increase', 'slideshow'), 'core', ['class' => 'increase']);

    // Enrolment QR.
    $qrcode = '';
    if (class_exists('\local_sharecourse\sharecourse_helper')) {
        $qricon = html_writer::tag('i', '', [
            'class' => 'icon fa fa-solid fa-qrcode fa-fw',
            'title' => get_string('qrcode', 'slideshow'),
            'role' => 'img',
            'aria-label' => get_string('qrcode', 'slideshow'),
        ]);
        $qrcode = html_writer::span($qricon, 'qrcode');
    }

    // Fullscreen button.
    $fullicon = $OUTPUT->pix_icon('e/fullscreen', get_string('fullscreen', 'slideshow'));
    $fullscreen = html_writer::tag('button', $fullicon, [
        'class' => 'fullscreen',
        'tabindex' => '0',
        'aria-label' => get_string('fullscreen', 'slideshow'),
    ]);

    $controls = html_writer::div($fontsize . $qrcode . $fullscreen, 'controls');
    $slideshtml .= html_writer::div($navbuttons . $currentslide . $controls, 'slidecontrols');

    echo $OUTPUT->box($slideshtml, "slideshow-container generalbox center clearfix", 'slideshow-' . $cm->id);

    // Edit slide button.
    if ($hascap = has_capability('mod/slideshow:viewslides', $context)) {
        $editbutton = html_writer::link(
            '#',
            get_string('edit', 'slideshow'),
            ['class' => 'editslide btn btn-secondary float-right']
        );
        echo $editbutton;
    }
} else {
    echo $OUTPUT->box_start('generalbox slideshow-empty clearfix');
    echo html_writer::tag('p', get_string('noslides', 'slideshow'));
    if (has_capability('mod/slideshow:viewslides', $context)) {
        echo html_writer::tag('p', get_string('noslides_teacherhint', 'slideshow'));
        $addslideurl = new moodle_url('/mod/slideshow/edit.php', ['cm' => $cm->id]);
        echo html_writer::link($addslideurl, get_string('addnew', 'slideshow'), ['class' => 'btn btn-primary']);
    }
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();

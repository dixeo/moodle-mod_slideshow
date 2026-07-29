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
 * Library functions and callbacks for mod_slideshow.
 *
 * @package    mod_slideshow
 * @copyright  2024 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * List of features supported in Slideshow module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function slideshow_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * @param stdClass $data The data submitted from the reset course form.
 * @return array Status array for reset reporting.
 */
function slideshow_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return [];
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function slideshow_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function slideshow_get_post_actions() {
    return ['update', 'add'];
}

/**
 * Add slideshow instance.
 * @param stdClass $data
 * @param mod_slideshow_mod_form $mform
 * @return int new slideshow instance id
 */
function slideshow_add_instance($data, $mform = null) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    $cmid = $data->coursemodule;
    $data->timemodified = time();

    $data->id = $DB->insert_record('slideshow', $data);

    // We need context now, so ensure instance and course_modules rows exist first.
    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $cmid]);
    $context = context_module::instance($cmid);

    // Insert slides if any (slideshow_slide.slideshow = activity instance id).
    if (!empty($data->slides) && is_array($data->slides)) {
        foreach ($data->slides as $slidedata) {
            $slidedata['slideshow'] = $data->id;
            $slideid = $DB->insert_record('slideshow_slide', $slidedata);
        }
    }

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'slideshow', $data->id, $completiontimeexpected);

    return $data->id;
}

/**
 * Update slideshow instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function slideshow_update_instance($data, $mform) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    $cmid        = $data->coursemodule;

    $data->timemodified = time();
    $data->id           = $data->instance;
    $data->revision++;

    $DB->update_record('slideshow', $data);

    $context = context_module::instance($cmid);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'slideshow', $data->id, $completiontimeexpected);

    return true;
}

/**
 * Delete slideshow instance.
 * @param int $id
 * @return bool true
 */
function slideshow_delete_instance($id) {
    global $DB;

    if (!$slideshow = $DB->get_record('slideshow', ['id' => $id])) {
        return false;
    }

    $cm = get_coursemodule_from_instance('slideshow', $id, 0, false, MUST_EXIST);
    \core_completion\api::update_completion_date_event($cm->id, 'slideshow', $id, null);

    $DB->delete_records('slideshow_slide', ['slideshow' => $slideshow->id]);

    $DB->delete_records('slideshow', ['id' => $slideshow->id]);

    return true;
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * See {@see course_modinfo::get_array_of_activities()}
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info Info to customise main slideshow display
 */
function slideshow_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if (
        !$slideshow = $DB->get_record(
            'slideshow',
            ['id' => $coursemodule->instance],
            'id, name, display, displayoptions, intro, introformat'
        )
    ) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $slideshow->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('slideshow', $slideshow, $coursemodule->id, false);
    }

    if ($slideshow->display != RESOURCELIB_DISPLAY_POPUP) {
        return $info;
    }

    $fullurl = "$CFG->wwwroot/mod/slideshow/view.php?id=$coursemodule->id&amp;inpopup=1";
    $options = empty($slideshow->displayoptions) ? [] : (array) unserialize_array($slideshow->displayoptions);
    $width  = empty($options['popupwidth']) ? 620 : $options['popupwidth'];
    $height = empty($options['popupheight']) ? 450 : $options['popupheight'];
    $wh = "width=$width,height=$height,toolbar=no,location=no,menubar=no,copyhistory=no,"
        . "status=no,directories=no,scrollbars=yes,resizable=yes";
    $info->onclick = "window.open('$fullurl', '', '$wh'); return false;";

    return $info;
}


/**
 * Lists all browsable file areas
 *
 * @package  mod_slideshow
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function slideshow_get_file_areas($course, $cm, $context) {
    $areas = [];
    $areas['content'] = get_string('content', 'slideshow');
    return $areas;
}

/**
 * File browsing support for slideshow module content area.
 *
 * @package  mod_slideshow
 * @category files
 * @param file_browser $browser file browser instance
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function slideshow_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG;

    if (!has_capability('moodle/course:managefiles', $context)) {
        // Students cannot browse files here without capability.
        return null;
    }

    $fs = get_file_storage();

    if ($filearea === 'content') {
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;
        $itemid = $itemid === null ? 0 : (int) $itemid;

        $urlbase = $CFG->wwwroot . '/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_slideshow', 'content', $itemid, $filepath, $filename)) {
            if ($filepath === '/' && $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_slideshow', 'content', $itemid);
            } else {
                // File not found.
                return null;
            }
        }
        require_once("$CFG->dirroot/mod/slideshow/locallib.php");
        return new slideshow_content_file_info(
            $browser,
            $context,
            $storedfile,
            $urlbase,
            $areas[$filearea],
            true,
            true,
            true,
            false
        );
    }

    // Note: intro area is handled automatically in the file browser.

    return null;
}

/**
 * Serves the slideshow files.
 *
 * @package  mod_slideshow
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function slideshow_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    if (!has_capability('mod/slideshow:view', $context)) {
        return false;
    }

    if ($filearea === 'content') {
        if (count($args) < 2) {
            send_header_404();
            die;
        }
        $itemid = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        if ($itemid > 0) {
            require_once("$CFG->dirroot/mod/slideshow/locallib.php");
            $slide = $DB->get_record('slideshow_slide', [
                'id' => $itemid,
                'slideshow' => $cm->instance,
            ], 'id,hidden', IGNORE_MISSING);
            if (!$slide || !slideshow_user_can_view_slide_files($slide, $context)) {
                return false;
            }
        }

        $options['immutable'] = true;
    } else {
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_slideshow', 'content', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        // Legacy: all slides shared itemid 0; first URL segment was slideshow revision, not slide id.
        $file = $fs->get_file($context->id, 'mod_slideshow', 'content', 0, $filepath, $filename);
    }
    if (!$file || $file->is_directory()) {
        send_header_404();
        die;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Return a list of slideshow types
 * @param string $slideshowtype current slideshow type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function slideshow_slideshow_type_list($slideshowtype, $parentcontext, $currentcontext) {
    $moduleslideshowtype = ['mod-slideshow-*' => get_string('slideshow-mod-slideshow-x', 'slideshow')];
    return $moduleslideshowtype;
}

/**
 * Export slideshow resource contents.
 *
 * @param stdClass $cm Course module record.
 * @param string $baseurl Base URL for export (unused; kept for API compatibility).
 * @return array List of exported file content descriptors.
 */
function slideshow_export_contents($cm, $baseurl) {
    global $CFG, $DB;
    $contents = [];
    $context = context_module::instance($cm->id);

    $slideshow = $DB->get_record('slideshow', ['id' => $cm->instance], '*', MUST_EXIST);

    // Slideshow slide files from storage.
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_slideshow', 'content', false, 'itemid, filepath, filename', false);
    foreach ($files as $fileinfo) {
        if ($fileinfo->is_directory()) {
            continue;
        }
        $file = [];
        $file['type']         = 'file';
        $file['filename']     = $fileinfo->get_filename();
        $file['filepath']     = $fileinfo->get_filepath();
        $file['filesize']     = $fileinfo->get_filesize();
        $itemid = $fileinfo->get_itemid();
        $file['fileurl']      = file_encode_url(
            "$CFG->wwwroot/" . $baseurl,
            '/' . $context->id . '/mod_slideshow/content/' . $itemid . $fileinfo->get_filepath() . $fileinfo->get_filename(),
            true
        );
        $file['timecreated']  = $fileinfo->get_timecreated();
        $file['timemodified'] = $fileinfo->get_timemodified();
        $file['sortorder']    = $fileinfo->get_sortorder();
        $file['userid']       = $fileinfo->get_userid();
        $file['author']       = $fileinfo->get_author();
        $file['license']      = $fileinfo->get_license();
        $file['mimetype']     = $fileinfo->get_mimetype();
        $file['isexternalfile'] = $fileinfo->is_external_file();
        if ($file['isexternalfile']) {
            $file['repositorytype'] = $fileinfo->get_repository_type();
        }
        $contents[] = $file;
    }

    // Synthetic index.html entry for packaged export.
    $filename = 'index.html';
    $slideshowfile = [];
    $slideshowfile['type']         = 'file';
    $slideshowfile['filename']     = $filename;
    $slideshowfile['filepath']     = '/';
    $slideshowfile['filesize']     = 0;
    $slideshowfile['fileurl'] = file_encode_url(
        "$CFG->wwwroot/" . $baseurl,
        '/' . $context->id . '/mod_slideshow/content/' . $filename,
        true
    );
    $slideshowfile['timecreated']  = null;
    $slideshowfile['timemodified'] = $slideshow->timemodified;
    // Mark this row as the main file in the export list.
    $slideshowfile['sortorder']    = 1;
    $slideshowfile['userid']       = null;
    $slideshowfile['author']       = null;
    $slideshowfile['license']      = null;
    $contents[] = $slideshowfile;

    return $contents;
}

/**
 * Register the ability to handle drag and drop file uploads
 * @return array containing details of the files / types the mod can handle
 */
function slideshow_dndupload_register() {
    return ['types' => [
                     ['identifier' => 'text/html', 'message' => get_string('createslideshow', 'slideshow')],
                     ['identifier' => 'text', 'message' => get_string('createslideshow', 'slideshow')],
                 ]];
}

/**
 * Handle a file that has been uploaded
 * @param object $uploadinfo details of the file / content that has been uploaded
 * @return int instance id of the newly created mod
 */
function slideshow_dndupload_handle($uploadinfo) {
    // Gather the required info.
    $data = new stdClass();
    $data->course = $uploadinfo->course->id;
    $data->name = $uploadinfo->displayname;
    $data->intro = '<p>' . $uploadinfo->displayname . '</p>';
    $data->introformat = FORMAT_HTML;
    if ($uploadinfo->type == 'text/html') {
        $data->contentformat = FORMAT_HTML;
        $data->content = clean_param($uploadinfo->content, PARAM_CLEANHTML);
    } else {
        $data->contentformat = FORMAT_PLAIN;
        $data->content = clean_param($uploadinfo->content, PARAM_TEXT);
    }
    $data->coursemodule = $uploadinfo->coursemodule;

    // Set the display options to the site defaults.
    $config = get_config('slideshow');
    $data->display = $config->display;
    $data->popupheight = $config->popupheight;
    $data->popupwidth = $config->popupwidth;
    $data->printintro = $config->printintro;
    $data->printlastmodified = $config->printlastmodified;

    return slideshow_add_instance($data, null);
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $slideshow       slideshow object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function slideshow_view($slideshow, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $slideshow->id,
    ];

    $event = \mod_slideshow\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('slideshow', $slideshow);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function slideshow_check_updates_since(cm_info $cm, $from, $filter = []) {
    $updates = course_check_module_updates_since($cm, $from, ['content'], $filter);
    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event The calendar event.
 * @param \core_calendar\action_factory $factory Factory for building the action.
 * @param int $userid User id to check completion for (0 = current user).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_slideshow_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory,
    $userid = 0
) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['slideshow'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/slideshow/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Copy embedded files from legacy shared itemid 0 into each slide's itemid (slideshow_slide.id).
 *
 * Used once on upgrade so existing slides keep working when URLs use per-slide itemids.
 */
function slideshow_upgrade_migrate_slide_content_files() {
    global $DB;

    $fs = get_file_storage();
    $slideshowmoduleid = $DB->get_field('modules', 'id', ['name' => 'slideshow'], IGNORE_MISSING);
    $slides = $DB->get_recordset('slideshow_slide', null, '', 'id, slideshow, content');
    foreach ($slides as $slide) {
        if ((string) $slide->content === '') {
            continue;
        }
        $context = null;
        if ($slideshowmoduleid) {
            $cmrow = $DB->get_record('course_modules', [
                'id' => $slide->slideshow,
                'module' => $slideshowmoduleid,
            ], 'id', IGNORE_MISSING);
            if ($cmrow) {
                $context = context_module::instance((int) $cmrow->id, IGNORE_MISSING);
            }
        }
        if (!$context) {
            $cm = get_coursemodule_from_instance('slideshow', (int) $slide->slideshow, 0, false, IGNORE_MISSING);
            if ($cm) {
                $context = context_module::instance($cm->id, IGNORE_MISSING);
            }
        }
        if (!$context) {
            continue;
        }
        $contextid = $context->id;
        $filerefs = [];
        if (preg_match_all('#@@PLUGINFILE@@/([^"\'\s<>]+)#', $slide->content, $matches)) {
            foreach ($matches[1] as $encodedpath) {
                $rel = urldecode($encodedpath);
                $rel = trim($rel, '/');
                if ($rel === '') {
                    continue;
                }
                if (strpos($rel, '/') !== false) {
                    $filename = basename($rel);
                    $dir = dirname($rel);
                    $filepath = ($dir === '.' || $dir === '') ? '/' : '/' . str_replace('\\', '/', $dir) . '/';
                } else {
                    $filepath = '/';
                    $filename = $rel;
                }
                if ($filename === '' || $filename === '.') {
                    continue;
                }
                $key = $filepath . '|' . $filename;
                $filerefs[$key] = [$filepath, $filename];
            }
        }
        foreach ($filerefs as $ref) {
            [$filepath, $filename] = $ref;
            if ($fs->file_exists($contextid, 'mod_slideshow', 'content', $slide->id, $filepath, $filename)) {
                continue;
            }
            $source = $fs->get_file($contextid, 'mod_slideshow', 'content', 0, $filepath, $filename);
            if (!$source || $source->is_directory()) {
                continue;
            }
            $fs->create_file_from_storedfile([
                'contextid' => $contextid,
                'component' => 'mod_slideshow',
                'filearea' => 'content',
                'itemid' => $slide->id,
                'filepath' => $filepath,
                'filename' => $filename,
                'userid' => $source->get_userid(),
            ], $source);
        }
    }
    $slides->close();
}

/**
 * Given an array with a file path, it returns the itemid and the filepath for the defined filearea.
 *
 * @param  string $filearea The filearea.
 * @param  array  $args The path (the part after the filearea and before the filename).
 * @return array The itemid and the filepath inside the $args path, for the defined filearea.
 */
function mod_slideshow_get_path_from_pluginfile(string $filearea, array $args): array {
    if (empty($args)) {
        return [
            'itemid' => 0,
            'filepath' => '/',
        ];
    }
    $itemid = (int) array_shift($args);
    $filepath = empty($args) ? '/' : '/' . implode('/', $args) . '/';

    return [
        'itemid' => $itemid,
        'filepath' => $filepath,
    ];
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param settings_navigation $settings navigation_node object.
 * @param navigation_node $slidesnode navigation_node object.
 * @return void
 */
function slideshow_extend_settings_navigation(settings_navigation $settings, navigation_node $slidesnode): void {
    if (has_capability('mod/slideshow:viewslides', $settings->get_page()->cm->context)) {
        $url = new moodle_url('/mod/slideshow/slides.php', ['id' => $settings->get_page()->cm->id]);
        $slidesnode->add(get_string("slides", "slideshow"), $url, navigation_node::TYPE_CUSTOM, null, 'slideshowslides');
    }
}

/**
 * Add play or edit icons to the context header on slideshow pages.
 *
 * @param \moodle_page $page Current page.
 * @return string HTML fragment.
 */
function slideshow_add_button_to_context_header($page) {
    global $OUTPUT;

    $target = '/mod/slideshow/slides.php';
    $icons = '';

    if ($page->cm->modname !== 'slideshow') {
        return $icons;
    }

    // Detect slide management page to show play instead of edit.
    $url = $_SERVER['PHP_SELF'];
    if (str_contains($url, $target)) {
        if (has_capability('mod/slideshow:view', $page->cm->context)) {
            $playstring = get_string('start', 'slideshow');
            $playurl = new moodle_url('/mod/slideshow/view.php', ['id' => $page->cm->id]);
            $playicon = $OUTPUT->pix_icon('t/go', $playstring, 'core', ['class' => 'icon']);
            $icons .= html_writer::link(
                $playurl,
                $playicon,
                [
                    'style' => 'padding: 10px 12px;',
                    'class' => 'btn btn-secondary play-button',
                    'title' => $playstring,
                ]
            );
        }

        return $icons;
    }

    if (has_capability('mod/slideshow:viewslides', $page->cm->context)) {
        $editstring = get_string('slides', 'slideshow');
        $editurl = new moodle_url($target, ['id' => $page->cm->id]);
        $editimage = $OUTPUT->image_url('monologo', 'slideshow');
        $editicon = html_writer::img(
            $editimage,
            $editstring,
            [
                'class' => 'icon',
                'alt' => $editstring,
                'style' => 'height: 26px; width: auto; margin: 0;',
            ]
        );
        $icons .= html_writer::link(
            $editurl,
            $editicon,
            [
                'style' => 'padding: 8px 10px;',
                'class' => 'btn btn-secondary edit-button',
                'title' => $editstring,
            ]
        );
    }

    return $icons;
}

/**
 * Add play or edit actions to the activity navigation menu.
 *
 * @param \moodle_page $page Current page.
 * @return array List of action link data for the activity menu.
 */
function slideshow_add_button_to_activity_menu($page) {
    global $OUTPUT;

    $actions = [];

    if ($page->cm->modname !== 'slideshow') {
        return $actions;
    }

    $currentpath = $page->url->get_path();
    $target = '/mod/slideshow/slides.php';

    $context = $page->cm->context;

    // Detect slide management page to offer play in the activity menu.
    if (str_contains($currentpath, $target)) {
        if (has_capability('mod/slideshow:view', $context)) {
            $text = get_string('start', 'slideshow');

            $actions[] = [
                'url' => new moodle_url('/mod/slideshow/view.php', ['id' => $page->cm->id]),
                'icon' => $OUTPUT->pix_icon('t/go', $text, 'core', ['class' => 'icon']),
                'params' => ['class' => 'btn btn-secondary play-button', 'title' => $text, 'style' => 'padding: 12px 13px;'],
            ];
        }
    } else if (has_capability('mod/slideshow:viewslides', $context)) {
        $text = get_string('slides', 'slideshow');

        $image = $OUTPUT->image_url('monologo', 'slideshow');
        $icon = html_writer::img(
            $image,
            $text,
            [
                'class' => 'icon',
                'alt' => $text,
                'style' => 'height: 26px; width: auto; margin: 0;',
            ]
        );

        $actions[] = [
            'url' => new moodle_url($target, ['id' => $page->cm->id]),
            'icon' => $icon,
            'params' => ['class' => 'btn btn-secondary edit-button', 'title' => $text, 'style' => 'padding: 10px 12px;'],
        ];
    }

    return $actions;
}

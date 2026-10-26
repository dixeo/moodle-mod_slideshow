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
 * External service to save the slide open in the player modal.
 *
 * @package    mod_slideshow
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Save the title, layout, and image from the player edit modal.
 */
class save_slide extends external_api {
    /**
     * Parameters for saving a slide from the player modal.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'slideid' => new external_value(PARAM_INT, 'Slide id'),
            'name' => new external_value(PARAM_RAW, 'Slide title'),
            'layout' => new external_value(PARAM_ALPHA, 'Slide layout'),
            'image' => new external_value(PARAM_INT, 'Image draft item id', VALUE_DEFAULT, 0),
            'hascontent' => new external_value(PARAM_BOOL, 'Whether the Moodle editor was submitted', VALUE_DEFAULT, false),
            'content' => new external_value(PARAM_RAW, 'Slide body', VALUE_DEFAULT, ''),
            'contentformat' => new external_value(PARAM_INT, 'Slide body format', VALUE_DEFAULT, FORMAT_HTML),
            'contentitemid' => new external_value(PARAM_INT, 'Slide body draft item id', VALUE_DEFAULT, 0),
            'usedixeo' => new external_value(PARAM_BOOL, 'Whether the Dixeo editor was submitted', VALUE_DEFAULT, false),
            'dixeotext' => new external_value(PARAM_RAW, 'Dixeo editor body', VALUE_DEFAULT, ''),
            'dixeoformat' => new external_value(PARAM_INT, 'Dixeo editor format', VALUE_DEFAULT, FORMAT_HTML),
            'dixeoitemid' => new external_value(PARAM_INT, 'Dixeo editor draft item id', VALUE_DEFAULT, 0),
            'editorsessionid' => new external_value(PARAM_INT, 'Dixeo editor session id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save a slide the user is allowed to edit from the player.
     *
     * @param int $cmid Course module id
     * @param int $slideid Slide id
     * @param string $name Slide title
     * @param string $layout Slide layout
     * @param int $image Image draft item id
     * @param bool $hascontent Whether the Moodle editor was submitted
     * @param string $content Slide body
     * @param int $contentformat Slide body format
     * @param int $contentitemid Slide body draft item id
     * @param bool $usedixeo Whether the Dixeo editor was submitted
     * @param string $dixeotext Dixeo editor body
     * @param int $dixeoformat Dixeo editor format
     * @param int $dixeoitemid Dixeo editor draft item id
     * @param int $editorsessionid Dixeo editor session id
     * @return array Rendered slide markup and field errors
     */
    public static function execute(
        int $cmid,
        int $slideid,
        string $name,
        string $layout,
        int $image = 0,
        bool $hascontent = false,
        string $content = '',
        int $contentformat = FORMAT_HTML,
        int $contentitemid = 0,
        bool $usedixeo = false,
        string $dixeotext = '',
        int $dixeoformat = FORMAT_HTML,
        int $dixeoitemid = 0,
        int $editorsessionid = 0
    ): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'slideid' => $slideid,
            'name' => $name,
            'layout' => $layout,
            'image' => $image,
            'hascontent' => $hascontent,
            'content' => $content,
            'contentformat' => $contentformat,
            'contentitemid' => $contentitemid,
            'usedixeo' => $usedixeo,
            'dixeotext' => $dixeotext,
            'dixeoformat' => $dixeoformat,
            'dixeoitemid' => $dixeoitemid,
            'editorsessionid' => $editorsessionid,
        ]);
        [$slide, $context] = self::require_view_slide($params['cmid'], $params['slideid']);
        if (!empty($params['usedixeo']) && slideshow_quick_edit_uses_moodle_editor($context)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'local/dixeo:edit');
        }

        $savedata = (object) [
            'name' => self::clean_name($params['name']),
            'layout' => $params['layout'],
            'image' => $params['image'],
        ];
        if (!empty($params['hascontent'])) {
            $savedata->content_editor = [
                'text' => (string) $params['content'],
                'format' => (int) $params['contentformat'],
                'itemid' => (int) $params['contentitemid'],
            ];
        }
        if (!empty($params['usedixeo'])) {
            $savedata->dixeo = true;
            $savedata->dixeo_text = (string) $params['dixeotext'];
            $savedata->dixeo_format = (int) $params['dixeoformat'];
            $savedata->dixeo_itemid = (int) $params['dixeoitemid'];
            $savedata->editorsessionid = (int) $params['editorsessionid'];
        }

        $result = slideshow_save_quick_edit($slide, $context, $savedata);

        return self::format_result($result);
    }

    /**
     * Return values for a modal save.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered slide markup'),
            'errors' => new external_multiple_structure(
                new external_single_structure([
                    'field' => new external_value(PARAM_ALPHANUMEXT, 'Field name'),
                    'message' => new external_value(PARAM_RAW, 'Error message'),
                ])
            ),
        ]);
    }

    /**
     * Require login and mod/slideshow:viewslides, and load the slide for this module.
     *
     * @param int $cmid Course module id
     * @param int $slideid Slide id
     * @return array{0: \stdClass, 1: \context_module} Slide and module context
     */
    protected static function require_view_slide(int $cmid, int $slideid): array {
        global $DB;

        $cm = get_coursemodule_from_id('slideshow', $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        require_login($course, false, $cm);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/slideshow:viewslides', $context);

        $slide = $DB->get_record('slideshow_slide', [
            'id' => $slideid,
            'slideshow' => $cm->instance,
        ], '*', MUST_EXIST);

        return [$slide, $context];
    }

    /**
     * Clean a slide title the same way the slide form does.
     *
     * @param string $name Submitted title
     * @return string
     */
    protected static function clean_name(string $name): string {
        global $CFG;

        if (!empty($CFG->formatstringstriptags)) {
            return clean_param($name, PARAM_TEXT);
        }

        return clean_param($name, PARAM_CLEANHTML);
    }

    /**
     * Turn the save result into the external return structure.
     *
     * @param array $result Result from slideshow_save_quick_edit
     * @return array
     */
    protected static function format_result(array $result): array {
        $errors = [];
        foreach ($result['errors'] as $field => $message) {
            $errors[] = [
                'field' => (string) $field,
                'message' => (string) $message,
            ];
        }

        return [
            'html' => (string) ($result['html'] ?? ''),
            'errors' => $errors,
        ];
    }
}

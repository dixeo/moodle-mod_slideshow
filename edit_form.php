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
 * Slide configuration form
 *
 * @package     mod_slideshow
 * @copyright   2024 Dixeo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/slideshow/locallib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Form used when creating or editing one slideshow slide.
 */
class mod_slideshow_slide_edit_form extends moodleform {
    /**
     * Define form fields.
     */
    protected function definition() {
        global $CFG, $OUTPUT;

        $mform = $this->_form;
        $customdata = $this->_customdata;
        $context = $customdata['context'];
        $cm = $customdata['cm'];

        $mform->addElement('header', 'contentsection', get_string('contentheader', 'slideshow'));
        $mform->addElement('text', 'name', get_string('slidetitle', 'slideshow'), ['size' => '48']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $layouts = [];
        foreach (slideshow_get_layouts() as $value => $stringkey) {
            $thumb = html_writer::empty_tag('img', [
                'src' => $OUTPUT->image_url('layout_' . $value, 'slideshow')->out(false),
                'alt' => '',
                'class' => 'slideshow-layout-thumb',
            ]);
            $label = html_writer::div(
                $thumb . html_writer::div(get_string($stringkey, 'slideshow'), 'slideshow-layout-name'),
                'slideshow-layout-choice'
            );
            $layouts[] = $mform->createElement('radio', 'layout', '', $label, $value);
        }
        $mform->addGroup(
            $layouts,
            'layoutgroup',
            get_string('layout', 'slideshow'),
            '',
            false,
            ['class' => 'slideshow-layout-group']
        );
        $mform->setDefault('layout', MOD_SLIDESHOW_LAYOUT_BACKGROUND);

        $mform->addElement(
            'filepicker',
            'image',
            get_string('image', 'slideshow'),
            null,
            slideshow_get_image_file_options()
        );
        $mform->hideIf('image', 'layout', 'eq', MOD_SLIDESHOW_LAYOUT_TITLEONLY);

        $mform->addElement(
            'editor',
            'content_editor',
            get_string('content', 'slideshow'),
            null,
            slideshow_get_editor_options($context)
        );
        $mform->hideIf('content_editor', 'layout', 'eq', MOD_SLIDESHOW_LAYOUT_TITLEONLY);

        $this->add_action_buttons();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'slideshow');
        $mform->setType('slideshow', PARAM_INT);
        $mform->setDefault('slideshow', $cm->instance);

        $mform->addElement('hidden', 'hidden');
        $mform->setType('hidden', PARAM_INT);
        $mform->setDefault('hidden', 0);
    }

    /**
     * Require slide content unless the layout is title only, and reject unknown layouts.
     *
     * @param array $data Submitted form data.
     * @param array $files Uploaded files.
     * @return array Field name => error message.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return array_merge($errors, slideshow_validate_slide_submission($data));
    }
}

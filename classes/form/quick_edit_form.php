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
 * Compact slide form shown in the player edit modal.
 *
 * @package    mod_slideshow
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Title, layout, and image fields for the slide open in the player.
 */
class quick_edit_form extends \moodleform {
    /**
     * Define the title, layout thumbnails, and image file manager.
     */
    protected function definition() {
        global $CFG, $OUTPUT;

        $mform = $this->_form;
        $cm = $this->_customdata['cm'];

        $mform->addElement('text', 'name', get_string('slidetitle', 'slideshow'), ['size' => '48']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('advcheckbox', 'hidetitle', '', get_string('hidetitle', 'slideshow'));
        $mform->setType('hidetitle', PARAM_BOOL);
        $mform->setDefault('hidetitle', 0);
        $mform->hideIf('hidetitle', 'layout', 'eq', MOD_SLIDESHOW_LAYOUT_TITLEONLY);

        $layouts = [];
        foreach (slideshow_get_layouts() as $value => $stringkey) {
            $label = get_string($stringkey, 'slideshow');
            $thumb = \html_writer::empty_tag('img', [
                'src' => $OUTPUT->image_url('layout_' . $value, 'slideshow')->out(false),
                'alt' => $label,
                'title' => $label,
                'class' => 'slideshow-layout-thumb',
            ]);
            $choice = \html_writer::div($thumb, 'slideshow-layout-choice');
            $layouts[] = $mform->createElement('radio', 'layout', '', $choice, $value);
        }
        $mform->addGroup(
            $layouts,
            'layoutgroup',
            get_string('layout', 'slideshow'),
            '',
            false,
            ['class' => 'slideshow-layout-group slideshow-layout-group-compact']
        );
        $mform->setDefault('layout', MOD_SLIDESHOW_LAYOUT_BACKGROUND);

        $mform->addElement(
            'filemanager',
            'image',
            get_string('image', 'slideshow'),
            null,
            slideshow_get_image_file_options()
        );
        $mform->hideIf('image', 'layout', 'eq', MOD_SLIDESHOW_LAYOUT_TITLEONLY);

        if (!empty($this->_customdata['moodleeditor'])) {
            $context = $this->_customdata['context'];
            $mform->addElement(
                'editor',
                'content_editor',
                get_string('content', 'slideshow'),
                ['rows' => 16],
                slideshow_get_editor_options($context)
            );
            $mform->hideIf('content_editor', 'layout', 'eq', MOD_SLIDESHOW_LAYOUT_TITLEONLY);
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', $cm->id);
    }
}

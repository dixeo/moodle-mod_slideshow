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
 * The mod_slideshow slide updated event.
 *
 * @package    mod_slideshow
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_slideshow slide updated event class.
 *
 * @package    mod_slideshow
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slide_updated extends \core\event\base {

    /**
     * Create an instance of this event.
     *
     * @param \stdClass $slideshow Slideshow activity record.
     * @param \context_module $context Module context.
     * @param \stdClass $slide Slideshow slide record.
     * @return slide_updated
     */
    public static function create_from_slide(\stdClass $slideshow, \context_module $context, \stdClass $slide) {
        $data = [
            'context' => $context,
            'objectid' => $slide->id,
        ];
        /** @var slide_updated $event */
        $event = self::create($data);
        $event->add_record_snapshot('slideshow', $slideshow);
        $event->add_record_snapshot('slideshow_slide', $slide);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' updated the slide with id '{$this->objectid}' for the slideshow with " .
            "course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventslideupdated', 'mod_slideshow');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/slideshow/slides.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'slideshow_slide';
    }

    /**
     * Map object ids for backup and restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'slideshow_slide', 'restore' => 'slideshow_slide'];
    }
}

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
 * Shared access checks for slideshow slide mutations.
 *
 * @package    mod_slideshow
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow\external;

/**
 * Loads a slide and requires mod/slideshow:manageslides in its module context.
 */
trait manage_slide {
    /**
     * Require login, module access, and the manage-slides capability.
     *
     * @param int $slideid Slide id
     * @return array{0: \stdClass, 1: \stdClass, 2: \context_module} Slide, slideshow, and module context
     */
    protected static function require_manage_slide(int $slideid): array {
        global $DB;

        $slide = $DB->get_record('slideshow_slide', ['id' => $slideid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('slideshow', $slide->slideshow, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $slideshow = $DB->get_record('slideshow', ['id' => $slide->slideshow], '*', MUST_EXIST);

        require_login($course, false, $cm);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/slideshow:manageslides', $context);

        return [$slide, $slideshow, $context];
    }
}

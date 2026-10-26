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
 * External service definitions for the slideshow module.
 *
 * @package    mod_slideshow
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_slideshow_reorder_slide' => [
        'classname' => 'mod_slideshow\external\reorder_slide',
        'methodname' => 'execute',
        'description' => 'Reorder a slideshow slide',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/slideshow:manageslides',
    ],
    'mod_slideshow_set_slide_visibility' => [
        'classname' => 'mod_slideshow\external\set_slide_visibility',
        'methodname' => 'execute',
        'description' => 'Show or hide a slideshow slide',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/slideshow:manageslides',
    ],
    'mod_slideshow_delete_slide' => [
        'classname' => 'mod_slideshow\external\delete_slide',
        'methodname' => 'execute',
        'description' => 'Delete a slideshow slide',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/slideshow:manageslides',
    ],
    'mod_slideshow_save_slide' => [
        'classname' => 'mod_slideshow\external\save_slide',
        'methodname' => 'execute',
        'description' => 'Save the slide open in the player modal',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/slideshow:viewslides',
    ],
];

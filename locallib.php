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
 * Private slideshow module utility functions
 *
 * @package mod_slideshow
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/filelib.php");
require_once("$CFG->libdir/resourcelib.php");
require_once("$CFG->dirroot/mod/slideshow/lib.php");


/**
 * File browsing support class for slide embedded files.
 */
class slideshow_content_file_info extends file_info_stored {
    /**
     * Parent folder in the file browser tree.
     *
     * @return file_info|null
     */
    public function get_parent() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->browser->get_file_info($this->context);
        }
        return parent::get_parent();
    }

    /**
     * Visible label for this file or folder.
     *
     * @return string
     */
    public function get_visible_name() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->topvisiblename;
        }
        return parent::get_visible_name();
    }
}

/**
 * Atto editor options for slide HTML content.
 *
 * @param \context $context Module context.
 * @return array
 */
function slideshow_get_editor_options($context) {
    global $CFG;
    return [
        'subdirs' => 1,
        'maxbytes' => $CFG->maxbytes,
        'maxfiles' => -1,
        'changeformat' => 1,
        'context' => $context,
        'noclean' => 1,
        'trusttext' => 0,
    ];
}

/**
 * Normalise slide HTML so unmatched closing tags cannot break ancestors (slideshow wrapper, watermark, controls).
 *
 * Parsed inside a single synthetic root; libxml repairs typical editor/paste damage (for example stray closing div tags).
 * Does not change Moodle's trust model relative to format_text with noclean — content is still author-supplied.
 *
 * @param string $html HTML fragment (e.g. output of format_text).
 * @param int $slideid Slide row id (used for a stable wrapper id while parsing).
 * @return string Normalised HTML, or original string if parsing fails.
 */
function slideshow_balance_slide_html(string $html, int $slideid): string {
    if (trim($html) === '') {
        return $html;
    }

    $wrapperid = 'slideshow-slide-frag-' . $slideid;
    $wrapped = '<div id="' . $wrapperid . '">' . $html . '</div>';

    $doc = new \DOMDocument();
    $useerrors = libxml_use_internal_errors(true);
    libxml_clear_errors();
    // Repair fragment; suppress libxml warnings for malformed legacy content.
    @$doc->loadHTML(
        '<?xml encoding="UTF-8"?>' . $wrapped,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($useerrors);

    $root = $doc->getElementById($wrapperid);
    if ($root === null) {
        $doc2 = new \DOMDocument();
        $useerrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        @$doc2->loadHTML('<?xml encoding="UTF-8"?>' . $wrapped);
        libxml_clear_errors();
        libxml_use_internal_errors($useerrors);
        $xpath = new \DOMXPath($doc2);
        $nodes = $xpath->query('//*[@id="' . $wrapperid . '"]');
        if ($nodes !== false && $nodes->length > 0) {
            $root = $nodes->item(0);
            $doc = $doc2;
        }
    }

    if ($root === null) {
        return $html;
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

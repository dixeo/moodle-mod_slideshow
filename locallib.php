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

global $CFG;

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
    ];
}

/**
 * Sanitize slide HTML for storage (defense in depth; same policy as display cleaning).
 *
 * @param string $content Raw slide HTML.
 * @param int $format Moodle content format.
 * @return string Cleaned HTML safe for storage.
 */
function slideshow_sanitize_slide_content(string $content, int $format = FORMAT_HTML): string {
    if ($content === '') {
        return '';
    }

    return clean_text($content, $format);
}

/**
 * Whether the current user may load embedded files for a slide via pluginfile.
 *
 * @param stdClass|null $slide slideshow_slide row with a hidden field, or null for legacy shared itemid 0.
 * @param \context $context Module context.
 * @return bool
 */
function slideshow_user_can_view_slide_files(?stdClass $slide, \context $context): bool {
    if ($slide === null) {
        return has_capability('mod/slideshow:manageslides', $context);
    }
    if (empty($slide->hidden)) {
        return true;
    }
    return has_capability('mod/slideshow:viewslides', $context);
}

/**
 * Build a slideshow_slide row for insert or update from validated form fields.
 *
 * Only whitelisted columns are copied; the owning slideshow id is always taken from the course module.
 *
 * @param stdClass $fromform Form data with name, hidden, and id when updating.
 * @param int $slideshowid Owning slideshow instance id ($cm->instance).
 * @param bool $isnewslide True when creating a new slide.
 * @param int $sortorder Sort order for new slides only.
 * @return stdClass Record ready for $DB->insert_record or $DB->update_record.
 */
function slideshow_prepare_slide_save_record(
    stdClass $fromform,
    int $slideshowid,
    bool $isnewslide,
    int $sortorder = 0
): stdClass {
    $record = new stdClass();
    $record->name = $fromform->name;
    $record->hidden = (int) $fromform->hidden;
    $record->slideshow = $slideshowid;
    $record->timemodified = time();

    if ($isnewslide) {
        $record->sortorder = $sortorder;
        $record->content = '';
        $record->contentformat = FORMAT_HTML;
    } else {
        $record->id = (int) $fromform->id;
    }

    return $record;
}

/** @var int Default max length for slide titles on the slides management list. */
define('SLIDESHOW_SLIDE_LIST_LABEL_MAX', 80);

/**
 * Label for one slide on the slides management list (slides.php).
 *
 * Prefers the stored slide name (set by the slide form or AI generation), then the
 * first heading in the HTML content, then a short plain-text excerpt.
 *
 * @param stdClass $slide slideshow_slide row (name, content).
 * @param int $maxlength Maximum characters (multibyte-safe).
 * @return string Plain-text label for display.
 */
function slideshow_get_slide_list_name(stdClass $slide, int $maxlength = SLIDESHOW_SLIDE_LIST_LABEL_MAX): string {
    $storedname = trim((string) ($slide->name ?? ''));
    if ($storedname !== '') {
        return slideshow_truncate_slide_list_label(format_string($storedname), $maxlength);
    }

    $content = (string) ($slide->content ?? '');
    if (trim($content) === '') {
        return get_string('slideslistnotitle', 'mod_slideshow');
    }

    $heading = slideshow_extract_first_heading_text($content);
    if ($heading !== '') {
        return slideshow_truncate_slide_list_label($heading, $maxlength);
    }

    $excerpt = slideshow_plain_text_excerpt($content, $maxlength);
    if ($excerpt !== '') {
        return $excerpt;
    }

    return get_string('slideslistnotitle', 'mod_slideshow');
}

/**
 * Extract plain text from the first h1–h6 in HTML (document order).
 *
 * @param string $html Slide HTML.
 * @return string Heading text or empty string.
 */
function slideshow_extract_first_heading_text(string $html): string {
    if (trim($html) === '') {
        return '';
    }

    if (preg_match('/<h[1-6]\b[^>]*>(.*?)<\/h[1-6]>/is', $html, $matches)) {
        $text = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    return '';
}

/**
 * Plain-text excerpt from HTML for slide list labels.
 *
 * @param string $html Slide HTML.
 * @param int $maxlength Maximum characters.
 * @return string
 */
function slideshow_plain_text_excerpt(string $html, int $maxlength): string {
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($text === '') {
        return '';
    }
    return slideshow_truncate_slide_list_label($text, $maxlength);
}

/**
 * Truncate a slide list label with an ellipsis when needed.
 *
 * @param string $text Label text.
 * @param int $maxlength Maximum characters.
 * @return string
 */
function slideshow_truncate_slide_list_label(string $text, int $maxlength): string {
    if ($maxlength <= 0) {
        return '';
    }
    if (core_text::strlen($text) <= $maxlength) {
        return $text;
    }
    if ($maxlength === 1) {
        return '…';
    }
    return core_text::substr($text, 0, $maxlength - 1) . '…';
}

/**
 * Normalise slide HTML so unmatched closing tags cannot break ancestors (slideshow wrapper, watermark, controls).
 *
 * Parsed inside a single synthetic root; libxml repairs typical editor/paste damage (for example stray closing div tags).
 * Repairs structure only; slide HTML is cleaned by format_text before this runs.
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

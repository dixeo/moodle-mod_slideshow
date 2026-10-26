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
 * @copyright  2024 Dixeo
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
 * File picker options for the single slide image.
 *
 * @return array
 */
function slideshow_get_image_file_options(): array {
    global $CFG;
    return [
        'subdirs' => 0,
        'maxbytes' => $CFG->maxbytes,
        'maxfiles' => 1,
        'accepted_types' => ['web_image'],
    ];
}

/**
 * Whether submitted slide HTML counts as content.
 *
 * Plain text counts, and so does a body that only embeds media. Empty markup does not.
 *
 * @param array $data Submitted form values. content_editor may be a string or an editor array.
 * @return bool
 */
function slideshow_slide_content_is_present(array $data): bool {
    $editor = $data['content_editor'] ?? '';
    $raw = is_array($editor) ? (string) ($editor['text'] ?? '') : (string) $editor;
    $raw = trim($raw);
    if ($raw === '') {
        return false;
    }
    if (trim(strip_tags($raw)) !== '') {
        return true;
    }
    return (bool) preg_match('/<(img|video|audio|iframe|object|embed)\b/i', $raw);
}

/**
 * Server-side checks for the slide edit form.
 *
 * Content is required for every layout except title only. Unknown layouts are rejected.
 *
 * @param array $data Submitted form values.
 * @return array Field name => error message. Empty when the submission is valid.
 */
function slideshow_validate_slide_submission(array $data): array {
    $errors = [];
    $layout = $data['layout'] ?? '';
    if (!is_string($layout) || !isset(slideshow_get_layouts()[$layout])) {
        $errors['layoutgroup'] = get_string('layoutinvalid', 'slideshow');
    }
    if ($layout !== MOD_SLIDESHOW_LAYOUT_TITLEONLY && !slideshow_slide_content_is_present($data)) {
        $errors['content_editor'] = get_string('contentrequired', 'slideshow');
    }
    return $errors;
}

/**
 * Whether an existing slide opens in the Dixeo editor.
 *
 * local_dixeo_editor depends on local_dixeo. A missing editor plugin, a missing
 * settings class, or Dixeo off for this module keeps the Moodle slide form.
 *
 * @param context $context Module context.
 * @return bool
 */
function slideshow_use_dixeo_editor(context $context): bool {
    if (!\core_plugin_manager::instance()->get_plugin_info('local_dixeo_editor')) {
        return false;
    }
    if (!class_exists(\local_dixeo\external\service_factory::class)) {
        return false;
    }
    return \local_dixeo\external\service_factory::get_settings_config()->enabled_for($context);
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
 * @param stdClass $fromform Form data with name, layout, hidden, and id when updating.
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
    $record->layout = slideshow_normalize_layout($fromform->layout ?? null);
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

/** @var string Slide shows a centered title and no body or image. */
define('MOD_SLIDESHOW_LAYOUT_TITLEONLY', 'titleonly');

/** @var string Slide image fills the background, with the title and body on top. No image keeps the light slide. */
define('MOD_SLIDESHOW_LAYOUT_BACKGROUND', 'background');

/** @var string Title on top, image on the left, text on the right. */
define('MOD_SLIDESHOW_LAYOUT_CONTENTRIGHT', 'contentright');

/** @var string Title on top, text on the left, image on the right. */
define('MOD_SLIDESHOW_LAYOUT_CONTENTLEFT', 'contentleft');

/** @var int Default max length for slide titles on the slides management list. */
define('MOD_SLIDESHOW_SLIDE_LIST_LABEL_MAX', 80);

/**
 * Layout values the edit form and the player accept.
 *
 * @return array<string, string> Layout value => language string key in mod_slideshow.
 */
function slideshow_get_layouts(): array {
    return [
        MOD_SLIDESHOW_LAYOUT_TITLEONLY => 'layout_titleonly',
        MOD_SLIDESHOW_LAYOUT_BACKGROUND => 'layout_background',
        MOD_SLIDESHOW_LAYOUT_CONTENTRIGHT => 'layout_contentright',
        MOD_SLIDESHOW_LAYOUT_CONTENTLEFT => 'layout_contentleft',
    ];
}

/**
 * Return a known layout, or background when the value is missing or unknown.
 *
 * An empty image on that background keeps the title and body on the light slide,
 * which is how slides created before layouts, and slides created without one, are shown.
 *
 * @param string|null $layout Stored or submitted layout.
 * @return string One of the MOD_SLIDESHOW_LAYOUT_* values.
 */
function slideshow_normalize_layout(?string $layout): string {
    $layouts = slideshow_get_layouts();
    $layout = (string) $layout;
    if (isset($layouts[$layout])) {
        return $layout;
    }
    return MOD_SLIDESHOW_LAYOUT_BACKGROUND;
}

/**
 * Pluginfile URL for the slide image, when one file is stored.
 *
 * @param \context $context Module context.
 * @param int $slideid Slide id, used as the file itemid.
 * @param bool $thumbnail True to request Moodle's cached thumb preview instead of the original file.
 * @return string|null URL, or null when the slide has no image.
 */
function slideshow_get_slide_image_url(\context $context, int $slideid, bool $thumbnail = false): ?string {
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $context->id,
        'mod_slideshow',
        'image',
        $slideid,
        'filename',
        false
    );
    foreach ($files as $file) {
        if ($file->is_directory()) {
            continue;
        }
        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_slideshow',
            'image',
            $slideid,
            $file->get_filepath(),
            $file->get_filename()
        );
        if ($thumbnail) {
            return $url->out(false, ['preview' => 'thumb']);
        }
        return $url->out(false);
    }
    return null;
}

/**
 * Player markup for one slide.
 *
 * The title is plain text. Title only omits the body and the image. A background
 * image is a CSS background on an inner element so the player can set the slide
 * height without removing it. With no image the slide stays on the light background.
 *
 * @param stdClass $slide Slide row with name and layout.
 * @param string $contenthtml Already formatted slide body.
 * @param string|null $imageurl Slide image URL, or null when no file is stored.
 * @param bool $hidden True when the slide is not the one shown first.
 * @return string
 */
function slideshow_render_slide_html(
    stdClass $slide,
    string $contenthtml,
    ?string $imageurl,
    bool $hidden = false
): string {
    $layout = slideshow_normalize_layout($slide->layout ?? null);
    $usesimage = $layout === MOD_SLIDESHOW_LAYOUT_BACKGROUND
        || $layout === MOD_SLIDESHOW_LAYOUT_CONTENTLEFT
        || $layout === MOD_SLIDESHOW_LAYOUT_CONTENTRIGHT;
    $hasimage = $usesimage && $imageurl !== null && $imageurl !== '';

    $classes = 'slide no-overflow layout-' . $layout;
    if ($layout === MOD_SLIDESHOW_LAYOUT_BACKGROUND && $hasimage) {
        $classes .= ' has-image';
    }
    if ($hidden) {
        $classes .= ' hidden';
    }

    $title = html_writer::tag('h2', format_string($slide->name ?? ''), ['class' => 'slide-title']);
    $inner = $title;

    if ($layout !== MOD_SLIDESHOW_LAYOUT_TITLEONLY) {
        $body = html_writer::div($contenthtml, 'slide-body');
        if ($layout === MOD_SLIDESHOW_LAYOUT_CONTENTLEFT || $layout === MOD_SLIDESHOW_LAYOUT_CONTENTRIGHT) {
            $mediaattrs = ['aria-hidden' => 'true'];
            if ($hasimage) {
                $mediaattrs['style'] = 'background-image:url("' . $imageurl . '")';
            }
            $media = html_writer::div('', 'slide-media', $mediaattrs);
            $inner .= html_writer::div($media . $body, 'slide-split');
        } else {
            $inner .= $body;
        }
    }

    if ($layout === MOD_SLIDESHOW_LAYOUT_BACKGROUND && $hasimage) {
        $backdrop = html_writer::div('', 'slide-backdrop', [
            'style' => 'background-image:url("' . $imageurl . '")',
            'aria-hidden' => 'true',
        ]);
        $inner = $backdrop . html_writer::div($inner, 'slide-layout');
    } else {
        $inner = html_writer::div($inner, 'slide-layout');
    }

    return html_writer::div($inner, $classes, [
        'data-slideid' => (int) ($slide->id ?? 0),
        'data-layout' => $layout,
    ]);
}

/**
 * Player markup for one slide, including formatted body text and the slide image.
 *
 * @param stdClass $slide Slide row.
 * @param \context $context Module context.
 * @param bool $hidden True when this slide is not the one shown first.
 * @return string
 */
function slideshow_render_player_slide(stdClass $slide, \context $context, bool $hidden = false): string {
    $layout = slideshow_normalize_layout($slide->layout ?? null);
    $content = '';
    $imageurl = null;
    if ($layout !== MOD_SLIDESHOW_LAYOUT_TITLEONLY) {
        $content = file_rewrite_pluginfile_urls(
            (string) ($slide->content ?? ''),
            'pluginfile.php',
            $context->id,
            'mod_slideshow',
            'content',
            (int) $slide->id
        );
        $formatoptions = new stdClass();
        $formatoptions->overflowdiv = true;
        $formatoptions->context = $context;
        $content = format_text($content, (int) ($slide->contentformat ?? FORMAT_HTML), $formatoptions);
        $content = slideshow_balance_slide_html($content, (int) $slide->id);
    }
    if (
        $layout === MOD_SLIDESHOW_LAYOUT_BACKGROUND
        || $layout === MOD_SLIDESHOW_LAYOUT_CONTENTLEFT
        || $layout === MOD_SLIDESHOW_LAYOUT_CONTENTRIGHT
    ) {
        $imageurl = slideshow_get_slide_image_url($context, (int) $slide->id);
    }
    return slideshow_render_slide_html($slide, $content, $imageurl, $hidden);
}

/**
 * Save the title, layout, and image of an existing slide.
 *
 * The stored body is left unchanged. Title only does not replace the stored image.
 *
 * @param stdClass $slide Existing slideshow_slide row that belongs to this module.
 * @param \context $context Module context.
 * @param stdClass $data Submitted name, layout, and image draft item id.
 * @return array{errors: array<string, string>, html?: string} Field errors, or rendered slide HTML when saved.
 */
function slideshow_save_quick_edit(stdClass $slide, \context $context, stdClass $data): array {
    global $DB;

    require_capability('mod/slideshow:viewslides', $context);

    $errors = [];
    $name = trim((string) ($data->name ?? ''));
    if ($name === '') {
        $errors['name'] = get_string('required');
    } else if (\core_text::strlen($name) > 255) {
        $errors['name'] = get_string('maximumchars', '', 255);
    }

    $layout = $data->layout ?? '';
    if (!is_string($layout) || !isset(slideshow_get_layouts()[$layout])) {
        $errors['layoutgroup'] = get_string('layoutinvalid', 'slideshow');
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }

    $data->name = $name;
    $data->id = (int) $slide->id;
    $data->hidden = (int) $slide->hidden;
    $record = slideshow_prepare_slide_save_record($data, (int) $slide->slideshow, false);
    $DB->update_record('slideshow_slide', $record);

    if ($record->layout !== MOD_SLIDESHOW_LAYOUT_TITLEONLY) {
        file_save_draft_area_files(
            (int) ($data->image ?? 0),
            $context->id,
            'mod_slideshow',
            'image',
            (int) $slide->id,
            slideshow_get_image_file_options()
        );
    }

    $slideshow = $DB->get_record('slideshow', ['id' => $slide->slideshow], '*', MUST_EXIST);
    $updated = $DB->get_record('slideshow_slide', ['id' => $slide->id], '*', MUST_EXIST);
    $event = \mod_slideshow\event\slide_updated::create_from_slide($slideshow, $context, $updated);
    $event->trigger();

    return [
        'errors' => [],
        'html' => slideshow_render_player_slide($updated, $context, false),
    ];
}

/**
 * Miniature of one slide for the slides management list.
 *
 * Uses the player markup, scaled by CSS. An image is the cached thumb preview, not the original file.
 *
 * @param stdClass $slide Slide row.
 * @param \context $context Module context.
 * @return string
 */
function slideshow_get_slide_list_preview_html(stdClass $slide, \context $context): string {
    $layout = slideshow_normalize_layout($slide->layout ?? null);
    $content = '';
    $imageurl = null;
    if ($layout !== MOD_SLIDESHOW_LAYOUT_TITLEONLY) {
        $content = file_rewrite_pluginfile_urls(
            (string) ($slide->content ?? ''),
            'pluginfile.php',
            $context->id,
            'mod_slideshow',
            'content',
            (int) $slide->id
        );
        $formatoptions = new stdClass();
        $formatoptions->overflowdiv = false;
        $formatoptions->context = $context;
        $content = format_text($content, (int) ($slide->contentformat ?? FORMAT_HTML), $formatoptions);
        $content = slideshow_balance_slide_html($content, (int) $slide->id);
    }
    if (
        $layout === MOD_SLIDESHOW_LAYOUT_BACKGROUND
        || $layout === MOD_SLIDESHOW_LAYOUT_CONTENTLEFT
        || $layout === MOD_SLIDESHOW_LAYOUT_CONTENTRIGHT
    ) {
        $imageurl = slideshow_get_slide_image_url($context, (int) $slide->id, true);
    }

    $slidehtml = slideshow_render_slide_html($slide, $content, $imageurl, false);
    $scaled = html_writer::div($slidehtml, 'slideshow-container slide-list-thumb-scale');
    return html_writer::div($scaled, 'slide-list-thumb', ['aria-hidden' => 'true']);
}

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
function slideshow_get_slide_list_name(stdClass $slide, int $maxlength = MOD_SLIDESHOW_SLIDE_LIST_LABEL_MAX): string {
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

/**
 * Process a manageslides AJAX mutation.
 *
 * Mutation events are emitted only after a confirmed successful change.
 *
 * @param string $action One of reorder, delete, show, hide.
 * @param stdClass $slide Slide record being mutated.
 * @param stdClass $slideshow Parent slideshow instance.
 * @param context $context Module context.
 * @param int $oldorder Previous sort order for reorder.
 * @param int $neworder Target sort order for reorder.
 * @return array JSON-serializable response payload, or empty array for unknown actions.
 */
function slideshow_process_ajax_action(
    string $action,
    stdClass $slide,
    stdClass $slideshow,
    context $context,
    int $oldorder = 0,
    int $neworder = 0
): array {
    global $DB;

    $slideid = (int) $slide->id;

    switch ($action) {
        case 'reorder':
            $success = true;
            $transaction = $DB->start_delegated_transaction();

            // Update sort order values.
            $records = $DB->get_records('slideshow_slide', ['slideshow' => $slide->slideshow], 'sortorder');
            foreach ($records as $record) {
                if ($record->sortorder == $oldorder) {
                    $record->sortorder = $neworder;
                } else {
                    if ($neworder > $oldorder) {
                        if ($record->sortorder > $oldorder && $record->sortorder <= $neworder) {
                            $record->sortorder--;
                        }
                    } else {
                        if ($record->sortorder >= $neworder && $record->sortorder < $oldorder) {
                            $record->sortorder++;
                        }
                    }
                }
                if (!$DB->update_record('slideshow_slide', $record)) {
                    $success = false;
                    break;
                }
            }

            // Fix gaps in sortorder.
            if ($success) {
                $records = $DB->get_records('slideshow_slide', ['slideshow' => $slide->slideshow], 'sortorder');
                $sortorder = 0;
                foreach ($records as $record) {
                    $record->sortorder = $sortorder;
                    $sortorder++;
                    if (!$DB->update_record('slideshow_slide', $record)) {
                        $success = false;
                        break;
                    }
                }
            }

            if ($success) {
                $transaction->allow_commit();
                $movedslide = $DB->get_record('slideshow_slide', ['id' => $slideid], '*', MUST_EXIST);
                $event = \mod_slideshow\event\slides_reordered::create_from_slide($slideshow, $context, $movedslide);
                $event->trigger();
            }
            // Uncommitted delegated transaction rolls back on dispose.

            return [
                'slide' => $slideid,
                'result' => $success,
            ];

        case 'delete':
            // Keep a snapshot for the event; emit only after a confirmed delete.
            $slidesnapshot = clone $slide;
            $transaction = $DB->start_delegated_transaction();

            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_slideshow', 'content', $slideid);

            $deleted = $DB->delete_records('slideshow_slide', ['id' => $slideid]);

            // Renumber sort order after delete.
            $renumbered = false;
            if ($deleted) {
                $sql = "UPDATE {slideshow_slide} SET sortorder = sortorder -1
                           WHERE slideshow = ? AND sortorder > ?";
                $renumbered = $DB->execute($sql, [$slide->slideshow, $slide->sortorder]);
            }

            $success = $deleted && $renumbered;
            if ($success) {
                $transaction->allow_commit();
                $event = \mod_slideshow\event\slide_deleted::create_from_slide(
                    $slideshow,
                    $context,
                    $slidesnapshot
                );
                $event->trigger();
            }

            return [
                'slide' => $slideid,
                'result' => $success,
            ];

        case 'show':
        case 'hide':
            $slide->hidden = $action == 'hide' ? 1 : 0;
            $updated = $DB->update_record('slideshow_slide', $slide);

            if ($updated) {
                $event = \mod_slideshow\event\slide_visibility_updated::create_from_slide(
                    $slideshow,
                    $context,
                    $slide
                );
                $event->trigger();
            }

            return [
                'slide' => $slideid,
                'action' => $action,
                'result' => $updated,
            ];

        default:
            return [];
    }
}

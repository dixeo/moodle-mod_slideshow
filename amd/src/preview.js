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
 * Unsaved slide preview on the slide edit form.
 *
 * Title and content are copied into the preview when those fields blur.
 * Layout, the hide-title checkbox, and the image update as soon as they change.
 * Nothing here is submitted.
 *
 * The slide body is never inserted as raw HTML. It is sent to the mod_slideshow/preview
 * fragment, which cleans and formats it with format_text() in the module context.
 *
 * @module      mod_slideshow/preview
 * @copyright   2026 Dixeo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/fragment', 'core/templates'], function(Fragment, Templates) {

    /** @var {string} Layout that shows only the title. */
    const LAYOUT_TITLEONLY = 'titleonly';

    /** @var {string} Layout that uses the image as a cover background. */
    const LAYOUT_BACKGROUND = 'background';

    /**
     * Selected layout, or background when the form has none.
     *
     * @return {string}
     */
    const readLayout = () => {
        const selected = document.querySelector('#fgroup_id_layoutgroup input[name="layout"]:checked');
        if (!selected) {
            return LAYOUT_BACKGROUND;
        }
        return selected.value;
    };

    /**
     * Current title field value.
     *
     * @return {string}
     */
    const readTitle = () => {
        const input = document.getElementById('id_name');
        if (!input) {
            return '';
        }
        return input.value;
    };

    /**
     * Whether the hide-title checkbox is checked.
     *
     * Title only still shows the title. The checkbox is hidden for that layout.
     *
     * @return {boolean}
     */
    const readHideTitle = () => {
        const input = document.getElementById('id_hidetitle');
        if (!input) {
            return false;
        }
        return input.checked;
    };

    /**
     * Current editor HTML, from Tiny, Atto, or the textarea.
     *
     * @return {string}
     */
    const readContent = () => {
        const tiny = window.tinymce && window.tinymce.get('id_content_editor');
        if (tiny) {
            return tiny.getContent();
        }
        const atto = document.getElementById('id_content_editoreditable');
        if (atto) {
            return atto.innerHTML;
        }
        const textarea = document.getElementById('id_content_editor');
        if (!textarea) {
            return '';
        }
        return textarea.value;
    };

    /**
     * Current editor format (HTML, Moodle, plain text, Markdown).
     *
     * @return {string}
     */
    const readFormat = () => {
        const select = document.querySelector('[name="content_editor[format]"]');
        if (!select || !select.value) {
            return '1';
        }
        return String(select.value);
    };

    /**
     * Draft or plugin file URL for the slide image, when one is chosen.
     *
     * The file manager shows that file as a thumbnail. A file picker link is
     * used when the thumbnail has not been painted yet.
     *
     * @return {string}
     */
    const readImageUrl = () => {
        const images = document.querySelectorAll('#fitem_id_image img');
        let found = '';
        images.forEach((img) => {
            if (found !== '' || !img.src) {
                return;
            }
            if (img.src.indexOf('draftfile.php') !== -1 || img.src.indexOf('pluginfile.php') !== -1) {
                found = img.src;
            }
        });
        if (found !== '') {
            return found;
        }
        const link = document.querySelector('#fitem_id_image .filepicker-filename a[href]');
        if (!link) {
            return '';
        }
        return link.href;
    };

    /**
     * CSS background-image value for a file URL.
     *
     * @param {string} url
     * @return {string}
     */
    const backgroundImage = (url) => {
        const escaped = url.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        return 'url("' + escaped + '")';
    };

    /**
     * Whether the layout stacks or sits the image next to the text.
     *
     * @param {string} layout
     * @return {boolean}
     */
    const isSplitLayout = (layout) => {
        return layout === 'contentleft' || layout === 'contentright'
            || layout === 'contenttop' || layout === 'contentbottom';
    };

    /**
     * Whether this layout shows the image beside, above, or behind the text.
     *
     * @param {string} layout
     * @return {boolean}
     */
    const layoutUsesImage = (layout) => {
        return layout === LAYOUT_BACKGROUND || isSplitLayout(layout);
    };

    /** @var {number} Context id of the slideshow module, used for the preview fragment. */
    let previewContextId = 0;

    /** @var {number} Increases with every body request so a stale response is ignored. */
    let bodyRequest = 0;

    /** @var {{key: string, html: string}|null} Last server-rendered body. */
    let bodyCache = null;

    /**
     * Fill the preview body with server-cleaned HTML for the current content.
     *
     * @param {HTMLElement} body
     * @param {string} content
     * @param {string} format
     */
    const renderBody = (body, content, format) => {
        const key = format + '\u0000' + content;
        if (bodyCache && bodyCache.key === key) {
            Templates.replaceNodeContents(body, bodyCache.html, '');
            return;
        }
        if (content.trim() === '' || !previewContextId) {
            bodyCache = {key: key, html: ''};
            return;
        }
        bodyRequest = bodyRequest + 1;
        const request = bodyRequest;
        Fragment.loadFragment('mod_slideshow', 'preview', previewContextId, {
            content: content,
            format: format,
        }).then((html, js) => {
            if (request !== bodyRequest) {
                return;
            }
            bodyCache = {key: key, html: html};
            Templates.replaceNodeContents(body, html, js);
            return;
        }).catch(() => {
            // The preview is visual only. Leave the body empty when it cannot be rendered.
            return;
        });
    };

    /**
     * Build the preview DOM for the current form values.
     *
     * @param {HTMLElement} root
     * @param {{title: string, hideTitle: boolean, content: string, format: string, layout: string, imageUrl: string}} state
     */
    const render = (root, state) => {
        while (root.firstChild) {
            root.removeChild(root.firstChild);
        }

        const layout = state.layout;
        const showImage = layoutUsesImage(layout);
        const imageUrl = showImage ? state.imageUrl : '';
        const slide = document.createElement('div');
        slide.className = 'slide no-overflow layout-' + layout;
        if (layout === LAYOUT_BACKGROUND && imageUrl !== '') {
            slide.classList.add('has-image');
        }

        const layoutEl = document.createElement('div');
        layoutEl.className = 'slide-layout';
        if (layout === LAYOUT_TITLEONLY || !state.hideTitle) {
            const title = document.createElement('h2');
            title.className = 'slide-title';
            title.textContent = state.title;
            layoutEl.appendChild(title);
        }

        if (layout !== LAYOUT_TITLEONLY) {
            const body = document.createElement('div');
            body.className = 'slide-body';
            renderBody(body, state.content, state.format);
            if (isSplitLayout(layout)) {
                const media = document.createElement('div');
                media.className = 'slide-media';
                media.setAttribute('aria-hidden', 'true');
                if (imageUrl !== '') {
                    media.style.backgroundImage = backgroundImage(imageUrl);
                }
                const split = document.createElement('div');
                split.className = 'slide-split';
                split.appendChild(media);
                split.appendChild(body);
                layoutEl.appendChild(split);
            } else {
                layoutEl.appendChild(body);
            }
        }

        if (layout === LAYOUT_BACKGROUND && imageUrl !== '') {
            const backdrop = document.createElement('div');
            backdrop.className = 'slide-backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            backdrop.style.backgroundImage = backgroundImage(imageUrl);
            slide.appendChild(backdrop);
        }
        slide.appendChild(layoutEl);
        root.appendChild(slide);
    };

    return {
        /**
         * Start the preview for the slide edit form.
         *
         * @param {number} contextid Module context id for the preview fragment.
         */
        init: (contextid) => {
            const root = document.getElementById('slideshow-slide-preview');
            if (!root) {
                return;
            }
            previewContextId = Number(contextid) || 0;

            const state = {
                title: readTitle(),
                hideTitle: readHideTitle(),
                content: readContent(),
                format: readFormat(),
                layout: readLayout(),
                imageUrl: readImageUrl(),
            };
            const draw = () => {
                render(root, state);
            };

            const title = document.getElementById('id_name');
            if (title) {
                title.addEventListener('blur', () => {
                    state.title = readTitle();
                    draw();
                });
            }

            const hideTitle = document.getElementById('id_hidetitle');
            if (hideTitle) {
                hideTitle.addEventListener('change', () => {
                    state.hideTitle = readHideTitle();
                    draw();
                });
            }

            const layoutGroup = document.getElementById('fgroup_id_layoutgroup');
            if (layoutGroup) {
                layoutGroup.addEventListener('change', () => {
                    state.layout = readLayout();
                    state.imageUrl = readImageUrl();
                    draw();
                });
            }

            const formatSelect = document.querySelector('[name="content_editor[format]"]');
            if (formatSelect) {
                formatSelect.addEventListener('change', () => {
                    state.format = readFormat();
                    draw();
                });
            }

            const imageField = document.getElementById('fitem_id_image');
            if (imageField) {
                imageField.addEventListener('change', () => {
                    state.imageUrl = readImageUrl();
                    draw();
                });
                const observer = new MutationObserver(() => {
                    const next = readImageUrl();
                    if (next === state.imageUrl) {
                        return;
                    }
                    state.imageUrl = next;
                    draw();
                });
                observer.observe(imageField, {
                    attributes: true,
                    attributeFilter: ['src'],
                    childList: true,
                    subtree: true,
                });
            }

            let tinyBound = false;
            let attoBound = false;
            const bindContentBlur = () => {
                const tiny = window.tinymce && window.tinymce.get('id_content_editor');
                if (tiny && !tinyBound) {
                    tinyBound = true;
                    tiny.on('blur', () => {
                        state.content = tiny.getContent();
                        state.format = readFormat();
                        draw();
                    });
                }
                const atto = document.getElementById('id_content_editoreditable');
                if (atto && !attoBound) {
                    attoBound = true;
                    atto.addEventListener('blur', () => {
                        state.content = atto.innerHTML;
                        state.format = readFormat();
                        draw();
                    });
                }
                const textarea = document.getElementById('id_content_editor');
                if (textarea && !textarea.dataset.slideshowPreview) {
                    textarea.dataset.slideshowPreview = '1';
                    textarea.addEventListener('blur', () => {
                        if (tinyBound || attoBound) {
                            return;
                        }
                        state.content = textarea.value;
                        state.format = readFormat();
                        draw();
                    });
                }
            };

            bindContentBlur();
            let attempts = 0;
            const timer = window.setInterval(() => {
                attempts = attempts + 1;
                bindContentBlur();
                if (tinyBound || attoBound || attempts > 40) {
                    window.clearInterval(timer);
                }
            }, 250);

            draw();
        },
    };
});

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
 * Layout and image update as soon as they change. Nothing here is submitted.
 *
 * @module      mod_slideshow/preview
 * @copyright   2026 Dixeo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

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
     * Draft or plugin file URL for the slide image, when one is chosen.
     *
     * @return {string}
     */
    const readImageUrl = () => {
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
     * Whether this layout shows the image beside or behind the text.
     *
     * @param {string} layout
     * @return {boolean}
     */
    const layoutUsesImage = (layout) => {
        return layout === LAYOUT_BACKGROUND || layout === 'contentleft' || layout === 'contentright';
    };

    /**
     * Build the preview DOM for the current form values.
     *
     * @param {HTMLElement} root
     * @param {{title: string, content: string, layout: string, imageUrl: string}} state
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

        const title = document.createElement('h2');
        title.className = 'slide-title';
        title.textContent = state.title;

        const layoutEl = document.createElement('div');
        layoutEl.className = 'slide-layout';
        layoutEl.appendChild(title);

        if (layout !== LAYOUT_TITLEONLY) {
            const body = document.createElement('div');
            body.className = 'slide-body';
            body.innerHTML = state.content;
            if (layout === 'contentleft' || layout === 'contentright') {
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
         */
        init: () => {
            const root = document.getElementById('slideshow-slide-preview');
            if (!root) {
                return;
            }

            const state = {
                title: readTitle(),
                content: readContent(),
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

            const layoutGroup = document.getElementById('fgroup_id_layoutgroup');
            if (layoutGroup) {
                layoutGroup.addEventListener('change', () => {
                    state.layout = readLayout();
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
                observer.observe(imageField, {childList: true, subtree: true});
            }

            let tinyBound = false;
            let attoBound = false;
            const bindContentBlur = () => {
                const tiny = window.tinymce && window.tinymce.get('id_content_editor');
                if (tiny && !tinyBound) {
                    tinyBound = true;
                    tiny.on('blur', () => {
                        state.content = tiny.getContent();
                        draw();
                    });
                }
                const atto = document.getElementById('id_content_editoreditable');
                if (atto && !attoBound) {
                    attoBound = true;
                    atto.addEventListener('blur', () => {
                        state.content = atto.innerHTML;
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

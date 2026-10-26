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
 * Edit the current slide from the player.
 *
 * @module      mod_slideshow/quickedit
 * @copyright   2026 Dixeo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/ajax',
    'core/fragment',
    'core/modal_events',
    'core/modal_save_cancel',
    'core/notification',
    'core/str',
], function(Ajax, Fragment, ModalEvents, ModalSaveCancel, Notification, Str) {
    let saving = false;

    /**
     * Show server-side field errors inside the modal.
     *
     * @param {Object} modal Save/cancel modal
     * @param {Object} errors Field name to message
     */
    function showErrors(modal, errors) {
        const body = modal.getBody()[0];
        const form = body.querySelector('form');
        let box = body.querySelector('.slideshow-quick-edit-errors');
        if (!box) {
            box = document.createElement('div');
            box.className = 'alert alert-danger slideshow-quick-edit-errors';
            box.setAttribute('role', 'alert');
            form.prepend(box);
        }
        const messages = [];
        Object.keys(errors).forEach((key) => {
            messages.push(errors[key]);
        });
        box.textContent = messages.join(' ');
    }

    /**
     * Remove a previous error alert.
     *
     * @param {Object} modal Save/cancel modal
     */
    function clearErrors(modal) {
        const box = modal.getBody()[0].querySelector('.slideshow-quick-edit-errors');
        if (box) {
            box.remove();
        }
    }

    /**
     * Hide the embedded Dixeo editor when the layout is title only.
     *
     * @param {Object} modal Save/cancel modal
     */
    function syncLayoutExtras(modal) {
        const body = modal.getBody()[0];
        const selected = body.querySelector('input[name="layout"]:checked');
        const editor = body.querySelector('.slideshow-dixeo-editor');
        if (!editor || !selected) {
            return;
        }
        if (selected.value === 'titleonly') {
            editor.setAttribute('hidden', 'hidden');
        } else {
            editor.removeAttribute('hidden');
        }
    }

    /**
     * Start the Dixeo editor once its textarea is in the modal.
     *
     * @param {Object} modal Save/cancel modal
     */
    function initDixeoEditor(modal) {
        const root = modal.getBody()[0].querySelector('.slideshow-dixeo-editor');
        if (!root || root.dataset.bound === '1' || !root.querySelector('textarea')) {
            return;
        }
        root.dataset.bound = '1';
        require(['local_dixeo_editor/mod_editor_form'], (Editor) => {
            Editor.initEmbedded(
                Number(root.dataset.cmid),
                Number(root.dataset.slideid),
                Number(root.dataset.sessionid)
            );
        });
    }

    /**
     * Live HTML from the embedded Dixeo editor.
     *
     * @param {HTMLElement} root Editor wrapper
     * @returns {String}
     */
    function readDixeoText(root) {
        const textarea = root.querySelector('textarea');
        if (window.tinymce && textarea && typeof window.tinymce.get === 'function') {
            const instance = window.tinymce.get(textarea.id);
            if (instance && typeof instance.getContent === 'function') {
                return instance.getContent();
            }
        }
        if (textarea) {
            return textarea.value;
        }
        return '';
    }

    /**
     * Read the embedded Dixeo editor, when it is the editor on screen.
     *
     * @param {HTMLElement} body Modal body
     * @returns {Object|null} External arguments, or null when the editor is hidden
     */
    function readDixeoArgs(body) {
        const root = body.querySelector('.slideshow-dixeo-editor');
        if (!root || root.hasAttribute('hidden')) {
            return null;
        }
        const itemInput = root.querySelector('input[name="modulecontent[itemid]"]');
        const formatInput = root.querySelector('[name="modulecontent[format]"]');
        return {
            usedixeo: true,
            dixeotext: readDixeoText(root),
            dixeoformat: Number(formatInput ? formatInput.value : 1),
            dixeoitemid: Number(itemInput ? itemInput.value : 0),
            editorsessionid: Number(root.dataset.sessionid || 0),
        };
    }

    /**
     * Run a callback for each TinyMCE instance inside the modal.
     *
     * @param {Object} modal Save/cancel modal
     * @param {Function} callback Receives the editor instance
     */
    function eachModalEditor(modal, callback) {
        if (!window.tinymce || typeof window.tinymce.get !== 'function') {
            return;
        }
        const body = modal.getBody()[0];
        if (!body) {
            return;
        }
        body.querySelectorAll('textarea').forEach((textarea) => {
            if (!textarea.id) {
                return;
            }
            const editor = window.tinymce.get(textarea.id);
            if (editor && !editor.removed) {
                callback(editor);
            }
        });
    }

    /**
     * Copy TinyMCE content into the textareas before the form is posted.
     *
     * @param {Object} modal Save/cancel modal
     */
    function saveModalEditors(modal) {
        eachModalEditor(modal, (editor) => {
            if (typeof editor.save === 'function') {
                editor.save();
            }
        });
    }

    /**
     * Remove one TinyMCE instance.
     *
     * @param {Object} editor TinyMCE editor
     */
    function removeEditor(editor) {
        if (!editor || editor.removed) {
            return;
        }
        try {
            editor.remove();
        } catch (e) {
            // A detached editor can throw. The next open must still be able to start.
            void e;
        }
    }

    /**
     * Remove TinyMCE instances whose textarea is no longer in the document.
     *
     * Closing the modal from the keyboard detaches the editor before this
     * module can walk the modal body.
     */
    function releaseDetachedEditors() {
        if (!window.tinymce || typeof window.tinymce.get !== 'function') {
            return;
        }
        window.tinymce.get().forEach((editor) => {
            if (editor.removed) {
                return;
            }
            let element = null;
            try {
                element = editor.getElement();
            } catch (e) {
                void e;
            }
            if (!element || !element.isConnected) {
                removeEditor(editor);
            }
        });
    }

    /**
     * Destroy TinyMCE instances that belong to this modal.
     *
     * TinyMCE registers an editor by textarea id. Hiding the modal leaves that
     * editor in place, so the next open cannot start another one on the same id.
     *
     * @param {Object} modal Save/cancel modal
     */
    function destroyModalEditors(modal) {
        eachModalEditor(modal, removeEditor);
        releaseDetachedEditors();
    }

    /**
     * Remove the modal after its editors have been destroyed.
     *
     * TinyMCE must leave the page outside the click that closed the modal.
     *
     * @param {Object} modal Save/cancel modal
     */
    function closeModal(modal) {
        window.setTimeout(() => {
            destroyModalEditors(modal);
            modal.destroy();
        }, 1);
    }

    /**
     * Drop the editor session when the modal closes without saving.
     *
     * @param {Object} modal Save/cancel modal
     */
    function discardDixeoSession(modal) {
        const root = modal.getBody()[0].querySelector('.slideshow-dixeo-editor');
        if (!root || root.dataset.discarded === '1' || !root.dataset.sessionid) {
            return;
        }
        root.dataset.discarded = '1';
        require(['core/ajax'], (Ajax) => {
            const request = Ajax.call([{
                methodname: 'local_dixeo_editor_discard_editor_session',
                args: {
                    cmid: Number(root.dataset.cmid),
                    sessionid: Number(root.dataset.sessionid),
                    slideid: Number(root.dataset.slideid),
                },
            }])[0];
            request.catch(() => {
                return undefined;
            });
        });
    }

    /**
     * Post the modal form and keep it open when the server rejects the values.
     *
     * @param {Object} modal Save/cancel modal
     * @param {Object} options cmid and onSaved callback
     * @param {{saved: Boolean}} state Set when the save is stored
     */
    function submitQuickEdit(modal, options, state) {
        if (saving) {
            return;
        }
        const body = modal.getBody()[0];
        const form = body.querySelector('.slideshow-quick-edit form');
        if (!form) {
            return;
        }
        saving = true;
        clearErrors(modal);
        saveModalEditors(modal);
        const layout = form.querySelector('input[name="layout"]:checked');
        const content = form.querySelector('[name="content_editor[text]"]');
        const contentformat = form.elements['content_editor[format]'];
        const contentitem = form.elements['content_editor[itemid]'];
        const dixeo = readDixeoArgs(body);
        const request = Ajax.call([{
            methodname: 'mod_slideshow_save_slide',
            args: {
                cmid: Number(form.elements.cmid.value),
                slideid: Number(form.elements.id.value),
                name: form.elements.name.value,
                layout: layout ? layout.value : '',
                image: Number(form.elements.image ? form.elements.image.value : 0),
                hascontent: content !== null,
                content: content ? content.value : '',
                contentformat: Number(contentformat ? contentformat.value : 1),
                contentitemid: Number(contentitem ? contentitem.value : 0),
                usedixeo: dixeo !== null,
                dixeotext: dixeo ? dixeo.dixeotext : '',
                dixeoformat: dixeo ? dixeo.dixeoformat : 1,
                dixeoitemid: dixeo ? dixeo.dixeoitemid : 0,
                editorsessionid: dixeo ? dixeo.editorsessionid : 0,
            },
        }])[0];
        request.then((payload) => {
            saving = false;
            if (payload.errors.length) {
                const errors = {};
                payload.errors.forEach((error) => {
                    errors[error.field] = error.message;
                });
                showErrors(modal, errors);
                return;
            }
            if (!payload.html) {
                return;
            }
            state.saved = true;
            options.onSaved(payload.html);
            closeModal(modal);
            return;
        }).catch((error) => {
            saving = false;
            Notification.exception(error);
            return;
        });
    }

    return {
        /**
         * Open the edit modal for one slide.
         *
         * @param {Object} options
         * @param {Number} options.cmid Course module id
         * @param {Number} options.contextid Module context id
         * @param {Number} options.slideid Slide row id
         * @param {Function} options.onSaved Called with the replacement slide HTML
         */
        open: function(options) {
            const state = {saved: false};
            releaseDetachedEditors();
            Str.get_string('edit', 'slideshow').then((title) => {
                return ModalSaveCancel.create({
                    title: title,
                    body: Fragment.loadFragment('mod_slideshow', 'quickedit', options.contextid, {
                        cmid: String(options.cmid),
                        slideid: String(options.slideid),
                    }),
                    large: true,
                    show: true,
                    removeOnClose: true,
                });
            }).then((modal) => {
                modal.getRoot().on(ModalEvents.bodyRendered, () => {
                    initDixeoEditor(modal);
                    syncLayoutExtras(modal);
                });
                modal.getRoot()[0].addEventListener('change', (event) => {
                    if (event.target && event.target.name === 'layout') {
                        syncLayoutExtras(modal);
                    }
                });
                modal.getRoot().on(ModalEvents.save, (event) => {
                    event.preventDefault();
                    submitQuickEdit(modal, options, state);
                });
                modal.getRoot()[0].addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        destroyModalEditors(modal);
                    }
                }, true);
                modal.getRoot().on(ModalEvents.cancel, (event) => {
                    event.preventDefault();
                    closeModal(modal);
                });
                modal.getRoot().on(ModalEvents.destroyed, () => {
                    window.setTimeout(() => {
                        destroyModalEditors(modal);
                    }, 1);
                });
                modal.getRoot().on(ModalEvents.hidden, () => {
                    if (!state.saved) {
                        discardDixeoSession(modal);
                    }
                });
                initDixeoEditor(modal);
                syncLayoutExtras(modal);
                return modal;
            }).catch(Notification.exception);
        },
    };
});
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
     * Post the modal form and keep it open when the server rejects the values.
     *
     * @param {Object} modal Save/cancel modal
     * @param {Object} options cmid and onSaved callback
     */
    function submitQuickEdit(modal, options) {
        if (saving) {
            return;
        }
        const form = modal.getBody()[0].querySelector('form');
        if (!form) {
            return;
        }
        saving = true;
        clearErrors(modal);
        const layout = form.querySelector('input[name="layout"]:checked');
        const request = Ajax.call([{
            methodname: 'mod_slideshow_save_slide',
            args: {
                cmid: Number(form.elements.cmid.value),
                slideid: Number(form.elements.id.value),
                name: form.elements.name.value,
                layout: layout ? layout.value : '',
                image: Number(form.elements.image ? form.elements.image.value : 0),
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
            options.onSaved(payload.html);
            modal.hide();
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
                modal.getRoot().on(ModalEvents.save, (event) => {
                    event.preventDefault();
                    submitQuickEdit(modal, options);
                });
                return modal;
            }).catch(Notification.exception);
        },
    };
});
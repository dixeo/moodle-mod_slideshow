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
 * Slide list management (reorder, delete, visibility) for mod_slideshow.
 *
 * @module      mod_slideshow/slides
 * @copyright   2024 Dixeo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/config',
    'core/ajax',
    'core/modal_save_cancel',
    'core/modal_events',
    'core/str',
    'mod_slideshow/slip',
], function(Config, Ajax, ModalSaveCancel, ModalEvents, str, Slip) {
    return {
        init: (slideshow, contextid) => {
            var ul = document.querySelector('#slide-list[data-cmid="' + slideshow + '"]');
            if (!ul) {
                return;
            }

            ul.addEventListener('slip:beforereorder', function(e) {
                if (/demo-no-reorder/.test(e.target.className)) {
                    e.preventDefault();
                }
            }, false);

            ul.addEventListener('slip:beforeswipe', function(e) {
                if (e.target.nodeName == 'INPUT' || /no-swipe/.test(e.target.className)) {
                    e.preventDefault();
                }
            }, false);

            ul.addEventListener('slip:beforewait', function(e) {
                if (e.target.className.indexOf('instant') > -1) {
                    e.preventDefault();
                }
            }, false);

            ul.addEventListener('slip:reorder', function(e) {
                e.target.parentNode.insertBefore(e.target, e.detail.insertBefore);

                let slideid = e.target.dataset.id;
                let oldorder = e.detail.originalIndex;
                let neworder = e.detail.spliceIndex;

                if (neworder !== oldorder) {
                    changeSortOrder(slideid, neworder, oldorder);
                }
                return false;
            }, false);

            new Slip(ul);

            var items = ul.querySelectorAll(".handle");
            items.forEach(function(item) {
                item.addEventListener('mousedown', function() {
                    this.style.cursor = "-webkit-grabbing";
                    this.style.cursor = "-moz-grabbing";
                });
                item.addEventListener('mouseover', function() {
                    this.style.cursor = "-webkit-grab";
                    this.style.cursor = "-moz-grab";
                });
                item.addEventListener('mouseup', function() {
                    this.style.cursor = "-webkit-grab";
                    this.style.cursor = "-moz-grab";
                });
            });

            var slides = ul.querySelectorAll('.slide-item');
            slides.forEach(function(slide) {
                let slideid = slide.dataset.id;

                let deleteBtn = slide.querySelector('.actions .delete');
                let showBtn = slide.querySelector('.actions .show');
                let hideBtn = slide.querySelector('.actions .hide');
                let editBtn = slide.querySelector('.actions .edit');

                deleteBtn.addEventListener('pointerup', function(e) {
                    e.preventDefault();
                    confirmDeleteSlide(slideid);
                });

                showBtn.addEventListener('pointerup', function(e) {
                    e.preventDefault();
                    hideShowSlide(slideid, 'show');
                });

                hideBtn.addEventListener('pointerup', function(e) {
                    e.preventDefault();
                    hideShowSlide(slideid, 'hide');
                });

                editBtn.addEventListener('pointerup', function(e) {
                    e.preventDefault();
                    if (!contextid) {
                        return;
                    }
                    require(['mod_slideshow/quickedit'], function(Quickedit) {
                        Quickedit.open({
                            cmid: Number(slideshow),
                            contextid: Number(contextid),
                            slideid: Number(slideid),
                            onSaved: function(html, payload) {
                                replaceListSlide(slideid, payload || {html: html});
                            },
                        });
                    });
                });
            });

            /**
             * Replace the list thumbnail and label after the edit modal saves.
             *
             * @param {Number} slideid Slide ID
             * @param {Object} payload Save response with preview markup and name
             */
            let replaceListSlide = (slideid, payload) => {
                if (!payload) {
                    return;
                }
                let slide = ul.querySelector('.slide-item[data-id="' + slideid + '"]');
                if (!slide) {
                    return;
                }
                if (payload.preview) {
                    let current = slide.querySelector('.slide-list-thumb');
                    let holder = document.createElement('template');
                    holder.innerHTML = String(payload.preview).trim();
                    let fresh = holder.content.firstElementChild;
                    if (current && fresh) {
                        current.replaceWith(fresh);
                    }
                }
                if (payload.name) {
                    let name = slide.querySelector('.name');
                    if (name) {
                        name.textContent = payload.name;
                    }
                }
            };

            /**
             * Change sort order
             * @param {Number} slideid Slide ID
             * @param {Number} neworder New order
             * @param {Number} oldorder Old order
             */
            let changeSortOrder = (slideid, neworder, oldorder) => {
                var ul = document.querySelector('#slide-list[data-cmid="' + slideshow + '"]');

                return Ajax.call([{
                    methodname: 'mod_slideshow_reorder_slide',
                    args: {
                        slideid: slideid,
                        oldorder: oldorder,
                        neworder: neworder,
                    },
                }])[0].then(function() {
                    updateSortorderUI(ul);
                    return;
                }).catch(function() {
                    // Request failed; list order may be stale until refresh.
                    return;
                });
            };

            /**
             * Update sort order numbers in UI
             * @param {Element} ul Slide list
             */
            let updateSortorderUI = (ul) => {
                let sortorder = 1;
                let slides = ul.querySelectorAll('.slide-item');
                slides.forEach(function(slide) {
                    slide.dataset.sortorder = sortorder;
                    let handle = slide.querySelector('.handle');
                    handle.textContent = sortorder;
                    sortorder++;
                });
            };

            /**
             * Hide/show slide
             * @param {Number} slideid Slide ID
             * @param {String} action Action
             */
            let hideShowSlide = (slideid, action) => {
                var ul = document.querySelector('#slide-list[data-cmid="' + slideshow + '"]');

                return Ajax.call([{
                    methodname: 'mod_slideshow_set_slide_visibility',
                    args: {
                        slideid: slideid,
                        action: action,
                    },
                }])[0].then(function(response) {
                    let slide = ul.querySelector('.slide-item[data-id="' + slideid + '"]');
                    let hide = slide.querySelector('.actions .hide');
                    let show = slide.querySelector('.actions .show');

                    if (response.action === 'show') {
                        hide.classList.remove('hidden');
                        show.classList.add('hidden');
                    } else {
                        hide.classList.add('hidden');
                        show.classList.remove('hidden');
                    }
                    return;
                }).catch(function() {
                    // Request failed.
                    return;
                });
            };

            /**
             * Confirm slide deletion
             * @param {Number} slideid Slide ID
             */
            let confirmDeleteSlide = (slideid) => {
                let deleteStr = str.get_string('delete', 'slideshow');
                let deleteConfirmStr = str.get_string('deleteconfirm', 'slideshow');
                let confirmStr = str.get_string('confirm', 'slideshow');
                ModalSaveCancel.create({
                    title: deleteStr,
                    body: deleteConfirmStr,
                    removeOnClose: true,
                    buttons: {
                        save: confirmStr,
                    },
                    show: true,
                })
                .then((modal) => {
                    modal.getRoot().on(ModalEvents.save, function() {
                        deleteSlide(slideid);
                    });
                    modal.show();
                    return modal;
                })
                .catch(function() {
                    // Modal could not be created.
                });
            };

            /**
             * Delete slide
             * @param {Number} slideid Slide ID
             */
            let deleteSlide = (slideid) => {
                var ul = document.querySelector('#slide-list[data-cmid="' + slideshow + '"]');

                return Ajax.call([{
                    methodname: 'mod_slideshow_delete_slide',
                    args: {
                        slideid: slideid,
                    },
                }])[0].then(function(response) {
                    if (response.result) {
                        let slide = ul.querySelector('.slide-item[data-id="' + slideid + '"]');
                        slide.remove();
                        updateSortorderUI(ul);
                    }
                    return;
                }).catch(function() {
                    // Request failed.
                    return;
                });
            };
        }
    };
});

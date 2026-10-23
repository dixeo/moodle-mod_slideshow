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
 * Add navigation functionality to slideshow.
 *
 * @module      mod_slideshow/presentation
 * @copyright   2025 Dixeo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/config',
    'mod_slideshow/qrcode',
    'core/notification',
    'core/str'
], function(Config, QRCode, Notification, Str) {
    const Selectors = {
        // Only direct children of the container (avoids .slide divs inside slide content).
        slides: ':scope > .slide',
        prev: '.prev',
        next: '.next',
        currentSlide: '.currentslide',
        fontsize: '.fontsize',
        overlay: '.overlay',
        scantoenrol: '.scantoenrol',
        qrcode: '.qrcode',
        fullscreen: '.fullscreen',
        editslide: '.editslide',
    };

    return {
        /**
         * Initialize module
         * @param {Object} options Configuration options
         * @param {Number} options.cmid The course module id to setup for
         */
        init: function(options) {
            Str.get_strings([
                {key: 'errorcontainernotfound', component: 'mod_slideshow'},
                {key: 'fullscreennotsupported', component: 'mod_slideshow'},
                {key: 'fullscreenexitnotsupported', component: 'mod_slideshow'},
                {key: 'error', component: 'moodle'},
                {key: 'warning', component: 'moodle'},
            ]).done(function(s) {
                const strContainerNotFound = s[0];
                const strFsNotSupported = s[1];
                const strFsExitNotSupported = s[2];
                const strError = s[3];
                const strWarning = s[4];

                let container = document.querySelector('#slideshow-' + options.cmid);
                if (!container) {
                    Notification.alert(strContainerNotFound, null, strError);
                    return;
                }

                let slides = container.querySelectorAll(Selectors.slides);
                let total = slides.length;
                let current = 0;

                // Navigation buttons
                let prev = container.querySelector(Selectors.prev);
                let next = container.querySelector(Selectors.next);
                let currentslide = container.querySelector(Selectors.currentSlide);
                let overlay = container.querySelector(Selectors.overlay);
                let scantoenrol = overlay.querySelector(Selectors.scantoenrol);
                let fontsize = container.querySelector(Selectors.fontsize);
                let fontsizecontrol = container.querySelector('.fontsize-control');
                let fontsizepanel = container.querySelector('.fontsize-panel');
                let fontsizetoggle = container.querySelector('.fontsize-toggle');
                let fullscreen = container.querySelector(Selectors.fullscreen);
                // Windowed slides start here. Fullscreen starts at the slider maximum, then shrinks to fit.
                const fontSizeDefault = Number(fontsize.value);

                // Edit slide button
                let editslide = document.querySelector(Selectors.editslide);

                /**
                 * Checks if the document is currently in fullscreen mode.
                 *
                 * @returns {boolean} True if fullscreen mode is active, false otherwise.
                 */
                function isFullscreenActive() {
                    return !!(
                        document.fullscreenElement ||
                        document.webkitFullscreenElement ||
                        document.mozFullScreenElement ||
                        document.msFullscreenElement
                    );
                }

                /**
                 * Requests fullscreen mode for the given element using the appropriate browser-specific API.
                 *
                 * @param {HTMLElement} elem - The DOM element to display in fullscreen mode.
                 */
                function requestFullscreenSafe(elem) {
                    if (elem.requestFullscreen) {
                        elem.requestFullscreen();
                    } else if (elem.webkitRequestFullscreen) { // Safari and iOS
                        elem.webkitRequestFullscreen();
                    } else if (elem.mozRequestFullScreen) { // Firefox
                        elem.mozRequestFullScreen();
                    } else if (elem.msRequestFullscreen) { // IE/Edge
                        elem.msRequestFullscreen();
                    } else {
                        Notification.alert(strFsNotSupported, null, strWarning);
                    }
                }

                /**
                 * Exits fullscreen mode in a cross-browser compatible way.
                 */
                function exitFullscreenSafe() {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) { // Safari
                        document.webkitExitFullscreen();
                    } else if (document.mozCancelFullScreen) { // Firefox
                        document.mozCancelFullScreen();
                    } else if (document.msExitFullscreen) { // IE/Edge
                        document.msExitFullscreen();
                    } else {
                        Notification.alert(strFsExitNotSupported, null, strWarning);
                    }
                }

                /**
                 * Updates the height of the slide container based on the current slide's width (16:9).
                 */
                function updateSlideDimensions() {
                    overlay.setAttribute(
                        'style',
                        'width: ' + container.offsetWidth + 'px; height: ' + container.offsetWidth * (9 / 16) + 'px;'
                    );
                    slides.forEach(slide => {
                        slide.setAttribute('style', 'height: ' + container.offsetWidth * (9 / 16) + 'px;');
                    });
                }

                /**
                 * Update slide counter and prev/next disabled state.
                 */
                function updateCurrentSlide() {
                    currentslide.innerText = (current + 1) + '/' + total;
                    if (current === 0) {
                        prev.classList.add('disabled');
                    } else {
                        prev.classList.remove('disabled');
                    }
                    if (current === total - 1) {
                        next.classList.add('disabled');
                    } else {
                        next.classList.remove('disabled');
                    }
                }

                /**
                 * Show the previous slide.
                 */
                function prevSlide() {
                    if (current > 0) {
                        slides[current].classList.add('hidden');
                        slides[current - 1].classList.remove('hidden');
                        current--;
                        updateCurrentSlide();
                        ensureSlideSize(slides[current]);
                    }
                }

                /**
                 * Show the next slide.
                 */
                function nextSlide() {
                    if (current < total - 1) {
                        slides[current].classList.add('hidden');
                        slides[current + 1].classList.remove('hidden');
                        current++;
                        updateCurrentSlide();
                        ensureSlideSize(slides[current]);
                    }
                }

                /**
                 * Attribute that stores the fitted size for the current display mode.
                 *
                 * @returns {string}
                 */
                function fontSizeAttribute() {
                    if (isFullscreenActive()) {
                        return 'data-fontsize-fullscreen';
                    }
                    return 'data-fontsize';
                }

                /**
                 * True when the visible slide body still has a scrollbar.
                 *
                 * @param {HTMLElement} slide
                 * @returns {boolean}
                 */
                function slideHasScrollbar(slide) {
                    let nodes = slide.querySelectorAll('.slide-body, .no-overflow');
                    for (let i = 0; i < nodes.length; i++) {
                        let el = nodes[i];
                        if (el.scrollHeight > el.clientHeight + 2) {
                            return true;
                        }
                        if (el.scrollWidth > el.clientWidth + 2) {
                            return true;
                        }
                    }
                    return false;
                }

                /**
                 * Apply a font size percentage and optionally remember it on the current slide.
                 *
                 * @param {number} size
                 * @param {boolean} remember
                 * @returns {number} The size after clamping to the slider range.
                 */
                function applyFontSize(size, remember) {
                    let min = Number(fontsize.min);
                    let max = Number(fontsize.max);
                    let next = size;
                    if (next < min) {
                        next = min;
                    }
                    if (next > max) {
                        next = max;
                    }
                    fontsize.value = String(next);
                    container.style.setProperty('font-size', next + '%');

                    let images = slides[current].querySelectorAll('img');
                    let scale = next / 100;
                    images.forEach(img => {
                        if (!img.naturalWidth) {
                            return;
                        }
                        img.style.width = (img.naturalWidth * scale) + 'px';
                    });
                    if (remember) {
                        slides[current].setAttribute(fontSizeAttribute(), String(next));
                    }
                    return next;
                }

                /**
                 * Move the slider by one step and keep the new size on this slide.
                 *
                 * @param {number} direction 1 to enlarge, -1 to reduce.
                 */
                function stepFontSize(direction) {
                    let step = Number(fontsize.step);
                    applyFontSize(Number(fontsize.value) + (direction * step), true);
                }

                /**
                 * Use the remembered size, or shrink from the default until the scrollbar is gone.
                 *
                 * @param {HTMLElement} slide
                 */
                function ensureSlideSize(slide) {
                    let attr = fontSizeAttribute();
                    let stored = slide.getAttribute(attr);
                    if (stored !== null) {
                        applyFontSize(Number(stored), false);
                        return;
                    }
                    let size = fontSizeDefault;
                    if (isFullscreenActive()) {
                        size = Number(fontsize.max);
                    }
                    size = applyFontSize(size, false);
                    let step = Number(fontsize.step);
                    let min = Number(fontsize.min);
                    while (size - step >= min && slideHasScrollbar(slide)) {
                        size = applyFontSize(size - step, false);
                    }
                    slide.setAttribute(attr, String(size));
                }

                /**
                 * Show or hide the size slider beside the A buttons.
                 *
                 * @param {boolean} open
                 */
                function setFontSizePanel(open) {
                    if (open) {
                        fontsizepanel.removeAttribute('hidden');
                        fontsizetoggle.setAttribute('aria-expanded', 'true');
                        fontsize.focus();
                    } else {
                        fontsizepanel.setAttribute('hidden', 'hidden');
                        fontsizetoggle.setAttribute('aria-expanded', 'false');
                    }
                }

                /**
                 * Navigate to the edit page for the current slide.
                 */
                function editSlide() {
                    let slideid = slides[current].getAttribute('data-slideid');
                    window.location.href = Config.wwwroot + '/mod/slideshow/edit.php?cm=' + options.cmid + '&id=' + slideid;
                }

                // Set width and height for the QR Code container
                overlay.setAttribute(
                    'style',
                    'width: ' + container.offsetWidth + 'px; height: ' + container.offsetWidth * (9 / 16) + 'px;'
                );

                // Hide overlay on click
                overlay.addEventListener('click', () => {
                    overlay.classList.toggle('hidden');
                });

                // Enrol URL is set, add QR logic
                if (options.enrolurl) {
                    let qrcode = container.querySelector(Selectors.qrcode);

                    // Toggle overlay button
                    qrcode.addEventListener('click', () => {
                        overlay.classList.toggle('hidden');
                    });

                    // Generate QR code
                    new QRCode(scantoenrol, {
                        text: options.enrolurl,
                        width: container.offsetHeight * 0.4,
                        height: container.offsetHeight * 0.4,
                    });
                }

                // Navigate slides with arrow keys
                document.addEventListener('keyup', (e) => {
                    if (isFullscreenActive()) {
                        if (e.key === 'ArrowLeft') {
                            prevSlide();
                        }
                        if (e.key === 'ArrowRight') {
                            nextSlide();
                        }
                    }
                });

                // Check if fullscreen is supported
                if (!document.fullscreenEnabled && !document.webkitFullscreenEnabled) {
                    fullscreen.classList.add('hidden');
                }

                // Listen for fullscreen change event
                document.addEventListener('fullscreenchange', () => {
                    if (isFullscreenActive()) {
                        container.classList.add('fullscreen');
                    } else {
                        container.classList.remove('fullscreen');
                    }
                    updateSlideDimensions();
                    ensureSlideSize(slides[current]);
                });

                // Previous slide button
                prev.addEventListener('click', () => {
                    prevSlide();
                });

                // Next slide button
                next.addEventListener('click', () => {
                    nextSlide();
                });

                // Fullscreen button
                fullscreen.addEventListener('click', () => {
                    if (isFullscreenActive()) {
                        exitFullscreenSafe();
                    } else {
                        requestFullscreenSafe(container);
                    }
                });

                // Font size scales the slide. The first visit shrinks until the body has no scrollbar.
                fontsize.addEventListener('input', function() {
                    applyFontSize(Number(this.value), true);
                });

                fontsizetoggle.addEventListener('click', () => {
                    setFontSizePanel(fontsizepanel.hasAttribute('hidden'));
                });

                container.querySelector('.fontsize-minus').addEventListener('click', () => {
                    stepFontSize(-1);
                });
                container.querySelector('.fontsize-plus').addEventListener('click', () => {
                    stepFontSize(1);
                });

                /**
                 * True while the size slider is open.
                 *
                 * @returns {boolean}
                 */
                function fontSizePanelOpen() {
                    return !fontsizepanel.hasAttribute('hidden');
                }

                // Capture phase so the open slider gets the keys before the page scrolls.
                document.addEventListener('keydown', (e) => {
                    if (!fontSizePanelOpen()) {
                        return;
                    }
                    if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') {
                        return;
                    }
                    let active = document.activeElement;
                    if (active && !fontsizecontrol.contains(active)) {
                        let tag = active.tagName;
                        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || active.isContentEditable) {
                            return;
                        }
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.key === 'ArrowUp') {
                        stepFontSize(1);
                    } else {
                        stepFontSize(-1);
                    }
                }, true);

                let wheelBudget = 0;
                fontsizecontrol.addEventListener('wheel', (e) => {
                    if (!fontSizePanelOpen()) {
                        return;
                    }
                    e.preventDefault();
                    let delta = e.deltaY;
                    if (e.deltaMode === 1) {
                        delta = delta * 40;
                    } else if (e.deltaMode === 2) {
                        delta = delta * 800;
                    }
                    wheelBudget += delta;
                    if (Math.abs(wheelBudget) < 40) {
                        return;
                    }
                    if (wheelBudget > 0) {
                        stepFontSize(-1);
                    } else {
                        stepFontSize(1);
                    }
                    wheelBudget = 0;
                }, {passive: false, capture: true});

                document.addEventListener('click', (e) => {
                    if (!fontsizecontrol.contains(e.target)) {
                        setFontSizePanel(false);
                    }
                });

                // Edit slide button (only in DOM for users with mod/slideshow:viewslides).
                if (editslide) {
                    editslide.addEventListener('click', () => {
                        editSlide();
                    });
                }

                // Resize slide to keep a 16:9 aspect ratio
                window.addEventListener('resize', () => {
                    updateSlideDimensions();
                }, true);

                // Set initial slide dimensions and counter, then fit the first slide.
                updateSlideDimensions();
                updateCurrentSlide();
                ensureSlideSize(slides[current]);
            });
        }
    };
});


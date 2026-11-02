Third-party code bundled in mod_slideshow
=========================================

Both libraries are AMD modules under amd/src/. thirdpartylibs.xml records thirdparty/slip/slip.js and thirdparty/qrcodejs/qrcode.js, which are links to those modules. The links sit outside amd/src, so Grunt does not ignore the source modules, and outside amd/build, which Plugin CI removes before Grunt runs.

Slip.js (amd/src/slip.js)
-------------------------
Upstream: https://github.com/kornelski/slip (BSD 2-Clause).
Base: the pre-npm "Slip.js 1.0" source (2014). It predates the first npm release (slipjs 1.2.0);
the exact upstream commit was not recorded when the file was first imported.
Moodle changes:
- Wrapped in an AMD define() that returns the Slip constructor instead of setting window.Slip.
- Added the GPL file header and /* eslint-disable */ (third-party code is not linted).
To upgrade: replace the code between "var Slip = (function() {" and "})();" with the upstream
slip.js body, keep the AMD wrapper, then run grunt amd for mod_slideshow. Check that the
slip:reorder event still provides detail.originalIndex and detail.spliceIndex (used by slides.js).

qrcodejs (amd/src/qrcode.js)
----------------------------
Upstream: https://github.com/davidshimjs/qrcodejs (MIT), qrcode.min.js from master
04f46c6a0708418cb7b96fc563eacae0fbf77674 (2015-11-25, the latest upstream commit).
Moodle changes:
- Wrapped in an AMD define() that returns QRCode.
- "return/android/i.test(b)" written as "return (/android/i).test(b)" (two occurrences) so the
  Moodle minifier parses it. No functional change.
To upgrade: replace the minified line with the new upstream qrcode.min.js, reapply the change
above if still needed, then run grunt amd for mod_slideshow.

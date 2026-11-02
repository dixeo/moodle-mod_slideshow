# Slideshow

## Description

Turn your course content into a polished, fullscreen slide deck—rich media, presenter controls inside a standard Moodle resource.

## About

Slideshow is a Moodle **resource module** that lets instructors build multi-slide presentations directly in the course. Each slide uses the familiar text editor, so you can combine **text, images, audio, video, links, and embedded content** (for example maps or third-party widgets) in one linear story.

Learners get a focused **presentation view** with **previous/next navigation**, **dynamic text sizing**, and **fullscreen** for classrooms or self-paced review. Teachers can **add, hide, reorder, and edit** slides without leaving the activity.

## Requirements

- Moodle **4.3** to **5.1** (see `version.php`: `$plugin->requires = 2023100900`, `$plugin->supported = [403, 501]`).
- Core **mod_page** must be present (its editor options are used when the Dixeo editor saves slide content).

## Installation

Install the release ZIP `mod_slideshow-<release>.zip` under **Site administration → Plugins → Install plugins**, or copy the `slideshow` directory to `mod/slideshow`.

## Source code

- **Repository:** https://github.com/dixeo/moodle-mod_slideshow  
- **Bug tracker:** https://github.com/dixeo/moodle-mod_slideshow/issues

## External services and subscriptions

The plugin itself does not call any external service. It integrates with these optional Moodle plugins when they are installed; each one is responsible for its own data handling and privacy declarations:

| Plugin | What mod_slideshow does with it |
|--------|---------------------------------|
| `local_dixeo_editor` (depends on `local_dixeo`) | When Dixeo is enabled for the activity, slide bodies are edited in the embedded Dixeo editor and the slide HTML is sent to that editor (and through it to the Dixeo service configured in `local_dixeo`). |
| `filter_dixeo_imageeditor` | Offers an image editor for the featured slide image; the image file is processed by that plugin. |
| `local_sharecourse` | Builds the course enrolment URL that the player shows as a QR code ("Scan to enrol"). |

## Capabilities

| Capability | Purpose |
|------------|---------|
| `mod/slideshow:addinstance` | Add a Slideshow resource to a course (editing teacher / manager by default). |
| `mod/slideshow:view` | View the activity and the learner-facing presentation (guest and authenticated user archetypes allowed by default). |
| `mod/slideshow:viewslides` | Read capability: see the slide list, including hidden slides and their files (teacher, editing teacher, manager by default). |
| `mod/slideshow:manageslides` | Write capability (RISK_XSS, RISK_DATALOSS): create, edit, reorder, show/hide and delete slides (editing teacher and manager by default). |

**Upgrade note (1.1.3):** slide creation and editing now require `mod/slideshow:manageslides` instead of `mod/slideshow:viewslides`, and the default archetypes of `manageslides` no longer include the non-editing teacher. Archetype changes only apply to new installs or role resets. On existing sites, administrators should decide whether to remove (or prohibit) `mod/slideshow:manageslides` from the **Non-editing teacher** role so that non-editing teachers can no longer change slide content.

Adjust permissions under **Site administration → Users → Permissions** in the activity or course context as needed.

## Backup and restore

The module implements **`FEATURE_BACKUP_MOODLE2`** with `backup/moodle2` definitions. Course backups include the slideshow instance, all slides, intro and slide embedded files, featured slide images, and (via core) activity completion where applicable. Restored slide content is cleaned with `clean_text()` and the display options are restored as integers.

## Privacy

The plugin implements the Moodle **Privacy API** as a **null provider** (`classes/privacy/provider.php`): it does not store personal data in its own database tables (content is course material, like the Page resource). Intro and slide files use Moodle’s standard file storage in the activity context.

# Support

For documentation, licensing or technical support:

**Dixeo**

https://www.dixeo.com

support@dixeo.com

# License

Copyright © Dixeo

Licensed under the GNU General Public License v3.0 or later. Third-party libraries bundled in this plugin keep their own licences, declared in `thirdpartylibs.xml`.


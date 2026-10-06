# Slideshow

## Description

Turn your course content into a polished, fullscreen slide deck—rich media, presenter controls inside a standard Moodle resource.

## About

Slideshow is a Moodle **resource module** that lets instructors build multi-slide presentations directly in the course. Each slide uses the familiar text editor, so you can combine **text, images, audio, video, links, and embedded content** (for example maps or third-party widgets) in one linear story.

Learners get a focused **presentation view** with **previous/next navigation**, **dynamic text sizing**, and **fullscreen** for classrooms or self-paced review. Teachers can **add, hide, reorder, and edit** slides without leaving the activity.

## Requirements

- Moodle **4.3** or newer (see `version.php` for `$plugin->requires`, currently `2023100900`).

## Installation

Install via **Site administration → Plugins → Install plugins** using a ZIP of the `slideshow` folder, or deploy the folder under `mod/slideshow` and complete the upgrade prompt.

## Source code

- **Repository:** https://github.com/dixeo/moodle-mod_slideshow  
- **Bug tracker:** https://github.com/dixeo/moodle-mod_slideshow/issues

## External services and subscriptions

None.

## Capabilities

| Capability | Purpose |
|------------|---------|
| `mod/slideshow:addinstance` | Add a Slideshow resource to a course (editing teacher / manager by default). |
| `mod/slideshow:view` | View the activity and the learner-facing presentation (guest and authenticated user archetypes allowed by default). |
| `mod/slideshow:viewslides` | Open slide management (add, edit, reorder, hide/show, delete); intended for teacher roles. |

Adjust permissions under **Site administration → Users → Permissions** in the activity or course context as needed.

## Backup and restore

The module implements **`FEATURE_BACKUP_MOODLE2`** with `backup/moodle2` definitions. Course backups include the slideshow instance, all slides, intro and slide embedded files, and (via core) activity completion where applicable.

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


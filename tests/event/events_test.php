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

namespace mod_slideshow\event;

/**
 * Slide mutation event tests.
 *
 * @package    mod_slideshow
 * @category   test
 * @copyright  2026 Josemaria Bolanos <admin@mako.digital>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class events_test extends \advanced_testcase {

    /**
     * Set up each test case.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Build a slideshow activity, context, and slide for event tests.
     *
     * @return array{0: \stdClass, 1: \context_module, 2: \stdClass}
     */
    private function create_slideshow_fixture(): array {
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $context = \context_module::instance($slideshow->cmid);
        $slide = (object) [
            'slideshow' => $slideshow->id,
            'name' => 'Test slide',
            'content' => '<p>Test</p>',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'sortorder' => 0,
            'timemodified' => time(),
        ];
        $slide->id = $this->getDataGenerator()->get_plugin_generator('mod_slideshow')->create_slide($slide);

        return [$slideshow, $context, $slide];
    }

    /**
     * Slide created event carries module context and slide id.
     */
    public function test_slide_created(): void {
        [$slideshow, $context, $slide] = $this->create_slideshow_fixture();

        $event = slide_created::create_from_slide($slideshow, $context, $slide);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(slide_created::class, $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($slide->id, $event->objectid);
    }

    /**
     * Slide updated event carries module context and slide id.
     */
    public function test_slide_updated(): void {
        [$slideshow, $context, $slide] = $this->create_slideshow_fixture();

        $event = slide_updated::create_from_slide($slideshow, $context, $slide);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(slide_updated::class, $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($slide->id, $event->objectid);
    }

    /**
     * Slide deleted event carries module context and slide id.
     */
    public function test_slide_deleted(): void {
        [$slideshow, $context, $slide] = $this->create_slideshow_fixture();

        $event = slide_deleted::create_from_slide($slideshow, $context, $slide);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(slide_deleted::class, $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($slide->id, $event->objectid);
    }

    /**
     * Slide visibility updated event carries module context and slide id.
     */
    public function test_slide_visibility_updated(): void {
        [$slideshow, $context, $slide] = $this->create_slideshow_fixture();

        $event = slide_visibility_updated::create_from_slide($slideshow, $context, $slide);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(slide_visibility_updated::class, $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($slide->id, $event->objectid);
    }

    /**
     * Slides reordered event carries module context and moved slide id.
     */
    public function test_slides_reordered(): void {
        [$slideshow, $context, $slide] = $this->create_slideshow_fixture();

        $event = slides_reordered::create_from_slide($slideshow, $context, $slide);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(slides_reordered::class, $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($slide->id, $event->objectid);
    }
}

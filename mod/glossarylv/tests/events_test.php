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

/**
 * Unit tests for lib.php
 *
 * @package    mod_glossarylv
 * @category   test
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for glossarylv events.
 *
 * @package   mod_glossarylv
 * @category  test
 * @copyright 2013 Rajesh Taneja <rajesh@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class glossarylv_event_testcase extends advanced_testcase {

    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Test comment_created event.
     */
    public function test_comment_created() {
        global $CFG;
        require_once($CFG->dirroot . '/comment/lib.php');

        // Create a record for adding comment.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', array('course' => $course));
        $glossarylvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossarylv');

        $entry = $glossarylvgenerator->create_content($glossarylv);

        $context = context_module::instance($glossarylv->cmid);
        $cm = get_coursemodule_from_instance('glossarylv', $glossarylv->id, $course->id);
        $cmt = new stdClass();
        $cmt->component = 'mod_glossarylv';
        $cmt->context = $context;
        $cmt->course = $course;
        $cmt->cm = $cm;
        $cmt->area = 'glossarylv_entry';
        $cmt->itemid = $entry->id;
        $cmt->showcount = true;
        $comment = new comment($cmt);

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $comment->add('New comment');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\comment_created', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/mod/glossarylv/view.php', array('id' => $glossarylv->cmid));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test comment_deleted event.
     */
    public function test_comment_deleted() {
        global $CFG;
        require_once($CFG->dirroot . '/comment/lib.php');

        // Create a record for deleting comment.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', array('course' => $course));
        $glossarylvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossarylv');

        $entry = $glossarylvgenerator->create_content($glossarylv);

        $context = context_module::instance($glossarylv->cmid);
        $cm = get_coursemodule_from_instance('glossarylv', $glossarylv->id, $course->id);
        $cmt = new stdClass();
        $cmt->component = 'mod_glossarylv';
        $cmt->context = $context;
        $cmt->course = $course;
        $cmt->cm = $cm;
        $cmt->area = 'glossarylv_entry';
        $cmt->itemid = $entry->id;
        $cmt->showcount = true;
        $comment = new comment($cmt);
        $newcomment = $comment->add('New comment 1');

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $comment->delete($newcomment->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\comment_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/mod/glossarylv/view.php', array('id' => $glossarylv->cmid));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    public function test_course_module_viewed() {
        global $DB;
        // There is no proper API to call to trigger this event, so what we are
        // doing here is simply making sure that the events returns the right information.

        $course = $this->getDataGenerator()->create_course();
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', array('course' => $course->id));

        $dbcourse = $DB->get_record('course', array('id' => $course->id));
        $dbglossarylv = $DB->get_record('glossarylv', array('id' => $glossarylv->id));
        $context = context_module::instance($glossarylv->cmid);
        $mode = 'letter';

        $event = \mod_glossarylv\event\course_module_viewed::create(array(
            'objectid' => $dbglossarylv->id,
            'context' => $context,
            'other' => array('mode' => $mode)
        ));

        $event->add_record_snapshot('course', $dbcourse);
        $event->add_record_snapshot('glossarylv', $dbglossarylv);

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\course_module_viewed', $event);
        $this->assertEquals(CONTEXT_MODULE, $event->contextlevel);
        $this->assertEquals($glossarylv->cmid, $event->contextinstanceid);
        $this->assertEquals($glossarylv->id, $event->objectid);
        $expected = array($course->id, 'glossarylv', 'view', 'view.php?id=' . $glossarylv->cmid . '&amp;tab=-1',
            $glossarylv->id, $glossarylv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEquals(new moodle_url('/mod/glossarylv/view.php', array('id' => $glossarylv->cmid, 'mode' => $mode)), $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    public function test_course_module_instance_list_viewed() {
        // There is no proper API to call to trigger this event, so what we are
        // doing here is simply making sure that the events returns the right information.

        $course = $this->getDataGenerator()->create_course();

        $event = \mod_glossarylv\event\course_module_instance_list_viewed::create(array(
            'context' => context_course::instance($course->id)
        ));

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\course_module_instance_list_viewed', $event);
        $this->assertEquals(CONTEXT_COURSE, $event->contextlevel);
        $this->assertEquals($course->id, $event->contextinstanceid);
        $expected = array($course->id, 'glossarylv', 'view all', 'index.php?id='.$course->id, '');
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_entry_created() {
        // There is no proper API to call to trigger this event, so what we are
        // doing here is simply making sure that the events returns the right information.

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', array('course' => $course));
        $context = context_module::instance($glossarylv->cmid);

        $glossarylvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossarylv');
        $entry = $glossarylvgenerator->create_content($glossarylv);

        $eventparams = array(
            'context' => $context,
            'objectid' => $entry->id,
            'other' => array('concept' => $entry->concept)
        );
        $event = \mod_glossarylv\event\entry_created::create($eventparams);
        $event->add_record_snapshot('glossarylv_entries', $entry);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\entry_created', $event);
        $this->assertEquals(CONTEXT_MODULE, $event->contextlevel);
        $this->assertEquals($glossarylv->cmid, $event->contextinstanceid);
        $expected = array($course->id, "glossarylv", "add entry",
            "view.php?id={$glossarylv->cmid}&amp;mode=entry&amp;hook={$entry->id}", $entry->id, $glossarylv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_entry_updated() {
        // There is no proper API to call to trigger this event, so what we are
        // doing here is simply making sure that the events returns the right information.

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', array('course' => $course));
        $context = context_module::instance($glossarylv->cmid);

        $glossarylvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossarylv');
        $entry = $glossarylvgenerator->create_content($glossarylv);

        $eventparams = array(
            'context' => $context,
            'objectid' => $entry->id,
            'other' => array('concept' => $entry->concept)
        );
        $event = \mod_glossarylv\event\entry_updated::create($eventparams);
        $event->add_record_snapshot('glossarylv_entries', $entry);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\entry_updated', $event);
        $this->assertEquals(CONTEXT_MODULE, $event->contextlevel);
        $this->assertEquals($glossarylv->cmid, $event->contextinstanceid);
        $expected = array($course->id, "glossarylv", "update entry",
            "view.php?id={$glossarylv->cmid}&amp;mode=entry&amp;hook={$entry->id}", $entry->id, $glossarylv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_entry_deleted() {
        global $DB;
        // There is no proper API to call to trigger this event, so what we are
        // doing here is simply making sure that the events returns the right information.

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', array('course' => $course));
        $context = context_module::instance($glossarylv->cmid);
        $prevmode = 'view';
        $hook = 'ALL';

        $glossarylvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossarylv');
        $entry = $glossarylvgenerator->create_content($glossarylv);

        $DB->delete_records('glossarylv_entries', array('id' => $entry->id));

        $eventparams = array(
            'context' => $context,
            'objectid' => $entry->id,
            'other' => array(
                'mode' => $prevmode,
                'hook' => $hook,
                'concept' => $entry->concept
            )
        );
        $event = \mod_glossarylv\event\entry_deleted::create($eventparams);
        $event->add_record_snapshot('glossarylv_entries', $entry);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\entry_deleted', $event);
        $this->assertEquals(CONTEXT_MODULE, $event->contextlevel);
        $this->assertEquals($glossarylv->cmid, $event->contextinstanceid);
        $expected = array($course->id, "glossarylv", "delete entry",
            "view.php?id={$glossarylv->cmid}&amp;mode={$prevmode}&amp;hook={$hook}", $entry->id, $glossarylv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_category_created() {
        global $DB;
        // There is no proper API to call to trigger this event, so what we are
        // doing here is simply making sure that the events returns the right information.

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', array('course' => $course));
        $context = context_module::instance($glossarylv->cmid);

        // Create category and trigger event.
        $category = new stdClass();
        $category->name = 'New category';
        $category->usedynalink = 0;
        $category->id = $DB->insert_record('glossarylv_categories', $category);

        $event = \mod_glossarylv\event\category_created::create(array(
            'context' => $context,
            'objectid' => $category->id
        ));

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\category_created', $event);
        $this->assertEquals(CONTEXT_MODULE, $event->contextlevel);
        $this->assertEquals($glossarylv->cmid, $event->contextinstanceid);
        //add_to_log($course->id, "glossarylv", "add category", "editcategories.php?id=$cm->id", $cat->id,$cm->id);
        $expected = array($course->id, "glossarylv", "add category",
            "editcategories.php?id={$glossarylv->cmid}", $category->id, $glossarylv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        // Update category and trigger event.
        $category->name = 'Updated category';
        $DB->update_record('glossarylv_categories', $category);

        $event = \mod_glossarylv\event\category_updated::create(array(
            'context' => $context,
            'objectid' => $category->id
        ));

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\category_updated', $event);
        $this->assertEquals(CONTEXT_MODULE, $event->contextlevel);
        $this->assertEquals($glossarylv->cmid, $event->contextinstanceid);
        //add_to_log($course->id, "glossarylv", "edit category", "editcategories.php?id=$cm->id", $hook,$cm->id);
        $expected = array($course->id, "glossarylv", "edit category",
            "editcategories.php?id={$glossarylv->cmid}", $category->id, $glossarylv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);


        // Delete category and trigger event.
        $category = $DB->get_record('glossarylv_categories', array('id' => $category->id));
        $DB->delete_records('glossarylv_categories', array('id' => $category->id));

        $event = \mod_glossarylv\event\category_deleted::create(array(
            'context' => $context,
            'objectid' => $category->id
        ));
        $event->add_record_snapshot('glossarylv_categories', $category);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\category_deleted', $event);
        $this->assertEquals(CONTEXT_MODULE, $event->contextlevel);
        $this->assertEquals($glossarylv->cmid, $event->contextinstanceid);
        //add_to_log($course->id, "glossarylv", "delete category", "editcategories.php?id=$cm->id", $hook,$cm->id);
        $expected = array($course->id, "glossarylv", "delete category",
            "editcategories.php?id={$glossarylv->cmid}", $category->id, $glossarylv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_entry_approved() {
        global $DB;
        // There is no proper API to call to trigger this event, so what we are
        // doing here is simply making sure that the events returns the right information.

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $rolestudent = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $rolestudent->id);
        $teacher = $this->getDataGenerator()->create_user();
        $roleteacher = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $roleteacher->id);

        $this->setUser($teacher);
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv',
                array('course' => $course, 'defaultapproval' => 0));
        $context = context_module::instance($glossarylv->cmid);

        $this->setUser($student);
        $glossarylvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossarylv');
        $entry = $glossarylvgenerator->create_content($glossarylv);
        $this->assertEquals(0, $entry->approved);

        // Approve entry, trigger and validate event.
        $this->setUser($teacher);
        $newentry = new stdClass();
        $newentry->id           = $entry->id;
        $newentry->approved     = true;
        $newentry->timemodified = time();
        $DB->update_record("glossarylv_entries", $newentry);
        $params = array(
            'context' => $context,
            'objectid' => $entry->id
        );
        $event = \mod_glossarylv\event\entry_approved::create($params);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\entry_approved', $event);
        $this->assertEquals(CONTEXT_MODULE, $event->contextlevel);
        $this->assertEquals($glossarylv->cmid, $event->contextinstanceid);
        $expected = array($course->id, "glossarylv", "approve entry",
            "showentry.php?id={$glossarylv->cmid}&amp;eid={$entry->id}", $entry->id, $glossarylv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);


        // Disapprove entry, trigger and validate event.
        $this->setUser($teacher);
        $newentry = new stdClass();
        $newentry->id           = $entry->id;
        $newentry->approved     = false;
        $newentry->timemodified = time();
        $DB->update_record("glossarylv_entries", $newentry);
        $params = array(
            'context' => $context,
            'objectid' => $entry->id
        );
        $event = \mod_glossarylv\event\entry_disapproved::create($params);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\entry_disapproved', $event);
        $this->assertEquals(CONTEXT_MODULE, $event->contextlevel);
        $this->assertEquals($glossarylv->cmid, $event->contextinstanceid);
        $expected = array($course->id, "glossarylv", "disapprove entry",
            "showentry.php?id={$glossarylv->cmid}&amp;eid={$entry->id}", $entry->id, $glossarylv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_entry_viewed() {
        // There is no proper API to call to trigger this event, so what we are
        // doing here is simply making sure that the events returns the right information.

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', array('course' => $course));
        $context = context_module::instance($glossarylv->cmid);

        $glossarylvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossarylv');
        $entry = $glossarylvgenerator->create_content($glossarylv);

        $event = \mod_glossarylv\event\entry_viewed::create(array(
            'objectid' => $entry->id,
            'context' => $context
        ));

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_glossarylv\event\entry_viewed', $event);
        $this->assertEquals(CONTEXT_MODULE, $event->contextlevel);
        $this->assertEquals($glossarylv->cmid, $event->contextinstanceid);
        $expected = array($course->id, "glossarylv", "view entry",
            "showentry.php?eid={$entry->id}", $entry->id, $glossarylv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }
}

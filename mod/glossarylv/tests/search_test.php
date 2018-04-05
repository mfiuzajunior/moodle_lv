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
 * Glossarylv search unit tests.
 *
 * @package     mod_glossarylv
 * @category    test
 * @copyright   2016 David Monllao {@link http://www.davidmonllao.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');
require_once($CFG->dirroot . '/mod/glossarylv/tests/generator/lib.php');

/**
 * Provides the unit tests for glossarylv search.
 *
 * @package     mod_glossarylv
 * @category    test
 * @copyright   2016 David Monllao {@link http://www.davidmonllao.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_glossarylv_search_testcase extends advanced_testcase {

    /**
     * @var string Area id
     */
    protected $entryareaid = null;

    public function setUp() {
        $this->resetAfterTest(true);
        set_config('enableglobalsearch', true);

        // Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
        $search = testable_core_search::instance();

        $this->entryareaid = \core_search\manager::generate_areaid('mod_glossarylv', 'entry');
    }

    /**
     * Availability.
     *
     * @return void
     */
    public function test_search_enabled() {

        $searcharea = \core_search\manager::get_search_area($this->entryareaid);
        list($componentname, $varname) = $searcharea->get_config_var_name();

        // Enabled by default once global search is enabled.
        $this->assertTrue($searcharea->is_enabled());

        set_config($varname . '_enabled', 0, $componentname);
        $this->assertFalse($searcharea->is_enabled());

        set_config($varname . '_enabled', 1, $componentname);
        $this->assertTrue($searcharea->is_enabled());
    }

    /**
     * Indexing contents.
     *
     * @return void
     */
    public function test_entries_indexing() {
        global $DB;

        $searcharea = \core_search\manager::get_search_area($this->entryareaid);
        $this->assertInstanceOf('\mod_glossarylv\search\entry', $searcharea);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, 'student');

        $record = new stdClass();
        $record->course = $course1->id;

        $this->setUser($user1);

        // Approved entries by default glossarylv.
        $glossarylv1 = self::getDataGenerator()->create_module('glossarylv', $record);
        $entry1 = self::getDataGenerator()->get_plugin_generator('mod_glossarylv')->create_content($glossarylv1);
        $entry2 = self::getDataGenerator()->get_plugin_generator('mod_glossarylv')->create_content($glossarylv1);

        // All records.
        $recordset = $searcharea->get_recordset_by_timestamp(0);
        $this->assertTrue($recordset->valid());
        $nrecords = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $doc = $searcharea->get_document($record);
            $this->assertInstanceOf('\core_search\document', $doc);

            // Static caches are working.
            $dbreads = $DB->perf_get_reads();
            $doc = $searcharea->get_document($record);

            // The +1 is because we are not caching glossarylv alias (keywords) as they depend on a single entry.
            $this->assertEquals($dbreads + 1, $DB->perf_get_reads());
            $this->assertInstanceOf('\core_search\document', $doc);
            $nrecords++;
        }
        // If there would be an error/failure in the foreach above the recordset would be closed on shutdown.
        $recordset->close();
        $this->assertEquals(2, $nrecords);

        // The +2 is to prevent race conditions.
        $recordset = $searcharea->get_recordset_by_timestamp(time() + 2);

        // No new records.
        $this->assertFalse($recordset->valid());
        $recordset->close();
    }

    /**
     * Document contents.
     *
     * @return void
     */
    public function test_entries_document() {
        global $DB;

        $searcharea = \core_search\manager::get_search_area($this->entryareaid);

        $user = self::getDataGenerator()->create_user();
        $course1 = self::getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course1->id, 'teacher');

        $record = new stdClass();
        $record->course = $course1->id;

        $this->setUser($user);
        $glossarylv = self::getDataGenerator()->create_module('glossarylv', $record);
        $entry = self::getDataGenerator()->get_plugin_generator('mod_glossarylv')->create_content($glossarylv);
        $entry->course = $glossarylv->course;

        $doc = $searcharea->get_document($entry);
        $this->assertInstanceOf('\core_search\document', $doc);
        $this->assertEquals($entry->id, $doc->get('itemid'));
        $this->assertEquals($course1->id, $doc->get('courseid'));
        $this->assertEquals($user->id, $doc->get('userid'));
        $this->assertEquals($entry->concept, $doc->get('title'));
        $this->assertEquals($entry->definition, $doc->get('content'));
    }

    /**
     * Document accesses.
     *
     * @return void
     */
    public function test_entries_access() {
        global $DB;

        // Returns the instance as long as the component is supported.
        $searcharea = \core_search\manager::get_search_area($this->entryareaid);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'teacher');
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, 'student');

        $record = new stdClass();
        $record->course = $course1->id;

        // Approved entries by default glossarylv, created by teacher.
        $this->setUser($user1);
        $glossarylv1 = self::getDataGenerator()->create_module('glossarylv', $record);
        $teacherapproved = self::getDataGenerator()->get_plugin_generator('mod_glossarylv')->create_content($glossarylv1);
        $teachernotapproved = self::getDataGenerator()->get_plugin_generator('mod_glossarylv')->create_content($glossarylv1, array('approved' => false));

        // Entries need to be approved and created by student.
        $glossarylv2 = self::getDataGenerator()->create_module('glossarylv', $record);
        $this->setUser($user2);
        $studentapproved = self::getDataGenerator()->get_plugin_generator('mod_glossarylv')->create_content($glossarylv2);
        $studentnotapproved = self::getDataGenerator()->get_plugin_generator('mod_glossarylv')->create_content($glossarylv2, array('approved' => false));

        // Activity hidden to students.
        $this->setUser($user1);
        $glossarylv3 = self::getDataGenerator()->create_module('glossarylv', $record);
        $hidden = self::getDataGenerator()->get_plugin_generator('mod_glossarylv')->create_content($glossarylv3);
        set_coursemodule_visible($glossarylv3->cmid, 0);

        $this->setUser($user2);
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($teacherapproved->id));
        $this->assertEquals(\core_search\manager::ACCESS_DENIED, $searcharea->check_access($teachernotapproved->id));
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($studentapproved->id));
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($studentnotapproved->id));
        $this->assertEquals(\core_search\manager::ACCESS_DENIED, $searcharea->check_access($hidden->id));
    }
}

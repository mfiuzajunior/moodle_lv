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
 * mod_glossarylv generator tests
 *
 * @package    mod_glossarylv
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Genarator tests class for mod_glossarylv.
 *
 * @package    mod_glossarylv
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_glossarylv_generator_testcase extends advanced_testcase {

    public function test_create_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('glossarylv', array('course' => $course->id)));
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', array('course' => $course));
        $records = $DB->get_records('glossarylv', array('course' => $course->id), 'id');
        $this->assertCount(1, $records);
        $this->assertTrue(array_key_exists($glossarylv->id, $records));

        $params = array('course' => $course->id, 'name' => 'Another glossarylv');
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', $params);
        $records = $DB->get_records('glossarylv', array('course' => $course->id), 'id');
        $this->assertCount(2, $records);
        $this->assertEquals('Another glossarylv', $records[$glossarylv->id]->name);
    }

    public function test_create_content() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $glossarylv = $this->getDataGenerator()->create_module('glossarylv', array('course' => $course));
        /** @var mod_glossarylv_generator $glossarylvgenerator */
        $glossarylvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossarylv');

        $entry1 = $glossarylvgenerator->create_content($glossarylv);
        $entry2 = $glossarylvgenerator->create_content($glossarylv,
            array('concept' => 'Custom concept', 'tags' => array('Cats', 'mice')), array('alias1', 'alias2'));
        $records = $DB->get_records('glossarylv_entries', array('glossarylvid' => $glossarylv->id), 'id');
        $this->assertCount(2, $records);
        $this->assertEquals($entry1->id, $records[$entry1->id]->id);
        $this->assertEquals($entry2->id, $records[$entry2->id]->id);
        $this->assertEquals('Custom concept', $records[$entry2->id]->concept);
        $this->assertEquals(array('Cats', 'mice'),
            array_values(core_tag_tag::get_item_tags_array('mod_glossarylv', 'glossarylv_entries', $entry2->id)));
        $aliases = $DB->get_records_menu('glossarylv_alias', array('entryid' => $entry2->id), 'id ASC', 'id, alias');
        $this->assertSame(array('alias1', 'alias2'), array_values($aliases));

        // Test adding of category to entry.
        $categories = $DB->get_records('glossarylv_categories', array('glossarylvid' => $glossarylv->id));
        $this->assertCount(0, $categories);
        $entry3 = $glossarylvgenerator->create_content($glossarylv, array('concept' => 'In category'));
        $category1 = $glossarylvgenerator->create_category($glossarylv, array());
        $categories = $DB->get_records('glossarylv_categories', array('glossarylvid' => $glossarylv->id));
        $this->assertCount(1, $categories);
        $category2 = $glossarylvgenerator->create_category($glossarylv, array('name' => 'Some category'), array($entry2, $entry3));
        $categories = $DB->get_records('glossarylv_categories', array('glossarylvid' => $glossarylv->id));
        $this->assertCount(2, $categories);
        $members = $DB->get_records_menu('glossarylv_entries_categ', array('categoryid' => $category2->id), 'id ASC', 'id, entryid');
        $this->assertSame(array($entry2->id, $entry3->id), array_values($members));
    }
}

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
 * Concept fetching and caching tests.
 *
 * @package    mod_glossarylv
 * @category   test
 * @copyright  2014 Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Concept fetching and caching tests.
 *
 * @package    mod_glossarylv
 * @category   test
 * @copyright  2014 Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_glossarylv_concept_cache_testcase extends advanced_testcase {
    /**
     * Test convect fetching.
     */
    public function test_concept_fetching() {
        global $CFG, $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $CFG->glossarylv_linkbydefault = 1;
        $CFG->glossarylv_linkentries = 0;

        // Create a test courses.
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $site = $DB->get_record('course', array('id' => SITEID));

        // Create a glossarylv.
        $glossarylv1a = $this->getDataGenerator()->create_module('glossarylv',
            array('course' => $course1->id, 'mainglossarylv' => 1, 'usedynalink' => 1));
        $glossarylv1b = $this->getDataGenerator()->create_module('glossarylv',
            array('course' => $course1->id, 'mainglossarylv' => 1, 'usedynalink' => 1));
        $glossarylv1c = $this->getDataGenerator()->create_module('glossarylv',
            array('course' => $course1->id, 'mainglossarylv' => 1, 'usedynalink' => 0));
        $glossarylv2 = $this->getDataGenerator()->create_module('glossarylv',
            array('course' => $course2->id, 'mainglossarylv' => 1, 'usedynalink' => 1));
        $glossarylv3 = $this->getDataGenerator()->create_module('glossarylv',
            array('course' => $site->id, 'mainglossarylv' => 1, 'usedynalink' => 1, 'globalglossarylv' => 1));

        /** @var mod_glossarylv_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_glossarylv');
        $entry1a1 = $generator->create_content($glossarylv1a, array('concept' => 'first', 'usedynalink' => 1), array('prvni', 'erste'));
        $entry1a2 = $generator->create_content($glossarylv1a, array('concept' => 'A&B', 'usedynalink' => 1));
        $entry1a3 = $generator->create_content($glossarylv1a, array('concept' => 'neee', 'usedynalink' => 0));
        $entry1b1 = $generator->create_content($glossarylv1b, array('concept' => 'second', 'usedynalink' => 1));
        $entry1c1 = $generator->create_content($glossarylv1c, array('concept' => 'third', 'usedynalink' => 1));
        $entry31 = $generator->create_content($glossarylv3, array('concept' => 'global', 'usedynalink' => 1), array('globalni'));

        $cat1 = $generator->create_category($glossarylv1a, array('name' => 'special'), array($entry1a1, $entry1a2));

        \mod_glossarylv\local\concept_cache::reset_caches();

        $concepts1 = \mod_glossarylv\local\concept_cache::get_concepts($course1->id);
        $this->assertCount(3, $concepts1[0]);
        $this->arrayHasKey($concepts1[0], $glossarylv1a->id);
        $this->arrayHasKey($concepts1[0], $glossarylv1b->id);
        $this->arrayHasKey($concepts1[0], $glossarylv3->id);
        $this->assertCount(3, $concepts1[1]);
        $this->arrayHasKey($concepts1[1], $glossarylv1a->id);
        $this->arrayHasKey($concepts1[1], $glossarylv1b->id);
        $this->arrayHasKey($concepts1[0], $glossarylv3->id);
        $this->assertCount(5, $concepts1[1][$glossarylv1a->id]);
        foreach($concepts1[1][$glossarylv1a->id] as $concept) {
            $this->assertSame(array('id', 'glossarylvid', 'concept', 'casesensitive', 'category', 'fullmatch'), array_keys((array)$concept));
            if ($concept->concept === 'first') {
                $this->assertEquals($entry1a1->id, $concept->id);
                $this->assertEquals($glossarylv1a->id, $concept->glossarylvid);
                $this->assertEquals(0, $concept->category);
            } else if ($concept->concept === 'prvni') {
                $this->assertEquals($entry1a1->id, $concept->id);
                $this->assertEquals($glossarylv1a->id, $concept->glossarylvid);
                $this->assertEquals(0, $concept->category);
            } else if ($concept->concept === 'erste') {
                $this->assertEquals($entry1a1->id, $concept->id);
                $this->assertEquals($glossarylv1a->id, $concept->glossarylvid);
                $this->assertEquals(0, $concept->category);
            } else if ($concept->concept === 'A&amp;B') {
                $this->assertEquals($entry1a2->id, $concept->id);
                $this->assertEquals($glossarylv1a->id, $concept->glossarylvid);
                $this->assertEquals(0, $concept->category);
            } else if ($concept->concept === 'special') {
                $this->assertEquals($cat1->id, $concept->id);
                $this->assertEquals($glossarylv1a->id, $concept->glossarylvid);
                $this->assertEquals(1, $concept->category);
            } else {
                $this->fail('Unexpected concept: ' . $concept->concept);
            }
        }
        $this->assertCount(1, $concepts1[1][$glossarylv1b->id]);
        foreach($concepts1[1][$glossarylv1b->id] as $concept) {
            $this->assertSame(array('id', 'glossarylvid', 'concept', 'casesensitive', 'category', 'fullmatch'), array_keys((array)$concept));
            if ($concept->concept === 'second') {
                $this->assertEquals($entry1b1->id, $concept->id);
                $this->assertEquals($glossarylv1b->id, $concept->glossarylvid);
                $this->assertEquals(0, $concept->category);
            } else {
                $this->fail('Unexpected concept: ' . $concept->concept);
            }
        }
        $this->assertCount(2, $concepts1[1][$glossarylv3->id]);
        foreach($concepts1[1][$glossarylv3->id] as $concept) {
            $this->assertSame(array('id', 'glossarylvid', 'concept', 'casesensitive', 'category', 'fullmatch'), array_keys((array)$concept));
            if ($concept->concept === 'global') {
                $this->assertEquals($entry31->id, $concept->id);
                $this->assertEquals($glossarylv3->id, $concept->glossarylvid);
                $this->assertEquals(0, $concept->category);
            } else if ($concept->concept === 'globalni') {
                $this->assertEquals($entry31->id, $concept->id);
                $this->assertEquals($glossarylv3->id, $concept->glossarylvid);
                $this->assertEquals(0, $concept->category);
            } else {
                $this->fail('Unexpected concept: ' . $concept->concept);
            }
        }

        $concepts3 = \mod_glossarylv\local\concept_cache::get_concepts($site->id);
        $this->assertCount(1, $concepts3[0]);
        $this->arrayHasKey($concepts3[0], $glossarylv3->id);
        $this->assertCount(1, $concepts3[1]);
        $this->arrayHasKey($concepts3[0], $glossarylv3->id);
        foreach($concepts3[1][$glossarylv3->id] as $concept) {
            $this->assertSame(array('id', 'glossarylvid', 'concept', 'casesensitive', 'category', 'fullmatch'), array_keys((array)$concept));
            if ($concept->concept === 'global') {
                $this->assertEquals($entry31->id, $concept->id);
                $this->assertEquals($glossarylv3->id, $concept->glossarylvid);
                $this->assertEquals(0, $concept->category);
            } else if ($concept->concept === 'globalni') {
                $this->assertEquals($entry31->id, $concept->id);
                $this->assertEquals($glossarylv3->id, $concept->glossarylvid);
                $this->assertEquals(0, $concept->category);
            } else {
                $this->fail('Unexpected concept: ' . $concept->concept);
            }
        }

        $concepts2 = \mod_glossarylv\local\concept_cache::get_concepts($course2->id);
        $this->assertEquals($concepts3, $concepts2);

        // Test uservisible flag.
        set_config('enableavailability', 1);
        $glossarylv1d = $this->getDataGenerator()->create_module('glossarylv',
                array('course' => $course1->id, 'mainglossarylv' => 1, 'usedynalink' => 1,
                'availability' => json_encode(\core_availability\tree::get_root_json(
                        array(\availability_group\condition::get_json())))));
        $entry1d1 = $generator->create_content($glossarylv1d, array('concept' => 'membersonly', 'usedynalink' => 1));
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);
        \mod_glossarylv\local\concept_cache::reset_caches();
        $concepts1 = \mod_glossarylv\local\concept_cache::get_concepts($course1->id);
        $this->assertCount(4, $concepts1[0]);
        $this->assertCount(4, $concepts1[1]);
        $this->setUser($user);
        course_modinfo::clear_instance_cache();
        \mod_glossarylv\local\concept_cache::reset_caches();
        $concepts1 = \mod_glossarylv\local\concept_cache::get_concepts($course1->id);
        $this->assertCount(3, $concepts1[0]);
        $this->assertCount(3, $concepts1[1]);
    }
}

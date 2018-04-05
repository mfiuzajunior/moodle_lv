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
 * mod_glossarylv data generator.
 *
 * @package    mod_glossarylv
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mod_glossarylv data generator class.
 *
 * @package    mod_glossarylv
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_glossarylv_generator extends testing_module_generator {

    /**
     * @var int keep track of how many entries have been created.
     */
    protected $entrycount = 0;

    /**
     * @var int keep track of how many entries have been created.
     */
    protected $categorycount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->entrycount = 0;
        $this->categorycount = 0;
        parent::reset();
    }

    public function create_instance($record = null, array $options = null) {
        global $CFG;

        // Add default values for glossarylv.
        $record = (array)$record + array(
            'globalglossarylv' => 0,
            'mainglossarylv' => 0,
            'defaultapproval' => $CFG->glossarylv_defaultapproval,
            'allowduplicatedentries' => $CFG->glossarylv_dupentries,
            'allowcomments' => $CFG->glossarylv_allowcomments,
            'usedynalink' => $CFG->glossarylv_linkbydefault,
            'displayformat' => 'dictionary',
            'approvaldisplayformat' => 'default',
            'entbypage' => !empty($CFG->glossarylv_entbypage) ? $CFG->glossarylv_entbypage : 10,
            'showalphabet' => 1,
            'showall' => 1,
            'showspecial' => 1,
            'allowprintview' => 1,
            'rsstype' => 0,
            'rssarticles' => 0,
            'grade' => 100,
            'assessed' => 0,
        );

        return parent::create_instance($record, (array)$options);
    }

    public function create_category($glossarylv, $record = array(), $entries = array()) {
        global $CFG, $DB;
        $this->categorycount++;
        $record = (array)$record + array(
            'name' => 'Glossarylv category '.$this->categorycount,
            'usedynalink' => $CFG->glossarylv_linkbydefault,
        );
        $record['glossarylvid'] = $glossarylv->id;

        $id = $DB->insert_record('glossarylv_categories', $record);

        if ($entries) {
            foreach ($entries as $entry) {
                $ce = new stdClass();
                $ce->categoryid = $id;
                $ce->entryid = $entry->id;
                $DB->insert_record('glossarylv_entries_categ', $ce);
            }
        }

        return $DB->get_record('glossarylv_categories', array('id' => $id), '*', MUST_EXIST);
    }

    public function create_content($glossarylv, $record = array(), $aliases = array()) {
        global $DB, $USER, $CFG;
        $this->entrycount++;
        $now = time();
        $record = (array)$record + array(
            'glossarylvid' => $glossarylv->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'userid' => $USER->id,
            'concept' => 'Glossarylv entry '.$this->entrycount,
            'definition' => 'Definition of glossarylv entry '.$this->entrycount,
            'definitionformat' => FORMAT_MOODLE,
            'definitiontrust' => 0,
            'usedynalink' => $CFG->glossarylv_linkentries,
            'casesensitive' => $CFG->glossarylv_casesensitive,
            'fullmatch' => $CFG->glossarylv_fullmatch
        );
        if (!isset($record['teacherentry']) || !isset($record['approved'])) {
            $context = context_module::instance($glossarylv->cmid);
            if (!isset($record['teacherentry'])) {
                $record['teacherentry'] = has_capability('mod/glossarylv:manageentries', $context, $record['userid']);
            }
            if (!isset($record['approved'])) {
                $defaultapproval = $glossarylv->defaultapproval;
                $record['approved'] = ($defaultapproval || has_capability('mod/glossarylv:approve', $context));
            }
        }

        $id = $DB->insert_record('glossarylv_entries', $record);

        if ($aliases) {
            foreach ($aliases as $alias) {
                $ar = new stdClass();
                $ar->entryid = $id;
                $ar->alias = $alias;
                $DB->insert_record('glossarylv_alias', $ar);
            }
        }

        if (array_key_exists('tags', $record)) {
            $tags = is_array($record['tags']) ? $record['tags'] : preg_split('/,/', $record['tags']);

            core_tag_tag::set_item_tags('mod_glossarylv', 'glossarylv_entries', $id,
                context_module::instance($glossarylv->cmid), $tags);
        }

        return $DB->get_record('glossarylv_entries', array('id' => $id), '*', MUST_EXIST);
    }
}

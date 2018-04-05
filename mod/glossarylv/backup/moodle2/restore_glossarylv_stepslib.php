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
 * @package mod_glossarylv
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_glossarylv_activity_task
 */

/**
 * Structure step to restore one glossarylv activity
 */
class restore_glossarylv_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('glossarylv', '/activity/glossarylv');
        $paths[] = new restore_path_element('glossarylv_category', '/activity/glossarylv/categories/category');
        if ($userinfo) {
            $paths[] = new restore_path_element('glossarylv_entry', '/activity/glossarylv/entries/entry');
            $paths[] = new restore_path_element('glossarylv_entry_tag', '/activity/glossarylv/entriestags/tag');
            $paths[] = new restore_path_element('glossarylv_alias', '/activity/glossarylv/entries/entry/aliases/alias');
            $paths[] = new restore_path_element('glossarylv_rating', '/activity/glossarylv/entries/entry/ratings/rating');
            $paths[] = new restore_path_element('glossarylv_category_entry',
                                                '/activity/glossarylv/categories/category/category_entries/category_entry');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_glossarylv($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->assesstimestart = $this->apply_date_offset($data->assesstimestart);
        $data->assesstimefinish = $this->apply_date_offset($data->assesstimefinish);
        if ($data->scale < 0) { // scale found, get mapping
            $data->scale = -($this->get_mappingid('scale', abs($data->scale)));
        }
        $formats = get_list_of_plugins('mod/glossarylv/formats'); // Check format
        if (!in_array($data->displayformat, $formats)) {
            $data->displayformat = 'dictionary';
        }
        if (!empty($data->mainglossarylv) and $data->mainglossarylv == 1 and
            $DB->record_exists('glossarylv', array('mainglossarylv' => 1, 'course' => $this->get_courseid()))) {
            // Only allow one main glossarylv in the course
            $data->mainglossarylv = 0;
        }

        // insert the glossarylv record
        $newitemid = $DB->insert_record('glossarylv', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_glossarylv_entry($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->glossarylvid = $this->get_new_parentid('glossarylv');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->sourceglossarylvid = $this->get_mappingid('glossarylv', $data->sourceglossarylvid);

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // insert the entry record
        $newitemid = $DB->insert_record('glossarylv_entries', $data);
        $this->set_mapping('glossarylv_entry', $oldid, $newitemid, true); // childs and files by itemname
    }

    protected function process_glossarylv_alias($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->entryid = $this->get_new_parentid('glossarylv_entry');
        $data->alias =  $data->alias_text;
        $newitemid = $DB->insert_record('glossarylv_alias', $data);
    }

    protected function process_glossarylv_rating($data) {
        global $DB;

        $data = (object)$data;

        // Cannot use ratings API, cause, it's missing the ability to specify times (modified/created)
        $data->contextid = $this->task->get_contextid();
        $data->itemid    = $this->get_new_parentid('glossarylv_entry');
        if ($data->scaleid < 0) { // scale found, get mapping
            $data->scaleid = -($this->get_mappingid('scale', abs($data->scaleid)));
        }
        $data->rating = $data->value;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Make sure that we have both component and ratingarea set. These were added in 2.1.
        // Prior to that all ratings were for entries so we know what to set them too.
        if (empty($data->component)) {
            $data->component = 'mod_glossarylv';
        }
        if (empty($data->ratingarea)) {
            $data->ratingarea = 'entry';
        }

        $newitemid = $DB->insert_record('rating', $data);
    }

    protected function process_glossarylv_entry_tag($data) {
        $data = (object)$data;

        if (!core_tag_tag::is_enabled('mod_glossarylv', 'glossarylv_entries')) { // Tags disabled in server, nothing to process.
            return;
        }

        $tag = $data->rawname;
        if (!$itemid = $this->get_mappingid('glossarylv_entry', $data->itemid)) {
            // Some orphaned tag, we could not find the glossarylv entry for it - ignore.
            return;
        }

        $context = context_module::instance($this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_glossarylv', 'glossarylv_entries', $itemid, $context, $tag);
    }

    protected function process_glossarylv_category($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->glossarylvid = $this->get_new_parentid('glossarylv');
        $newitemid = $DB->insert_record('glossarylv_categories', $data);
        $this->set_mapping('glossarylv_category', $oldid, $newitemid);
    }

    protected function process_glossarylv_category_entry($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->categoryid = $this->get_new_parentid('glossarylv_category');
        $data->entryid    = $this->get_mappingid('glossarylv_entry', $data->entryid);
        $newitemid = $DB->insert_record('glossarylv_entries_categ', $data);
    }

    protected function after_execute() {
        // Add glossarylv related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_glossarylv', 'intro', null);
        // Add entries related files, matching by itemname (glossarylv_entry)
        $this->add_related_files('mod_glossarylv', 'entry', 'glossarylv_entry');
        $this->add_related_files('mod_glossarylv', 'attachment', 'glossarylv_entry');
    }
}

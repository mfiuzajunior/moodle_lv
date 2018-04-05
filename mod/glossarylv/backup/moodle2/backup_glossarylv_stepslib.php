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
 * Define all the backup steps that will be used by the backup_glossarylv_activity_task
 */

/**
 * Define the complete glossarylv structure for backup, with file and id annotations
 */
class backup_glossarylv_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $glossarylv = new backup_nested_element('glossarylv', array('id'), array(
            'name', 'intro', 'introformat', 'allowduplicatedentries', 'displayformat',
            'mainglossarylv', 'showspecial', 'showalphabet', 'showall',
            'allowcomments', 'allowprintview', 'usedynalink', 'defaultapproval',
            'globalglossarylv', 'entbypage', 'editalways', 'rsstype',
            'rssarticles', 'assessed', 'assesstimestart', 'assesstimefinish',
            'scale', 'timecreated', 'timemodified', 'completionentries'));

        $entries = new backup_nested_element('entries');

        $entry = new backup_nested_element('entry', array('id'), array(
            'userid', 'concept', 'definition', 'definitionformat',
            'definitiontrust', 'attachment', 'timecreated', 'timemodified',
            'teacherentry', 'sourceglossarylvid', 'usedynalink', 'casesensitive',
            'fullmatch', 'approved'));

        $tags = new backup_nested_element('entriestags');
        $tag = new backup_nested_element('tag', array('id'), array('itemid', 'rawname'));

        $aliases = new backup_nested_element('aliases');

        $alias = new backup_nested_element('alias', array('id'), array(
            'alias_text'));

        $ratings = new backup_nested_element('ratings');

        $rating = new backup_nested_element('rating', array('id'), array(
            'component', 'ratingarea', 'scaleid', 'value', 'userid', 'timecreated', 'timemodified'));

        $categories = new backup_nested_element('categories');

        $category = new backup_nested_element('category', array('id'), array(
            'name', 'usedynalink'));

        $categoryentries = new backup_nested_element('category_entries');

        $categoryentry = new backup_nested_element('category_entry', array('id'), array(
            'entryid'));

        // Build the tree
        $glossarylv->add_child($entries);
        $entries->add_child($entry);

        $glossarylv->add_child($tags);
        $tags->add_child($tag);

        $entry->add_child($aliases);
        $aliases->add_child($alias);

        $entry->add_child($ratings);
        $ratings->add_child($rating);

        $glossarylv->add_child($categories);
        $categories->add_child($category);

        $category->add_child($categoryentries);
        $categoryentries->add_child($categoryentry);

        // Define sources
        $glossarylv->set_source_table('glossarylv', array('id' => backup::VAR_ACTIVITYID));

        $category->set_source_table('glossarylv_categories', array('glossarylvid' => backup::VAR_PARENTID));

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $entry->set_source_table('glossarylv_entries', array('glossarylvid' => backup::VAR_PARENTID));

            $alias->set_source_table('glossarylv_alias', array('entryid' => backup::VAR_PARENTID));
            $alias->set_source_alias('alias', 'alias_text');

            $rating->set_source_table('rating', array('contextid'  => backup::VAR_CONTEXTID,
                                                      'itemid'     => backup::VAR_PARENTID,
                                                      'component'  => backup_helper::is_sqlparam('mod_glossarylv'),
                                                      'ratingarea' => backup_helper::is_sqlparam('entry')));
            $rating->set_source_alias('rating', 'value');

            $categoryentry->set_source_table('glossarylv_entries_categ', array('categoryid' => backup::VAR_PARENTID));

            if (core_tag_tag::is_enabled('mod_glossarylv', 'glossarylv_entries')) {
                $tag->set_source_sql('SELECT t.id, ti.itemid, t.rawname
                                        FROM {tag} t
                                        JOIN {tag_instance} ti ON ti.tagid = t.id
                                       WHERE ti.itemtype = ?
                                         AND ti.component = ?
                                         AND ti.contextid = ?', array(
                    backup_helper::is_sqlparam('glossarylv_entries'),
                    backup_helper::is_sqlparam('mod_glossarylv'),
                    backup::VAR_CONTEXTID));
            }
        }

        // Define id annotations
        $glossarylv->annotate_ids('scale', 'scale');

        $entry->annotate_ids('user', 'userid');

        $rating->annotate_ids('scale', 'scaleid');

        $rating->annotate_ids('user', 'userid');

        // Define file annotations
        $glossarylv->annotate_files('mod_glossarylv', 'intro', null); // This file area hasn't itemid

        $entry->annotate_files('mod_glossarylv', 'entry', 'id');
        $entry->annotate_files('mod_glossarylv', 'attachment', 'id');

        // Return the root element (glossarylv), wrapped into standard activity structure
        return $this->prepare_activity_structure($glossarylv);
    }
}

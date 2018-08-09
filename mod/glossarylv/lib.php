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
 * Library of functions and constants for module glossarylv
 * (replace glossarylv with the name of your module and delete this line)
 *
 * @package   mod_glossarylv
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . '/completionlib.php');

define("GLOSSARYLV_SHOW_ALL_CATEGORIES", 0);
define("GLOSSARYLV_SHOW_NOT_CATEGORISED", -1);

define("GLOSSARYLV_NO_VIEW", -1);
define("GLOSSARYLV_STANDARD_VIEW", 0);
define("GLOSSARYLV_CATEGORY_VIEW", 1);
define("GLOSSARYLV_DATE_VIEW", 2);
define("GLOSSARYLV_AUTHOR_VIEW", 3);
define("GLOSSARYLV_ADDENTRY_VIEW", 4);
define("GLOSSARYLV_IMPORT_VIEW", 5);
define("GLOSSARYLV_EXPORT_VIEW", 6);
define("GLOSSARYLV_APPROVAL_VIEW", 7);

// Glossarylv tabs.
define('GLOSSARYLV_STANDARD', 'standard');
define('GLOSSARYLV_AUTHOR', 'author');
define('GLOSSARYLV_CATEGORY', 'category');
define('GLOSSARYLV_DATE', 'date');

// Glossarylv displayformats.
define('GLOSSARYLV_CONTINUOUS', 'continuous');
define('GLOSSARYLV_DICTIONARY', 'dictionary');
define('GLOSSARYLV_FULLWITHOUTAUTHOR', 'fullwithoutauthor');

// @lvs Classes LVs
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
use uab\ifce\lvs\moodle2\business\GlossarioLv;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;

// Loader dos LVs
require_once($CFG->dirroot.'/blocks/lvs/biblioteca/lib.php');
//---

/// STANDARD FUNCTIONS ///////////////////////////////////////////////////////////
/**
 * @global object
 * @param object $glossarylv
 * @return int
 */
function glossarylv_add_instance($glossarylv) {
    global $DB;
/// Given an object containing all the necessary data,
/// (defined by the form in mod_form.php) this function
/// will create a new instance and return the id number
/// of the new instance.

    if (empty($glossarylv->ratingtime) or empty($glossarylv->assessed)) {
        $glossarylv->assesstimestart  = 0;
        $glossarylv->assesstimefinish = 0;
    }

    if (empty($glossarylv->globalglossarylv) ) {
        $glossarylv->globalglossarylv = 0;
    }

    if (!has_capability('mod/glossarylv:manageentries', context_system::instance())) {
        $glossarylv->globalglossarylv = 0;
    }

    $glossarylv->timecreated  = time();
    $glossarylv->timemodified = $glossarylv->timecreated;

    //Check displayformat is a valid one
    $formats = get_list_of_plugins('mod/glossarylv/formats','TEMPLATE');
    if (!in_array($glossarylv->displayformat, $formats)) {
        print_error('unknowformat', '', '', $glossarylv->displayformat);
    }

    $returnid = $DB->insert_record("glossarylv", $glossarylv);
    $glossarylv->id = $returnid;
    glossarylv_grade_item_update($glossarylv);

    $completiontimeexpected = !empty($glossarylv->completionexpected) ? $glossarylv->completionexpected : null;
    \core_completion\api::update_completion_date_event($glossarylv->coursemodule,
        'glossarylv', $glossarylv->id, $completiontimeexpected);

    return $returnid;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @global object
 * @param object $glossarylv
 * @return bool
 */
function glossarylv_update_instance($glossarylv) {
    global $CFG, $DB;

    if (empty($glossarylv->globalglossarylv)) {
        $glossarylv->globalglossarylv = 0;
    }

    if (!has_capability('mod/glossarylv:manageentries', context_system::instance())) {
        // keep previous
        unset($glossarylv->globalglossarylv);
    }

    $glossarylv->timemodified = time();
    $glossarylv->id           = $glossarylv->instance;

    if (empty($glossarylv->ratingtime) or empty($glossarylv->assessed)) {
        $glossarylv->assesstimestart  = 0;
        $glossarylv->assesstimefinish = 0;
    }

    //Check displayformat is a valid one
    $formats = get_list_of_plugins('mod/glossarylv/formats','TEMPLATE');
    if (!in_array($glossarylv->displayformat, $formats)) {
        print_error('unknowformat', '', '', $glossarylv->displayformat);
    }

    $DB->update_record("glossarylv", $glossarylv);
    if ($glossarylv->defaultapproval) {
        $DB->execute("UPDATE {glossarylv_entries} SET approved = 1 where approved <> 1 and glossarylvid = ?", array($glossarylv->id));
    }
    glossarylv_grade_item_update($glossarylv);

    $completiontimeexpected = !empty($glossarylv->completionexpected) ? $glossarylv->completionexpected : null;
    \core_completion\api::update_completion_date_event($glossarylv->coursemodule,
        'glossarylv', $glossarylv->id, $completiontimeexpected);

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id glossarylv id
 * @return bool success
 */
function glossarylv_delete_instance($id) {
    global $DB, $CFG;

    if (!$glossarylv = $DB->get_record('glossarylv', array('id'=>$id))) {
        return false;
    }

    if (!$cm = get_coursemodule_from_instance('glossarylv', $id)) {
        return false;
    }

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        return false;
    }

    $fs = get_file_storage();

    if ($glossarylv->mainglossarylv) {
        // unexport entries
        $sql = "SELECT ge.id, ge.sourceglossarylvid, cm.id AS sourcecmid
                  FROM {glossarylv_entries} ge
                  JOIN {modules} m ON m.name = 'glossarylv'
                  JOIN {course_modules} cm ON (cm.module = m.id AND cm.instance = ge.sourceglossarylvid)
                 WHERE ge.glossarylvid = ? AND ge.sourceglossarylvid > 0";

        if ($exported = $DB->get_records_sql($sql, array($id))) {
            foreach ($exported as $entry) {
                $entry->glossarylvid = $entry->sourceglossarylvid;
                $entry->sourceglossarylvid = 0;
                $newcontext = context_module::instance($entry->sourcecmid);
                if ($oldfiles = $fs->get_area_files($context->id, 'mod_glossarylv', 'attachment', $entry->id)) {
                    foreach ($oldfiles as $oldfile) {
                        $file_record = new stdClass();
                        $file_record->contextid = $newcontext->id;
                        $fs->create_file_from_storedfile($file_record, $oldfile);
                    }
                    $fs->delete_area_files($context->id, 'mod_glossarylv', 'attachment', $entry->id);
                    $entry->attachment = '1';
                } else {
                    $entry->attachment = '0';
                }
                $DB->update_record('glossarylv_entries', $entry);
            }
        }
    } else {
        // move exported entries to main glossarylv
        $sql = "UPDATE {glossarylv_entries}
                   SET sourceglossarylvid = 0
                 WHERE sourceglossarylvid = ?";
        $DB->execute($sql, array($id));
    }

    // Delete any dependent records
    $entry_select = "SELECT id FROM {glossarylv_entries} WHERE glossarylvid = ?";
    $DB->delete_records_select('comments', "contextid=? AND commentarea=? AND itemid IN ($entry_select)", array($id, 'glossarylv_entry', $context->id));
    $DB->delete_records_select('glossarylv_alias',    "entryid IN ($entry_select)", array($id));

    $category_select = "SELECT id FROM {glossarylv_categories} WHERE glossarylvid = ?";
    $DB->delete_records_select('glossarylv_entries_categ', "categoryid IN ($category_select)", array($id));
    $DB->delete_records('glossarylv_categories', array('glossarylvid'=>$id));
    $DB->delete_records('glossarylv_entries', array('glossarylvid'=>$id));

    // delete all files
    $fs->delete_area_files($context->id);

    glossarylv_grade_item_delete($glossarylv);

    \core_completion\api::update_completion_date_event($cm->id, 'glossarylv', $glossarylv->id, null);

    $DB->delete_records('glossarylv', array('id'=>$id));

    // Reset caches.
    \mod_glossarylv\local\concept_cache::reset_glossarylv($glossarylv);

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $glossarylv
 * @return object|null
 */
function glossarylv_user_outline($course, $user, $mod, $glossarylv) {
    global $CFG;

    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'glossarylv', $glossarylv->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    if ($entries = glossarylv_get_user_entries($glossarylv->id, $user->id)) {
        $result = new stdClass();
        $result->info = count($entries) . ' ' . get_string("entries", "glossarylv");

        $lastentry = array_pop($entries);
        $result->time = $lastentry->timemodified;

        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;

        //datesubmitted == time created. dategraded == time modified or time overridden
        //if grade was last modified by the user themselves use date graded. Otherwise use date submitted
        //TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $result->time = $grade->dategraded;
        } else {
            $result->time = $grade->datesubmitted;
        }

        return $result;
    }
    return NULL;
}

/**
 * @global object
 * @param int $glossarylvid
 * @param int $userid
 * @return array
 */
function glossarylv_get_user_entries($glossarylvid, $userid) {
/// Get all the entries for a user in a glossarylv
    global $DB;

    return $DB->get_records_sql("SELECT e.*, u.firstname, u.lastname, u.email, u.picture
                                   FROM {glossarylv} g, {glossarylv_entries} e, {user} u
                             WHERE g.id = ?
                               AND e.glossarylvid = g.id
                               AND e.userid = ?
                               AND e.userid = u.id
                          ORDER BY e.timemodified ASC", array($glossarylvid, $userid));
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $glossarylv
 */
function glossarylv_user_complete($course, $user, $mod, $glossarylv) {
    global $CFG, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'glossarylv', $glossarylv->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
        }
    }

    if ($entries = glossarylv_get_user_entries($glossarylv->id, $user->id)) {
        echo '<table width="95%" border="0"><tr><td>';
        foreach ($entries as $entry) {
            $cm = get_coursemodule_from_instance("glossarylv", $glossarylv->id, $course->id);
            glossarylv_print_entry($course, $cm, $glossarylv, $entry,"","",0);
            echo '<p>';
        }
        echo '</td></tr></table>';
    }
}

/**
 * Returns all glossarylv entries since a given time for specified glossarylv
 *
 * @param array $activities sequentially indexed array of objects
 * @param int   $index
 * @param int   $timestart
 * @param int   $courseid
 * @param int   $cmid
 * @param int   $userid defaults to 0
 * @param int   $groupid defaults to 0
 * @return void adds items into $activities and increases $index
 */
function glossarylv_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0) {
    global $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);
    $cm = $modinfo->cms[$cmid];
    $context = context_module::instance($cm->id);

    if (!$cm->uservisible) {
        return;
    }

    $viewfullnames = has_capability('moodle/site:viewfullnames', $context);
    // Groups are not yet supported for glossarylv. See MDL-10728 .
    /*
    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $groupmode = groups_get_activity_groupmode($cm, $course);
     */

    $params['timestart'] = $timestart;

    if ($userid) {
        $userselect = "AND u.id = :userid";
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $approvedselect = "";
    if (!has_capability('mod/glossarylv:approve', $context)) {
        $approvedselect = " AND ge.approved = 1 ";
    }

    $params['timestart'] = $timestart;
    $params['glossarylvid'] = $cm->instance;

    $ufields = user_picture::fields('u', null, 'userid');
    $entries = $DB->get_records_sql("
              SELECT ge.id AS entryid, ge.glossarylvid, ge.concept, ge.definition, ge.approved,
                     ge.timemodified, $ufields
                FROM {glossarylv_entries} ge
                JOIN {user} u ON u.id = ge.userid
                     $groupjoin
               WHERE ge.timemodified > :timestart
                 AND ge.glossarylvid = :glossarylvid
                     $approvedselect
                     $userselect
                     $groupselect
            ORDER BY ge.timemodified ASC", $params);

    if (!$entries) {
        return;
    }

    foreach ($entries as $entry) {
        // Groups are not yet supported for glossarylv. See MDL-10728 .
        /*
        $usersgroups = null;
        if ($entry->userid != $USER->id) {
            if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
                if (is_null($usersgroups)) {
                    $usersgroups = groups_get_all_groups($course->id, $entry->userid, $cm->groupingid);
                    if (is_array($usersgroups)) {
                        $usersgroups = array_keys($usersgroups);
                    } else {
                        $usersgroups = array();
                    }
                }
                if (!array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }
         */

        $tmpactivity                       = new stdClass();
        $tmpactivity->user                 = user_picture::unalias($entry, null, 'userid');
        $tmpactivity->user->fullname       = fullname($tmpactivity->user, $viewfullnames);
        $tmpactivity->type                 = 'glossarylv';
        $tmpactivity->cmid                 = $cm->id;
        $tmpactivity->glossarylvid           = $entry->glossarylvid;
        $tmpactivity->name                 = format_string($cm->name, true);
        $tmpactivity->sectionnum           = $cm->sectionnum;
        $tmpactivity->timestamp            = $entry->timemodified;
        $tmpactivity->content              = new stdClass();
        $tmpactivity->content->entryid     = $entry->entryid;
        $tmpactivity->content->concept     = $entry->concept;
        $tmpactivity->content->definition  = $entry->definition;
        $tmpactivity->content->approved    = $entry->approved;

        $activities[$index++] = $tmpactivity;
    }

    return true;
}

/**
 * Outputs the glossarylv entry indicated by $activity
 *
 * @param object $activity      the activity object the glossarylv resides in
 * @param int    $courseid      the id of the course the glossarylv resides in
 * @param bool   $detail        not used, but required for compatibilty with other modules
 * @param int    $modnames      not used, but required for compatibilty with other modules
 * @param bool   $viewfullnames not used, but required for compatibilty with other modules
 * @return void
 */
function glossarylv_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $OUTPUT;

    echo html_writer::start_tag('div', array('class'=>'glossarylv-activity clearfix'));
    if (!empty($activity->user)) {
        echo html_writer::tag('div', $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid)),
            array('class' => 'glossarylv-activity-picture'));
    }

    echo html_writer::start_tag('div', array('class'=>'glossarylv-activity-content'));
    echo html_writer::start_tag('div', array('class'=>'glossarylv-activity-entry'));

    if (isset($activity->content->approved) && !$activity->content->approved) {
        $urlparams = array('g' => $activity->glossarylvid, 'mode' => 'approval', 'hook' => $activity->content->concept);
        $class = array('class' => 'dimmed_text');
    } else {
        $urlparams = array('g' => $activity->glossarylvid, 'mode' => 'entry', 'hook' => $activity->content->entryid);
        $class = array();
    }
    echo html_writer::link(new moodle_url('/mod/glossarylv/view.php', $urlparams),
            strip_tags($activity->content->concept), $class);
    echo html_writer::end_tag('div');

    $url = new moodle_url('/user/view.php', array('course'=>$courseid, 'id'=>$activity->user->id));
    $name = $activity->user->fullname;
    $link = html_writer::link($url, $name, $class);

    echo html_writer::start_tag('div', array('class'=>'user'));
    echo $link .' - '. userdate($activity->timestamp);
    echo html_writer::end_tag('div');

    echo html_writer::end_tag('div');

    echo html_writer::end_tag('div');
    return;
}
/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in glossarylv activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @global object
 * @global object
 * @global object
 * @param object $course
 * @param object $viewfullnames
 * @param int $timestart
 * @return bool
 */
function glossarylv_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT, $PAGE;

    //TODO: use timestamp in approved field instead of changing timemodified when approving in 2.0
    if (!defined('GLOSSARYLV_RECENT_ACTIVITY_LIMIT')) {
        define('GLOSSARYLV_RECENT_ACTIVITY_LIMIT', 50);
    }
    $modinfo = get_fast_modinfo($course);
    $ids = array();

    foreach ($modinfo->cms as $cm) {
        if ($cm->modname != 'glossarylv') {
            continue;
        }
        if (!$cm->uservisible) {
            continue;
        }
        $ids[$cm->instance] = $cm->id;
    }

    if (!$ids) {
        return false;
    }

    // generate list of approval capabilities for all glossaries in the course.
    $approvals = array();
    foreach ($ids as $glinstanceid => $glcmid) {
        $context = context_module::instance($glcmid);
        if (has_capability('mod/glossarylv:view', $context)) {
            // get records glossarylv entries that are approved if user has no capability to approve entries.
            if (has_capability('mod/glossarylv:approve', $context)) {
                $approvals[] = ' ge.glossarylvid = :glsid'.$glinstanceid.' ';
            } else {
                $approvals[] = ' (ge.approved = 1 AND ge.glossarylvid = :glsid'.$glinstanceid.') ';
            }
            $params['glsid'.$glinstanceid] = $glinstanceid;
        }
    }

    if (count($approvals) == 0) {
        return false;
    }
    $selectsql = 'SELECT ge.id, ge.concept, ge.approved, ge.timemodified, ge.glossarylvid,
                                        '.user_picture::fields('u',null,'userid');
    $countsql = 'SELECT COUNT(*)';

    $joins = array(' FROM {glossarylv_entries} ge ');
    $joins[] = 'JOIN {user} u ON u.id = ge.userid ';
    $fromsql = implode($joins, "\n");

    $params['timestart'] = $timestart;
    $clausesql = ' WHERE ge.timemodified > :timestart ';

    if (count($approvals) > 0) {
        $approvalsql = 'AND ('. implode($approvals, ' OR ') .') ';
    } else {
        $approvalsql = '';
    }
    $ordersql = 'ORDER BY ge.timemodified ASC';
    $entries = $DB->get_records_sql($selectsql.$fromsql.$clausesql.$approvalsql.$ordersql, $params, 0, (GLOSSARYLV_RECENT_ACTIVITY_LIMIT+1));

    if (empty($entries)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newentries', 'glossarylv').':', 3);
    $strftimerecent = get_string('strftimerecent');
    $entrycount = 0;
    foreach ($entries as $entry) {
        if ($entrycount < GLOSSARYLV_RECENT_ACTIVITY_LIMIT) {
            if ($entry->approved) {
                $dimmed = '';
                $urlparams = array('g' => $entry->glossarylvid, 'mode' => 'entry', 'hook' => $entry->id);
            } else {
                $dimmed = ' dimmed_text';
                $urlparams = array('id' => $ids[$entry->glossarylvid], 'mode' => 'approval', 'hook' => format_text($entry->concept, true));
            }
            $link = new moodle_url($CFG->wwwroot.'/mod/glossarylv/view.php' , $urlparams);
            echo '<div class="head'.$dimmed.'">';
            echo '<div class="date">'.userdate($entry->timemodified, $strftimerecent).'</div>';
            echo '<div class="name">'.fullname($entry, $viewfullnames).'</div>';
            echo '</div>';
            echo '<div class="info"><a href="'.$link.'">'.format_string($entry->concept, true).'</a></div>';
            $entrycount += 1;
        } else {
            $numnewentries = $DB->count_records_sql($countsql.$joins[0].$clausesql.$approvalsql, $params);
            echo '<div class="head"><div class="activityhead">'.get_string('andmorenewentries', 'glossarylv', $numnewentries - GLOSSARYLV_RECENT_ACTIVITY_LIMIT).'</div></div>';
            break;
        }
    }

    return true;
}

/**
 * @global object
 * @param object $log
 */
function glossarylv_log_info($log) {
    global $DB;

    return $DB->get_record_sql("SELECT e.*, u.firstname, u.lastname
                                  FROM {glossarylv_entries} e, {user} u
                                 WHERE e.id = ? AND u.id = ?", array($log->info, $log->userid));
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 * @return bool
 */
function glossarylv_cron () {
    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $glossarylv A glossarylv instance
 * @param int $userid Optional user id, 0 means all users
 * @return array An array of grades, false if none
 */
function glossarylv_get_user_grades($glossarylv, $userid=0) {
    global $CFG;

    require_once($CFG->dirroot.'/rating/lib.php');

    $ratingoptions = new stdClass;

    //need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
    $ratingoptions->modulename = 'glossarylv';
    $ratingoptions->moduleid   = $glossarylv->id;
    $ratingoptions->component  = 'mod_glossarylv';
    $ratingoptions->ratingarea = 'entry';

    $ratingoptions->userid = $userid;
    $ratingoptions->aggregationmethod = $glossarylv->assessed;
    $ratingoptions->scaleid = $glossarylv->scale;
    $ratingoptions->itemtable = 'glossarylv_entries';
    $ratingoptions->itemtableusercolumn = 'userid';

    $rm = new rating_manager();
    return $rm->get_user_grades($ratingoptions);
}

/**
 * Return rating related permissions
 *
 * @param int $contextid the context id
 * @param string $component The component we want to get permissions for
 * @param string $ratingarea The ratingarea that we want to get permissions for
 * @return array an associative array of the user's rating permissions
 */
function glossarylv_rating_permissions($contextid, $component, $ratingarea) {
    if ($component != 'mod_glossarylv' || $ratingarea != 'entry') {
        // We don't know about this component/ratingarea so just return null to get the
        // default restrictive permissions.
        return null;
    }
    $context = context::instance_by_id($contextid);
    return array(
        'view'    => has_capability('mod/glossarylv:viewrating', $context),
        'viewany' => has_capability('mod/glossarylv:viewanyrating', $context),
        'viewall' => has_capability('mod/glossarylv:viewallratings', $context),
        'rate'    => has_capability('mod/glossarylv:rate', $context)
    );
}

/**
 * Validates a submitted rating
 * @param array $params submitted data
 *            context => object the context in which the rated items exists [required]
 *            component => The component for this module - should always be mod_forum [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int the scale from which the user can select a rating. Used for bounds checking. [required]
 *            rating => int the submitted rating
 *            rateduserid => int the id of the user whose items have been rated. NOT the user who submitted the ratings. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie RATING_AGGREGATE_AVERAGE [optional]
 * @return boolean true if the rating is valid. Will throw rating_exception if not
 */
function glossarylv_rating_validate($params) {
    global $DB, $USER;

    // Check the component is mod_forum
    if ($params['component'] != 'mod_glossarylv') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in forum)
    if ($params['ratingarea'] != 'entry') {
        throw new rating_exception('invalidratingarea');
    }

    // Check the rateduserid is not the current user .. you can't rate your own posts
    if ($params['rateduserid'] == $USER->id) {
        throw new rating_exception('nopermissiontorate');
    }

    $glossarylvsql = "SELECT g.id as glossarylvid, g.scale, g.course, e.userid as userid, e.approved, e.timecreated, g.assesstimestart, g.assesstimefinish
                      FROM {glossarylv_entries} e
                      JOIN {glossarylv} g ON e.glossarylvid = g.id
                     WHERE e.id = :itemid";
    $glossarylvparams = array('itemid' => $params['itemid']);
    $info = $DB->get_record_sql($glossarylvsql, $glossarylvparams);
    if (!$info) {
        //item doesn't exist
        throw new rating_exception('invaliditemid');
    }

    if ($info->scale != $params['scaleid']) {
        //the scale being submitted doesnt match the one in the database
        throw new rating_exception('invalidscaleid');
    }

    //check that the submitted rating is valid for the scale

    // lower limit
    if ($params['rating'] < 0  && $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }

    // upper limit
    if ($info->scale < 0) {
        //its a custom scale
        $scalerecord = $DB->get_record('scale', array('id' => -$info->scale));
        if ($scalerecord) {
            $scalearray = explode(',', $scalerecord->scale);
            if ($params['rating'] > count($scalearray)) {
                throw new rating_exception('invalidnum');
            }
        } else {
            throw new rating_exception('invalidscaleid');
        }
    } else if ($params['rating'] > $info->scale) {
        //if its numeric and submitted rating is above maximum
        throw new rating_exception('invalidnum');
    }

    //check the item we're rating was created in the assessable time window
    if (!empty($info->assesstimestart) && !empty($info->assesstimefinish)) {
        if ($info->timecreated < $info->assesstimestart || $info->timecreated > $info->assesstimefinish) {
            throw new rating_exception('notavailable');
        }
    }

    $cm = get_coursemodule_from_instance('glossarylv', $info->glossarylvid, $info->course, false, MUST_EXIST);
    $context = context_module::instance($cm->id, MUST_EXIST);

    // if the supplied context doesnt match the item's context
    if ($context->id != $params['context']->id) {
        throw new rating_exception('invalidcontext');
    }

    return true;
}

/**
 * Update activity grades
 *
 * @category grade
 * @param stdClass $glossarylv Null means all glossaries (with extra cmidnumber property)
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone If true and the user has no grade then a grade item with rawgrade == null will be inserted
 */
function glossarylv_update_grades($glossarylv=null, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (!$glossarylv->assessed) {
        glossarylv_grade_item_update($glossarylv);

    } else if ($grades = glossarylv_get_user_grades($glossarylv, $userid)) {
        glossarylv_grade_item_update($glossarylv, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = NULL;
        glossarylv_grade_item_update($glossarylv, $grade);

    } else {
        glossarylv_grade_item_update($glossarylv);
    }
}

/**
 * Create/update grade item for given glossarylv
 *
 * @category grade
 * @param stdClass $glossarylv object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int, 0 if ok, error code otherwise
 */
function glossarylv_grade_item_update($glossarylv, $grades=NULL) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $params = array('itemname'=>$glossarylv->name, 'idnumber'=>$glossarylv->cmidnumber);

    if (!$glossarylv->assessed or $glossarylv->scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($glossarylv->scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $glossarylv->scale;
        $params['grademin']  = 0;

    } else if ($glossarylv->scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$glossarylv->scale;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/glossarylv', $glossarylv->course, 'mod', 'glossarylv', $glossarylv->id, 0, $grades, $params);
}

/**
 * Delete grade item for given glossarylv
 *
 * @category grade
 * @param object $glossarylv object
 */
function glossarylv_grade_item_delete($glossarylv) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/glossarylv', $glossarylv->course, 'mod', 'glossarylv', $glossarylv->id, 0, NULL, array('deleted'=>1));
}

/**
 * @global object
 * @param int $gloassryid
 * @param int $scaleid
 * @return bool
 */
function glossarylv_scale_used ($glossarylvid,$scaleid) {
//This function returns if a scale is being used by one glossarylv
    global $DB;

    $return = false;

    $rec = $DB->get_record("glossarylv", array("id"=>$glossarylvid, "scale"=>-$scaleid));

    if (!empty($rec)  && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of glossarylv
 *
 * This is used to find out if scale used anywhere
 *
 * @global object
 * @param int $scaleid
 * @return boolean True if the scale is used by any glossarylv
 */
function glossarylv_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('glossarylv', array('scale'=>-$scaleid))) {
        return true;
    } else {
        return false;
    }
}

//////////////////////////////////////////////////////////////////////////////////////
/// Any other glossarylv functions go here.  Each of them must have a name that
/// starts with glossarylv_

/**
 * This function return an array of valid glossarylv_formats records
 * Everytime it's called, every existing format is checked, new formats
 * are included if detected and old formats are deleted and any glossarylv
 * using an invalid format is updated to the default (dictionary).
 *
 * @global object
 * @global object
 * @return array
 */
function glossarylv_get_available_formats() {
    global $CFG, $DB;

    //Get available formats (plugin) and insert (if necessary) them into glossarylv_formats
    $formats = get_list_of_plugins('mod/glossarylv/formats', 'TEMPLATE');
    $pluginformats = array();
    foreach ($formats as $format) {
        //If the format file exists
        if (file_exists($CFG->dirroot.'/mod/glossarylv/formats/'.$format.'/'.$format.'_format.php')) {
            include_once($CFG->dirroot.'/mod/glossarylv/formats/'.$format.'/'.$format.'_format.php');
            //If the function exists
            if (function_exists('glossarylv_show_entry_'.$format)) {
                //Acummulate it as a valid format
                $pluginformats[] = $format;
                //If the format doesn't exist in the table
                if (!$rec = $DB->get_record('glossarylv_formats', array('name'=>$format))) {
                    //Insert the record in glossarylv_formats
                    $gf = new stdClass();
                    $gf->name = $format;
                    $gf->popupformatname = $format;
                    $gf->visible = 1;
                    $id = $DB->insert_record('glossarylv_formats', $gf);
                    $rec = $DB->get_record('glossarylv_formats', array('id' => $id));
                }

                if (empty($rec->showtabs)) {
                    glossarylv_set_default_visible_tabs($rec);
                }
            }
        }
    }

    //Delete non_existent formats from glossarylv_formats table
    $formats = $DB->get_records("glossarylv_formats");
    foreach ($formats as $format) {
        $todelete = false;
        //If the format in DB isn't a valid previously detected format then delete the record
        if (!in_array($format->name,$pluginformats)) {
            $todelete = true;
        }

        if ($todelete) {
            //Delete the format
            $DB->delete_records('glossarylv_formats', array('name'=>$format->name));
            //Reasign existing glossaries to default (dictionary) format
            if ($glossaries = $DB->get_records('glossarylv', array('displayformat'=>$format->name))) {
                foreach($glossaries as $glossarylv) {
                    $DB->set_field('glossarylv','displayformat','dictionary', array('id'=>$glossarylv->id));
                }
            }
        }
    }

    //Now everything is ready in glossarylv_formats table
    $formats = $DB->get_records("glossarylv_formats");

    return $formats;
}

/**
 * @param bool $debug
 * @param string $text
 * @param int $br
 */
function glossarylv_debug($debug,$text,$br=1) {
    if ( $debug ) {
        echo '<font color="red">' . $text . '</font>';
        if ( $br ) {
            echo '<br />';
        }
    }
}

/**
 *
 * @global object
 * @param int $glossarylvid
 * @param string $entrylist
 * @param string $pivot
 * @return array
 */
function glossarylv_get_entries($glossarylvid, $entrylist, $pivot = "") {
    global $DB;
    if ($pivot) {
       $pivot .= ",";
    }

    return $DB->get_records_sql("SELECT $pivot id,userid,concept,definition,format
                                   FROM {glossarylv_entries}
                                  WHERE glossarylvid = ?
                                        AND id IN ($entrylist)", array($glossarylvid));
}

/**
 * @global object
 * @global object
 * @param object $concept
 * @param string $courseid
 * @return array
 */
function glossarylv_get_entries_search($concept, $courseid) {
    global $CFG, $DB;

    //Check if the user is an admin
    $bypassadmin = 1; //This means NO (by default)
    if (has_capability('moodle/course:viewhiddenactivities', context_system::instance())) {
        $bypassadmin = 0; //This means YES
    }

    //Check if the user is a teacher
    $bypassteacher = 1; //This means NO (by default)
    if (has_capability('mod/glossarylv:manageentries', context_course::instance($courseid))) {
        $bypassteacher = 0; //This means YES
    }

    $conceptlower = core_text::strtolower(trim($concept));

    $params = array('courseid1'=>$courseid, 'courseid2'=>$courseid, 'conceptlower'=>$conceptlower, 'concept'=>$concept);

    return $DB->get_records_sql("SELECT e.*, g.name as glossarylvname, cm.id as cmid, cm.course as courseid
                                   FROM {glossarylv_entries} e, {glossarylv} g,
                                        {course_modules} cm, {modules} m
                                  WHERE m.name = 'glossarylv' AND
                                        cm.module = m.id AND
                                        (cm.visible = 1 OR  cm.visible = $bypassadmin OR
                                            (cm.course = :courseid1 AND cm.visible = $bypassteacher)) AND
                                        g.id = cm.instance AND
                                        e.glossarylvid = g.id  AND
                                        ( (e.casesensitive != 0 AND LOWER(concept) = :conceptlower) OR
                                          (e.casesensitive = 0 and concept = :concept)) AND
                                        (g.course = :courseid2 OR g.globalglossarylv = 1) AND
                                         e.usedynalink != 0 AND
                                         g.usedynalink != 0", $params);
}

/**
 * @global object
 * @global object
 * @param object $course
 * @param object $course
 * @param object $glossarylv
 * @param object $entry
 * @param string $mode
 * @param string $hook
 * @param int $printicons
 * @param int $displayformat
 * @param bool $printview
 * @return mixed
 */
function glossarylv_print_entry($course, $cm, $glossarylv, $entry, $mode='',$hook='',$printicons = 1, $displayformat  = -1, $printview = false) {
    global $USER, $CFG;
    $return = false;
    if ( $displayformat < 0 ) {
        $displayformat = $glossarylv->displayformat;
    }
    if ($entry->approved or ($USER->id == $entry->userid) or ($mode == 'approval' and !$entry->approved) ) {
        $formatfile = $CFG->dirroot.'/mod/glossarylv/formats/'.$displayformat.'/'.$displayformat.'_format.php';
        if ($printview) {
            $functionname = 'glossarylv_print_entry_'.$displayformat;
        } else {
            $functionname = 'glossarylv_show_entry_'.$displayformat;
        }

        if (file_exists($formatfile)) {
            include_once($formatfile);
            if (function_exists($functionname)) {
                $return = $functionname($course, $cm, $glossarylv, $entry,$mode,$hook,$printicons);
            } else if ($printview) {
                //If the glossarylv_print_entry_XXXX function doesn't exist, print default (old) print format
                $return = glossarylv_print_entry_default($entry, $glossarylv, $cm);
            }
        }
    }
    return $return;
}

/**
 * Default (old) print format used if custom function doesn't exist in format
 *
 * @param object $entry
 * @param object $glossarylv
 * @param object $cm
 * @return void Output is echo'd
 */
function glossarylv_print_entry_default ($entry, $glossarylv, $cm) {
    global $CFG;

    require_once($CFG->libdir . '/filelib.php');

    echo $OUTPUT->heading(strip_tags($entry->concept), 4);

    $definition = $entry->definition;

    $definition = '<span class="nolink">' . strip_tags($definition) . '</span>';

    $context = context_module::instance($cm->id);
    $definition = file_rewrite_pluginfile_urls($definition, 'pluginfile.php', $context->id, 'mod_glossarylv', 'entry', $entry->id);

    $options = new stdClass();
    $options->para = false;
    $options->trusted = $entry->definitiontrust;
    $options->context = $context;
    $options->overflowdiv = true;
    $definition = format_text($definition, $entry->definitionformat, $options);
    echo ($definition);
    echo '<br /><br />';
}

/**
 * Print glossarylv concept/term as a heading &lt;h4>
 * @param object $entry
 */
function  glossarylv_print_entry_concept($entry, $return=false) {
    global $OUTPUT;

    $text = $OUTPUT->heading(format_string($entry->concept), 4);
    if (!empty($entry->highlight)) {
        $text = highlight($entry->highlight, $text);
    }

    if ($return) {
        return $text;
    } else {
        echo $text;
    }
}

/**
 *
 * @global moodle_database DB
 * @param object $entry
 * @param object $glossarylv
 * @param object $cm
 */
function glossarylv_print_entry_definition($entry, $glossarylv, $cm) {
    global $GLOSSARYLV_EXCLUDEENTRY;

    $definition = $entry->definition;

    // Do not link self.
    $GLOSSARYLV_EXCLUDEENTRY = $entry->id;

    $context = context_module::instance($cm->id);
    $definition = file_rewrite_pluginfile_urls($definition, 'pluginfile.php', $context->id, 'mod_glossarylv', 'entry', $entry->id);

    $options = new stdClass();
    $options->para = false;
    $options->trusted = $entry->definitiontrust;
    $options->context = $context;
    $options->overflowdiv = true;

    $text = format_text($definition, $entry->definitionformat, $options);

    // Stop excluding concepts from autolinking
    unset($GLOSSARYLV_EXCLUDEENTRY);

    if (!empty($entry->highlight)) {
        $text = highlight($entry->highlight, $text);
    }
    if (isset($entry->footer)) {   // Unparsed footer info
        $text .= $entry->footer;
    }
    echo $text;
}

/**
 *
 * @global object
 * @param object $course
 * @param object $cm
 * @param object $glossarylv
 * @param object $entry
 * @param string $mode
 * @param string $hook
 * @param string $type
 * @return string|void
 */
function  glossarylv_print_entry_aliases($course, $cm, $glossarylv, $entry,$mode='',$hook='', $type = 'print') {
    global $DB;

    $return = '';
    if ( $aliases = $DB->get_records('glossarylv_alias', array('entryid'=>$entry->id))) {
        foreach ($aliases as $alias) {
            if (trim($alias->alias)) {
                if ($return == '') {
                    $return = '<select id="keyword" class="custom-select">';
                }
                $return .= "<option>$alias->alias</option>";
            }
        }
        if ($return != '') {
            $return .= '</select>';
        }
    }
    if ($type == 'print') {
        echo $return;
    } else {
        return $return;
    }
}

/**
 *
 * @global object
 * @global object
 * @global object
 * @param object $course
 * @param object $cm
 * @param object $glossarylv
 * @param object $entry
 * @param string $mode
 * @param string $hook
 * @param string $type
 * @return string|void
 */
function glossarylv_print_entry_icons($course, $cm, $glossarylv, $entry, $mode='',$hook='', $type = 'print') {
    global $USER, $CFG, $DB, $OUTPUT;

    $context = context_module::instance($cm->id);

    $output = false;   //To decide if we must really return text in "return". Activate when needed only!
    $importedentry = ($entry->sourceglossarylvid == $glossarylv->id);
    $ismainglossarylv = $glossarylv->mainglossarylv;


    $return = '<span class="commands">';
    // Differentiate links for each entry.
    $altsuffix = strip_tags(format_text($entry->concept));

    if (!$entry->approved) {
        $output = true;
        $return .= html_writer::tag('span', get_string('entryishidden','glossarylv'),
            array('class' => 'glossarylv-hidden-note'));
    }

    if (has_capability('mod/glossarylv:approve', $context) && !$glossarylv->defaultapproval && $entry->approved) {
        $output = true;
        $return .= '<a class="icon" title="' . get_string('disapprove', 'glossarylv').
                   '" href="approve.php?newstate=0&amp;eid='.$entry->id.'&amp;mode='.$mode.
                   '&amp;hook='.urlencode($hook).'&amp;sesskey='.sesskey().
                   '">' . $OUTPUT->pix_icon('t/block', get_string('disapprove', 'glossarylv')) . '</a>';
    }

    $iscurrentuser = ($entry->userid == $USER->id);

    if (has_capability('mod/glossarylv:manageentries', $context) or (isloggedin() and has_capability('mod/glossarylv:write', $context) and $iscurrentuser)) {
        // only teachers can export entries so check it out
        if (has_capability('mod/glossarylv:export', $context) and !$ismainglossarylv and !$importedentry) {
            $mainglossarylv = $DB->get_record('glossarylv', array('mainglossarylv'=>1,'course'=>$course->id));
            if ( $mainglossarylv ) {  // if there is a main glossarylv defined, allow to export the current entry
                $output = true;
                $return .= '<a class="icon" title="'.get_string('exporttomainglossarylv','glossarylv') . '" ' .
                    'href="exportentry.php?id='.$entry->id.'&amp;prevmode='.$mode.'&amp;hook='.urlencode($hook).'">' .
                    $OUTPUT->pix_icon('export', get_string('exporttomainglossarylv', 'glossarylv'), 'glossarylv') . '</a>';
            }
        }

        $icon = 't/delete';
        $iconcomponent = 'moodle';
        if ( $entry->sourceglossarylvid ) {
            $icon = 'minus';   // graphical metaphor (minus) for deleting an imported entry
            $iconcomponent = 'glossarylv';
        }

        //Decide if an entry is editable:
        // -It isn't a imported entry (so nobody can edit a imported (from secondary to main) entry)) and
        // -The user is teacher or he is a student with time permissions (edit period or editalways defined).
        $ineditperiod = ((time() - $entry->timecreated <  $CFG->maxeditingtime) || $glossarylv->editalways);
        if ( !$importedentry and (has_capability('mod/glossarylv:manageentries', $context) or ($entry->userid == $USER->id and ($ineditperiod and has_capability('mod/glossarylv:write', $context))))) {
            $output = true;
            $url = "deleteentry.php?id=$cm->id&amp;mode=delete&amp;entry=$entry->id&amp;prevmode=$mode&amp;hook=".urlencode($hook);
            $return .= "<a class='icon' title=\"" . get_string("delete") . "\" " .
                       "href=\"$url\">" . $OUTPUT->pix_icon($icon, get_string('deleteentrya', 'mod_glossarylv', $altsuffix), $iconcomponent) . '</a>';

            $url = "edit.php?cmid=$cm->id&amp;id=$entry->id&amp;mode=$mode&amp;hook=".urlencode($hook);
            $return .= "<a class='icon' title=\"" . get_string("edit") . "\" href=\"$url\">" .
                       $OUTPUT->pix_icon('t/edit', get_string('editentrya', 'mod_glossarylv', $altsuffix)) . '</a>';
        } elseif ( $importedentry ) {
            $return .= "<font size=\"-1\">" . get_string("exportedentry","glossarylv") . "</font>";
        }
    }

    if (!empty($CFG->enableportfolios) && (has_capability('mod/glossarylv:exportentry', $context) || ($iscurrentuser && has_capability('mod/glossarylv:exportownentry', $context)))) {
        require_once($CFG->libdir . '/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('glossarylv_entry_portfolio_caller',  array('id' => $cm->id, 'entryid' => $entry->id), 'mod_glossarylv');

        $filecontext = $context;
        if ($entry->sourceglossarylvid == $cm->instance) {
            if ($maincm = get_coursemodule_from_instance('glossarylv', $entry->glossarylvid)) {
                $filecontext = context_module::instance($maincm->id);
            }
        }
        $fs = get_file_storage();
        if ($files = $fs->get_area_files($filecontext->id, 'mod_glossarylv', 'attachment', $entry->id, "timemodified", false)
         || $files = $fs->get_area_files($filecontext->id, 'mod_glossarylv', 'entry', $entry->id, "timemodified", false)) {

            $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
        } else {
            $button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
        }

        $return .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
    }
    $return .= '</span>';

    if (!empty($CFG->usecomments) && has_capability('mod/glossarylv:comment', $context) and $glossarylv->allowcomments) {
        require_once($CFG->dirroot . '/comment/lib.php');
        $cmt = new stdClass();
        $cmt->component = 'mod_glossarylv';
        $cmt->context  = $context;
        $cmt->course   = $course;
        $cmt->cm       = $cm;
        $cmt->area     = 'glossarylv_entry';
        $cmt->itemid   = $entry->id;
        $cmt->showcount = true;
        $comment = new comment($cmt);
        $return .= '<div>'.$comment->output(true).'</div>';
        $output = true;
    }
    $return .= '<hr>';

    //If we haven't calculated any REAL thing, delete result ($return)
    if (!$output) {
        $return = '';
    }
    //Print or get
    if ($type == 'print') {
        echo $return;
    } else {
        return $return;
    }
}

/**
 * @param object $course
 * @param object $cm
 * @param object $glossarylv
 * @param object $entry
 * @param string $mode
 * @param object $hook
 * @param bool $printicons
 * @param bool $aliases
 * @return void
 */
function  glossarylv_print_entry_lower_section($course, $cm, $glossarylv, $entry, $mode, $hook, $printicons, $aliases=true) {
    // @lvs imprime avaliação da entrada.
    if(isset($entry->itemlv)){
	$gerenciadorDeNotas = NotasLvFactory::criarGerenciador('moodle2');
	$gerenciadorDeNotas->setModulo(new GlossarioLv($glossarylv->id));
	echo html_writer::tag( 'div',
                               $gerenciadorDeNotas->avaliacaoAtual($entry->itemlv).
                               $gerenciadorDeNotas->avaliadoPor($entry->itemlv).
                               $gerenciadorDeNotas->formAvaliacaoAjax($entry->itemlv),
			       array('class'=>'glossariolv-entry-rating'));
    }
    // ----
    if ($aliases) {
        $aliases = glossarylv_print_entry_aliases($course, $cm, $glossarylv, $entry, $mode, $hook,'html');
    }
    $icons   = '';
    if ($printicons) {
        $icons   = glossarylv_print_entry_icons($course, $cm, $glossarylv, $entry, $mode, $hook,'html');
    }
    if ($aliases || $icons || !empty($entry->rating)) {
        echo '<table>';
        if ( $aliases ) {
            echo '<tr valign="top"><td class="aliases">' .
                 '<label for="keyword">' . get_string('aliases','glossarylv').': </label>' .
                 $aliases . '</td></tr>';
        }
        if ($icons) {
            echo '<tr valign="top"><td class="icons">'.$icons.'</td></tr>';
        }
        if (!empty($entry->rating)) {
            echo '<tr valign="top"><td class="ratings">';
            glossarylv_print_entry_ratings($course, $entry);
            echo '</td></tr>';
        }
        echo '</table>';
    }
}

/**
 * Print the list of attachments for this glossarylv entry
 *
 * @param object $entry
 * @param object $cm The coursemodule
 * @param string $format The format for this view (html, or text)
 * @param string $unused1 This parameter is no longer used
 * @param string $unused2 This parameter is no longer used
 */
function glossarylv_print_entry_attachment($entry, $cm, $format = null, $unused1 = null, $unused2 = null) {
    // Valid format values: html: The HTML link for the attachment is an icon; and
    //                      text: The HTML link for the attachment is text.
    if ($entry->attachment) {
        echo '<div class="attachments">';
        echo glossarylv_print_attachments($entry, $cm, $format);
        echo '</div>';
    }
    if ($unused1) {
        debugging('The align parameter is deprecated, please use appropriate CSS instead', DEBUG_DEVELOPER);
    }
    if ($unused2 !== null) {
        debugging('The insidetable parameter is deprecated, please use appropriate CSS instead', DEBUG_DEVELOPER);
    }
}

/**
 * @global object
 * @param object $cm
 * @param object $entry
 * @param string $mode
 * @param string $align
 * @param bool $insidetable
 */
function  glossarylv_print_entry_approval($cm, $entry, $mode, $align="right", $insidetable=true) {
    global $CFG, $OUTPUT;

    if ($mode == 'approval' and !$entry->approved) {
        if ($insidetable) {
            echo '<table class="glossarylvapproval" align="'.$align.'"><tr><td align="'.$align.'">';
        }
        echo $OUTPUT->action_icon(
            new moodle_url('approve.php', array('eid' => $entry->id, 'mode' => $mode, 'sesskey' => sesskey())),
            new pix_icon('t/approve', get_string('approve','glossarylv'), '',
                array('class' => 'iconsmall', 'align' => $align))
        );
        if ($insidetable) {
            echo '</td></tr></table>';
        }
    }
}

/**
 * It returns all entries from all glossaries that matches the specified criteria
 *  within a given $course. It performs an $extended search if necessary.
 * It restrict the search to only one $glossarylv if the $glossarylv parameter is set.
 *
 * @global object
 * @global object
 * @param object $course
 * @param array $searchterms
 * @param int $extended
 * @param object $glossarylv
 * @return array
 */
function glossarylv_search($course, $searchterms, $extended = 0, $glossarylv = NULL) {
    global $CFG, $DB;

    if ( !$glossarylv ) {
        if ( $glossaries = $DB->get_records("glossarylv", array("course"=>$course->id)) ) {
            $glos = "";
            foreach ( $glossaries as $glossarylv ) {
                $glos .= "$glossarylv->id,";
            }
            $glos = substr($glos,0,-1);
        }
    } else {
        $glos = $glossarylv->id;
    }

    if (!has_capability('mod/glossarylv:manageentries', context_course::instance($glossarylv->course))) {
        $glossarylvmodule = $DB->get_record("modules", array("name"=>"glossarylv"));
        $onlyvisible = " AND g.id = cm.instance AND cm.visible = 1 AND cm.module = $glossarylvmodule->id";
        $onlyvisibletable = ", {course_modules} cm";
    } else {

        $onlyvisible = "";
        $onlyvisibletable = "";
    }

    if ($DB->sql_regex_supported()) {
        $REGEXP    = $DB->sql_regex(true);
        $NOTREGEXP = $DB->sql_regex(false);
    }

    $searchcond = array();
    $params     = array();
    $i = 0;

    $concat = $DB->sql_concat('e.concept', "' '", 'e.definition');


    foreach ($searchterms as $searchterm) {
        $i++;

        $NOT = false; /// Initially we aren't going to perform NOT LIKE searches, only MSSQL and Oracle
                   /// will use it to simulate the "-" operator with LIKE clause

    /// Under Oracle and MSSQL, trim the + and - operators and perform
    /// simpler LIKE (or NOT LIKE) queries
        if (!$DB->sql_regex_supported()) {
            if (substr($searchterm, 0, 1) == '-') {
                $NOT = true;
            }
            $searchterm = trim($searchterm, '+-');
        }

        // TODO: +- may not work for non latin languages

        if (substr($searchterm,0,1) == '+') {
            $searchterm = trim($searchterm, '+-');
            $searchterm = preg_quote($searchterm, '|');
            $searchcond[] = "$concat $REGEXP :ss$i";
            $params['ss'.$i] = "(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)";

        } else if (substr($searchterm,0,1) == "-") {
            $searchterm = trim($searchterm, '+-');
            $searchterm = preg_quote($searchterm, '|');
            $searchcond[] = "$concat $NOTREGEXP :ss$i";
            $params['ss'.$i] = "(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)";

        } else {
            $searchcond[] = $DB->sql_like($concat, ":ss$i", false, true, $NOT);
            $params['ss'.$i] = "%$searchterm%";
        }
    }

    if (empty($searchcond)) {
        $totalcount = 0;
        return array();
    }

    $searchcond = implode(" AND ", $searchcond);

    $sql = "SELECT e.*
              FROM {glossarylv_entries} e, {glossarylv} g $onlyvisibletable
             WHERE $searchcond
               AND (e.glossarylvid = g.id or e.sourceglossarylvid = g.id) $onlyvisible
               AND g.id IN ($glos) AND e.approved <> 0";

    return $DB->get_records_sql($sql, $params);
}

/**
 * @global object
 * @param array $searchterms
 * @param object $glossarylv
 * @param bool $extended
 * @return array
 */
function glossarylv_search_entries($searchterms, $glossarylv, $extended) {
    global $DB;

    $course = $DB->get_record("course", array("id"=>$glossarylv->course));
    return glossarylv_search($course,$searchterms,$extended,$glossarylv);
}

/**
 * if return=html, then return a html string.
 * if return=text, then return a text-only string.
 * otherwise, print HTML for non-images, and return image HTML
 *     if attachment is an image, $align set its aligment.
 *
 * @global object
 * @global object
 * @param object $entry
 * @param object $cm
 * @param string $type html, txt, empty
 * @param string $unused This parameter is no longer used
 * @return string image string or nothing depending on $type param
 */
function glossarylv_print_attachments($entry, $cm, $type=NULL, $unused = null) {
    global $CFG, $DB, $OUTPUT;

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        return '';
    }

    if ($entry->sourceglossarylvid == $cm->instance) {
        if (!$maincm = get_coursemodule_from_instance('glossarylv', $entry->glossarylvid)) {
            return '';
        }
        $filecontext = context_module::instance($maincm->id);

    } else {
        $filecontext = $context;
    }

    $strattachment = get_string('attachment', 'glossarylv');

    $fs = get_file_storage();

    $imagereturn = '';
    $output = '';

    if ($files = $fs->get_area_files($filecontext->id, 'mod_glossarylv', 'attachment', $entry->id, "timemodified", false)) {
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
            $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/mod_glossarylv/attachment/'.$entry->id.'/'.$filename);

            if ($type == 'html') {
                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= "<a href=\"$path\">".s($filename)."</a>";
                $output .= "<br />";

            } else if ($type == 'text') {
                $output .= "$strattachment ".s($filename).":\n$path\n";

            } else {
                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links
                    $imagereturn .= "<br /><img src=\"$path\" alt=\"\" />";
                } else {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= format_text("<a href=\"$path\">".s($filename)."</a>", FORMAT_HTML, array('context'=>$context));
                    $output .= '<br />';
                }
            }
        }
    }

    if ($type) {
        return $output;
    } else {
        echo $output;
        return $imagereturn;
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Lists all browsable file areas
 *
 * @package  mod_glossarylv
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function glossarylv_get_file_areas($course, $cm, $context) {
    return array(
        'attachment' => get_string('areaattachment', 'mod_glossarylv'),
        'entry' => get_string('areaentry', 'mod_glossarylv'),
    );
}

/**
 * File browsing support for glossarylv module.
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param cm_info $cm
 * @param context $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info_stored file_info_stored instance or null if not found
 */
function glossarylv_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    if (!isset($areas[$filearea])) {
        return null;
    }

    if (is_null($itemid)) {
        require_once($CFG->dirroot.'/mod/glossarylv/locallib.php');
        return new glossarylv_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    if (!$entry = $DB->get_record('glossarylv_entries', array('id' => $itemid))) {
        return null;
    }

    if (!$glossarylv = $DB->get_record('glossarylv', array('id' => $cm->instance))) {
        return null;
    }

    if ($glossarylv->defaultapproval and !$entry->approved and !has_capability('mod/glossarylv:approve', $context)) {
        return null;
    }

    // this trickery here is because we need to support source glossarylv access
    if ($entry->glossarylvid == $cm->instance) {
        $filecontext = $context;
    } else if ($entry->sourceglossarylvid == $cm->instance) {
        if (!$maincm = get_coursemodule_from_instance('glossarylv', $entry->glossarylvid)) {
            return null;
        }
        $filecontext = context_module::instance($maincm->id);
    } else {
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs->get_file($filecontext->id, 'mod_glossarylv', $filearea, $itemid, $filepath, $filename))) {
        return null;
    }

    // Checks to see if the user can manage files or is the owner.
    // TODO MDL-33805 - Do not use userid here and move the capability check above.
    if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';

    return new file_info_stored($browser, $filecontext, $storedfile, $urlbase, s($entry->concept), true, true, false, false);
}

/**
 * Serves the glossarylv attachments. Implements needed access control ;-)
 *
 * @package  mod_glossarylv
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClsss $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function glossarylv_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    if ($filearea === 'attachment' or $filearea === 'entry') {
        $entryid = (int)array_shift($args);

        require_course_login($course, true, $cm);

        if (!$entry = $DB->get_record('glossarylv_entries', array('id'=>$entryid))) {
            return false;
        }

        if (!$glossarylv = $DB->get_record('glossarylv', array('id'=>$cm->instance))) {
            return false;
        }

        if ($glossarylv->defaultapproval and !$entry->approved and !has_capability('mod/glossarylv:approve', $context)) {
            return false;
        }

        // this trickery here is because we need to support source glossarylv access

        if ($entry->glossarylvid == $cm->instance) {
            $filecontext = $context;

        } else if ($entry->sourceglossarylvid == $cm->instance) {
            if (!$maincm = get_coursemodule_from_instance('glossarylv', $entry->glossarylvid)) {
                return false;
            }
            $filecontext = context_module::instance($maincm->id);

        } else {
            return false;
        }

        $relativepath = implode('/', $args);
        $fullpath = "/$filecontext->id/mod_glossarylv/$filearea/$entryid/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        // finally send the file
        send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!

    } else if ($filearea === 'export') {
        require_login($course, false, $cm);
        require_capability('mod/glossarylv:export', $context);

        if (!$glossarylv = $DB->get_record('glossarylv', array('id'=>$cm->instance))) {
            return false;
        }

        $cat = array_shift($args);
        $cat = clean_param($cat, PARAM_ALPHANUM);

        $filename = clean_filename(strip_tags(format_string($glossarylv->name)).'.xml');
        $content = glossarylv_generate_export_file($glossarylv, NULL, $cat);

        send_file($content, $filename, 0, 0, true, true);
    }

    return false;
}

/**
 *
 */
function glossarylv_print_tabbed_table_end() {
     echo "</div></div>";
}

/**
 * @param object $cm
 * @param object $glossarylv
 * @param string $mode
 * @param string $hook
 * @param string $sortkey
 * @param string $sortorder
 */
function glossarylv_print_approval_menu($cm, $glossarylv,$mode, $hook, $sortkey = '', $sortorder = '') {
    if ($glossarylv->showalphabet) {
        echo '<div class="glossarylvexplain">' . get_string("explainalphabet","glossarylv") . '</div><br />';
    }
    glossarylv_print_special_links($cm, $glossarylv, $mode, $hook);

    glossarylv_print_alphabet_links($cm, $glossarylv, $mode, $hook,$sortkey, $sortorder);

    glossarylv_print_all_links($cm, $glossarylv, $mode, $hook);

    glossarylv_print_sorting_links($cm, $mode, 'CREATION', 'asc');
}
/**
 * @param object $cm
 * @param object $glossarylv
 * @param string $hook
 * @param string $sortkey
 * @param string $sortorder
 */
function glossarylv_print_import_menu($cm, $glossarylv, $mode, $hook, $sortkey='', $sortorder = '') {
    echo '<div class="glossarylvexplain">' . get_string("explainimport","glossarylv") . '</div>';
}

/**
 * @param object $cm
 * @param object $glossarylv
 * @param string $hook
 * @param string $sortkey
 * @param string $sortorder
 */
function glossarylv_print_export_menu($cm, $glossarylv, $mode, $hook, $sortkey='', $sortorder = '') {
    echo '<div class="glossarylvexplain">' . get_string("explainexport","glossarylv") . '</div>';
}
/**
 * @param object $cm
 * @param object $glossarylv
 * @param string $hook
 * @param string $sortkey
 * @param string $sortorder
 */
function glossarylv_print_alphabet_menu($cm, $glossarylv, $mode, $hook, $sortkey='', $sortorder = '') {
    if ( $mode != 'date' ) {
        if ($glossarylv->showalphabet) {
            echo '<div class="glossarylvexplain">' . get_string("explainalphabet","glossarylv") . '</div><br />';
        }

        glossarylv_print_special_links($cm, $glossarylv, $mode, $hook);

        glossarylv_print_alphabet_links($cm, $glossarylv, $mode, $hook, $sortkey, $sortorder);

        glossarylv_print_all_links($cm, $glossarylv, $mode, $hook);
    } else {
        glossarylv_print_sorting_links($cm, $mode, $sortkey,$sortorder);
    }
}

/**
 * @param object $cm
 * @param object $glossarylv
 * @param string $hook
 * @param string $sortkey
 * @param string $sortorder
 */
function glossarylv_print_author_menu($cm, $glossarylv,$mode, $hook, $sortkey = '', $sortorder = '') {
    if ($glossarylv->showalphabet) {
        echo '<div class="glossarylvexplain">' . get_string("explainalphabet","glossarylv") . '</div><br />';
    }

    glossarylv_print_alphabet_links($cm, $glossarylv, $mode, $hook, $sortkey, $sortorder);
    glossarylv_print_all_links($cm, $glossarylv, $mode, $hook);
    glossarylv_print_sorting_links($cm, $mode, $sortkey,$sortorder);
}

/**
 * @global object
 * @global object
 * @param object $cm
 * @param object $glossarylv
 * @param string $hook
 * @param object $category
 */
function glossarylv_print_categories_menu($cm, $glossarylv, $hook, $category) {
     global $CFG, $DB, $OUTPUT;

     $context = context_module::instance($cm->id);

    // Prepare format_string/text options
    $fmtoptions = array(
        'context' => $context);

     echo '<table border="0" width="100%">';
     echo '<tr>';

     echo '<td align="center" style="width:20%">';
     if (has_capability('mod/glossarylv:managecategories', $context)) {
             $options['id'] = $cm->id;
             $options['mode'] = 'cat';
             $options['hook'] = $hook;
             echo $OUTPUT->single_button(new moodle_url("editcategories.php", $options), get_string("editcategories","glossarylv"), "get");
     }
     echo '</td>';

     echo '<td align="center" style="width:60%">';
     echo '<b>';

     $menu = array();
     $menu[GLOSSARYLV_SHOW_ALL_CATEGORIES] = get_string("allcategories","glossarylv");
     $menu[GLOSSARYLV_SHOW_NOT_CATEGORISED] = get_string("notcategorised","glossarylv");

     $categories = $DB->get_records("glossarylv_categories", array("glossarylvid"=>$glossarylv->id), "name ASC");
     $selected = '';
     if ( $categories ) {
          foreach ($categories as $currentcategory) {
                 $url = $currentcategory->id;
                 if ( $category ) {
                     if ($currentcategory->id == $category->id) {
                         $selected = $url;
                     }
                 }
                 $menu[$url] = format_string($currentcategory->name, true, $fmtoptions);
          }
     }
     if ( !$selected ) {
         $selected = GLOSSARYLV_SHOW_NOT_CATEGORISED;
     }

     if ( $category ) {
        echo format_string($category->name, true, $fmtoptions);
     } else {
        if ( $hook == GLOSSARYLV_SHOW_NOT_CATEGORISED ) {

            echo get_string("entrieswithoutcategory","glossarylv");
            $selected = GLOSSARYLV_SHOW_NOT_CATEGORISED;

        } elseif ( $hook == GLOSSARYLV_SHOW_ALL_CATEGORIES ) {

            echo get_string("allcategories","glossarylv");
            $selected = GLOSSARYLV_SHOW_ALL_CATEGORIES;

        }
     }
     echo '</b></td>';
     echo '<td align="center" style="width:20%">';

     $select = new single_select(new moodle_url("/mod/glossarylv/view.php", array('id'=>$cm->id, 'mode'=>'cat')), 'hook', $menu, $selected, null, "catmenu");
     $select->set_label(get_string('categories', 'glossarylv'), array('class' => 'accesshide'));
     echo $OUTPUT->render($select);

     echo '</td>';
     echo '</tr>';

     echo '</table>';
}

/**
 * @global object
 * @param object $cm
 * @param object $glossarylv
 * @param string $mode
 * @param string $hook
 */
function glossarylv_print_all_links($cm, $glossarylv, $mode, $hook) {
global $CFG;
     if ( $glossarylv->showall) {
         $strallentries       = get_string("allentries", "glossarylv");
         if ( $hook == 'ALL' ) {
              echo "<b>$strallentries</b>";
         } else {
              $strexplainall = strip_tags(get_string("explainall","glossarylv"));
              echo "<a title=\"$strexplainall\" href=\"$CFG->wwwroot/mod/glossarylv/view.php?id=$cm->id&amp;mode=$mode&amp;hook=ALL\">$strallentries</a>";
         }
     }
}

/**
 * @global object
 * @param object $cm
 * @param object $glossarylv
 * @param string $mode
 * @param string $hook
 */
function glossarylv_print_special_links($cm, $glossarylv, $mode, $hook) {
global $CFG;
     if ( $glossarylv->showspecial) {
         $strspecial          = get_string("special", "glossarylv");
         if ( $hook == 'SPECIAL' ) {
              echo "<b>$strspecial</b> | ";
         } else {
              $strexplainspecial = strip_tags(get_string("explainspecial","glossarylv"));
              echo "<a title=\"$strexplainspecial\" href=\"$CFG->wwwroot/mod/glossarylv/view.php?id=$cm->id&amp;mode=$mode&amp;hook=SPECIAL\">$strspecial</a> | ";
         }
     }
}

/**
 * @global object
 * @param object $glossarylv
 * @param string $mode
 * @param string $hook
 * @param string $sortkey
 * @param string $sortorder
 */
function glossarylv_print_alphabet_links($cm, $glossarylv, $mode, $hook, $sortkey, $sortorder) {
global $CFG;
     if ( $glossarylv->showalphabet) {
          $alphabet = explode(",", get_string('alphabet', 'langconfig'));
          for ($i = 0; $i < count($alphabet); $i++) {
              if ( $hook == $alphabet[$i] and $hook) {
                   echo "<b>$alphabet[$i]</b>";
              } else {
                   echo "<a href=\"$CFG->wwwroot/mod/glossarylv/view.php?id=$cm->id&amp;mode=$mode&amp;hook=".urlencode($alphabet[$i])."&amp;sortkey=$sortkey&amp;sortorder=$sortorder\">$alphabet[$i]</a>";
              }
              echo ' | ';
          }
     }
}

/**
 * @global object
 * @param object $cm
 * @param string $mode
 * @param string $sortkey
 * @param string $sortorder
 */
function glossarylv_print_sorting_links($cm, $mode, $sortkey = '',$sortorder = '') {
    global $CFG, $OUTPUT;

    $asc    = get_string("ascending","glossarylv");
    $desc   = get_string("descending","glossarylv");
    $bopen  = '<b>';
    $bclose = '</b>';

     $neworder = '';
     $currentorder = '';
     $currentsort = '';
     if ( $sortorder ) {
         if ( $sortorder == 'asc' ) {
             $currentorder = $asc;
             $neworder = '&amp;sortorder=desc';
             $newordertitle = get_string('changeto', 'glossarylv', $desc);
         } else {
             $currentorder = $desc;
             $neworder = '&amp;sortorder=asc';
             $newordertitle = get_string('changeto', 'glossarylv', $asc);
         }
         $icon = " " . $OUTPUT->pix_icon($sortorder, $newordertitle, 'glossarylv');
     } else {
         if ( $sortkey != 'CREATION' and $sortkey != 'UPDATE' and
               $sortkey != 'FIRSTNAME' and $sortkey != 'LASTNAME' ) {
             $icon = "";
             $newordertitle = $asc;
         } else {
             $newordertitle = $desc;
             $neworder = '&amp;sortorder=desc';
             $icon = " " . $OUTPUT->pix_icon('asc', $newordertitle, 'glossarylv');
         }
     }
     $ficon     = '';
     $fneworder = '';
     $fbtag     = '';
     $fendbtag  = '';

     $sicon     = '';
     $sneworder = '';

     $sbtag      = '';
     $fbtag      = '';
     $fendbtag      = '';
     $sendbtag      = '';

     $sendbtag  = '';

     if ( $sortkey == 'CREATION' or $sortkey == 'FIRSTNAME' ) {
         $ficon       = $icon;
         $fneworder   = $neworder;
         $fordertitle = $newordertitle;
         $sordertitle = $asc;
         $fbtag       = $bopen;
         $fendbtag    = $bclose;
     } elseif ($sortkey == 'UPDATE' or $sortkey == 'LASTNAME') {
         $sicon = $icon;
         $sneworder   = $neworder;
         $fordertitle = $asc;
         $sordertitle = $newordertitle;
         $sbtag       = $bopen;
         $sendbtag    = $bclose;
     } else {
         $fordertitle = $asc;
         $sordertitle = $asc;
     }

     if ( $sortkey == 'CREATION' or $sortkey == 'UPDATE' ) {
         $forder = 'CREATION';
         $sorder =  'UPDATE';
         $fsort  = get_string("sortbycreation", "glossarylv");
         $ssort  = get_string("sortbylastupdate", "glossarylv");

         $currentsort = $fsort;
         if ($sortkey == 'UPDATE') {
             $currentsort = $ssort;
         }
         $sort        = get_string("sortchronogically", "glossarylv");
     } elseif ( $sortkey == 'FIRSTNAME' or $sortkey == 'LASTNAME') {
         $forder = 'FIRSTNAME';
         $sorder =  'LASTNAME';
         $fsort  = get_string("firstname");
         $ssort  = get_string("lastname");

         $currentsort = $fsort;
         if ($sortkey == 'LASTNAME') {
             $currentsort = $ssort;
         }
         $sort        = get_string("sortby", "glossarylv");
     }
     $current = '<span class="accesshide">'.get_string('current', 'glossarylv', "$currentsort $currentorder").'</span>';
     echo "<br />$current $sort: $sbtag<a title=\"$ssort $sordertitle\" href=\"$CFG->wwwroot/mod/glossarylv/view.php?id=$cm->id&amp;sortkey=$sorder$sneworder&amp;mode=$mode\">$ssort$sicon</a>$sendbtag | ".
                          "$fbtag<a title=\"$fsort $fordertitle\" href=\"$CFG->wwwroot/mod/glossarylv/view.php?id=$cm->id&amp;sortkey=$forder$fneworder&amp;mode=$mode\">$fsort$ficon</a>$fendbtag<br />";
}

/**
 *
 * @param object $entry0
 * @param object $entry1
 * @return int [-1 | 0 | 1]
 */
function glossarylv_sort_entries ( $entry0, $entry1 ) {

    if ( core_text::strtolower(ltrim($entry0->concept)) < core_text::strtolower(ltrim($entry1->concept)) ) {
        return -1;
    } elseif ( core_text::strtolower(ltrim($entry0->concept)) > core_text::strtolower(ltrim($entry1->concept)) ) {
        return 1;
    } else {
        return 0;
    }
}


/**
 * @global object
 * @global object
 * @global object
 * @param object $course
 * @param object $entry
 * @return bool
 */
function  glossarylv_print_entry_ratings($course, $entry) {
    global $OUTPUT;
    if( !empty($entry->rating) ){
        echo $OUTPUT->render($entry->rating);
    }
}

/**
 *
 * @global object
 * @global object
 * @global object
 * @param int $courseid
 * @param array $entries
 * @param int $displayformat
 */
function glossarylv_print_dynaentry($courseid, $entries, $displayformat = -1) {
    global $USER, $CFG, $DB;

    echo '<div class="boxaligncenter">';
    echo '<table class="glossarylvpopup" cellspacing="0"><tr>';
    echo '<td>';
    if ( $entries ) {
        foreach ( $entries as $entry ) {
            if (! $glossarylv = $DB->get_record('glossarylv', array('id'=>$entry->glossarylvid))) {
                print_error('invalidid', 'glossarylv');
            }
            if (! $course = $DB->get_record('course', array('id'=>$glossarylv->course))) {
                print_error('coursemisconf');
            }
            if (!$cm = get_coursemodule_from_instance('glossarylv', $entry->glossarylvid, $glossarylv->course) ) {
                print_error('invalidid', 'glossarylv');
            }

            //If displayformat is present, override glossarylv->displayformat
            if ($displayformat < 0) {
                $dp = $glossarylv->displayformat;
            } else {
                $dp = $displayformat;
            }

            //Get popupformatname
            $format = $DB->get_record('glossarylv_formats', array('name'=>$dp));
            $displayformat = $format->popupformatname;

            //Check displayformat variable and set to default if necessary
            if (!$displayformat) {
                $displayformat = 'dictionary';
            }

            $formatfile = $CFG->dirroot.'/mod/glossarylv/formats/'.$displayformat.'/'.$displayformat.'_format.php';
            $functionname = 'glossarylv_show_entry_'.$displayformat;

            if (file_exists($formatfile)) {
                include_once($formatfile);
                if (function_exists($functionname)) {
                    $functionname($course, $cm, $glossarylv, $entry,'','','','');
                }
            }
        }
    }
    echo '</td>';
    echo '</tr></table></div>';
}

/**
 *
 * @global object
 * @param array $entries
 * @param array $aliases
 * @param array $categories
 * @return string
 */
function glossarylv_generate_export_csv($entries, $aliases, $categories) {
    global $CFG;
    $csv = '';
    $delimiter = '';
    require_once($CFG->libdir . '/csvlib.class.php');
    $delimiter = csv_import_reader::get_delimiter('comma');
    $csventries = array(0 => array(get_string('concept', 'glossarylv'), get_string('definition', 'glossarylv')));
    $csvaliases = array(0 => array());
    $csvcategories = array(0 => array());
    $aliascount = 0;
    $categorycount = 0;

    foreach ($entries as $entry) {
        $thisaliasesentry = array();
        $thiscategoriesentry = array();
        $thiscsventry = array($entry->concept, nl2br($entry->definition));

        if (array_key_exists($entry->id, $aliases) && is_array($aliases[$entry->id])) {
            $thiscount = count($aliases[$entry->id]);
            if ($thiscount > $aliascount) {
                $aliascount = $thiscount;
            }
            foreach ($aliases[$entry->id] as $alias) {
                $thisaliasesentry[] = trim($alias);
            }
        }
        if (array_key_exists($entry->id, $categories) && is_array($categories[$entry->id])) {
            $thiscount = count($categories[$entry->id]);
            if ($thiscount > $categorycount) {
                $categorycount = $thiscount;
            }
            foreach ($categories[$entry->id] as $catentry) {
                $thiscategoriesentry[] = trim($catentry);
            }
        }
        $csventries[$entry->id] = $thiscsventry;
        $csvaliases[$entry->id] = $thisaliasesentry;
        $csvcategories[$entry->id] = $thiscategoriesentry;

    }
    $returnstr = '';
    foreach ($csventries as $id => $row) {
        $aliasstr = '';
        $categorystr = '';
        if ($id == 0) {
            $aliasstr = get_string('alias', 'glossarylv');
            $categorystr = get_string('category', 'glossarylv');
        }
        $row = array_merge($row, array_pad($csvaliases[$id], $aliascount, $aliasstr), array_pad($csvcategories[$id], $categorycount, $categorystr));
        $returnstr .= '"' . implode('"' . $delimiter . '"', $row) . '"' . "\n";
    }
    return $returnstr;
}

/**
 *
 * @param object $glossarylv
 * @param string $ignored invalid parameter
 * @param int|string $hook
 * @return string
 */
function glossarylv_generate_export_file($glossarylv, $ignored = "", $hook = 0) {
    global $CFG, $DB;

    // Large exports are likely to take their time and memory.
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_EXTRA);

    $cm = get_coursemodule_from_instance('glossarylv', $glossarylv->id, $glossarylv->course);
    $context = context_module::instance($cm->id);

    $co  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

    $co .= glossarylv_start_tag("GLOSSARYLV",0,true);
    $co .= glossarylv_start_tag("INFO",1,true);
        $co .= glossarylv_full_tag("NAME",2,false,$glossarylv->name);
        $co .= glossarylv_full_tag("INTRO",2,false,$glossarylv->intro);
        $co .= glossarylv_full_tag("INTROFORMAT",2,false,$glossarylv->introformat);
        $co .= glossarylv_full_tag("ALLOWDUPLICATEDENTRIES",2,false,$glossarylv->allowduplicatedentries);
        $co .= glossarylv_full_tag("DISPLAYFORMAT",2,false,$glossarylv->displayformat);
        $co .= glossarylv_full_tag("SHOWSPECIAL",2,false,$glossarylv->showspecial);
        $co .= glossarylv_full_tag("SHOWALPHABET",2,false,$glossarylv->showalphabet);
        $co .= glossarylv_full_tag("SHOWALL",2,false,$glossarylv->showall);
        $co .= glossarylv_full_tag("ALLOWCOMMENTS",2,false,$glossarylv->allowcomments);
        $co .= glossarylv_full_tag("USEDYNALINK",2,false,$glossarylv->usedynalink);
        $co .= glossarylv_full_tag("DEFAULTAPPROVAL",2,false,$glossarylv->defaultapproval);
        $co .= glossarylv_full_tag("GLOBALGLOSSARYLV",2,false,$glossarylv->globalglossarylv);
        $co .= glossarylv_full_tag("ENTBYPAGE",2,false,$glossarylv->entbypage);
        $co .= glossarylv_xml_export_files('INTROFILES', 2, $context->id, 'intro', 0);

        if ( $entries = $DB->get_records("glossarylv_entries", array("glossarylvid"=>$glossarylv->id))) {
            $co .= glossarylv_start_tag("ENTRIES",2,true);
            foreach ($entries as $entry) {
                $permissiongranted = 1;
                if ( $hook ) {
                    switch ( $hook ) {
                    case "ALL":
                    case "SPECIAL":
                    break;
                    default:
                        $permissiongranted = ($entry->concept[ strlen($hook)-1 ] == $hook);
                    break;
                    }
                }
                if ( $hook ) {
                    switch ( $hook ) {
                    case GLOSSARYLV_SHOW_ALL_CATEGORIES:
                    break;
                    case GLOSSARYLV_SHOW_NOT_CATEGORISED:
                        $permissiongranted = !$DB->record_exists("glossarylv_entries_categ", array("entryid"=>$entry->id));
                    break;
                    default:
                        $permissiongranted = $DB->record_exists("glossarylv_entries_categ", array("entryid"=>$entry->id, "categoryid"=>$hook));
                    break;
                    }
                }
                if ( $entry->approved and $permissiongranted ) {
                    $co .= glossarylv_start_tag("ENTRY",3,true);
                    $co .= glossarylv_full_tag("CONCEPT",4,false,trim($entry->concept));
                    $co .= glossarylv_full_tag("DEFINITION",4,false,$entry->definition);
                    $co .= glossarylv_full_tag("FORMAT",4,false,$entry->definitionformat); // note: use old name for BC reasons
                    $co .= glossarylv_full_tag("USEDYNALINK",4,false,$entry->usedynalink);
                    $co .= glossarylv_full_tag("CASESENSITIVE",4,false,$entry->casesensitive);
                    $co .= glossarylv_full_tag("FULLMATCH",4,false,$entry->fullmatch);
                    $co .= glossarylv_full_tag("TEACHERENTRY",4,false,$entry->teacherentry);

                    if ( $aliases = $DB->get_records("glossarylv_alias", array("entryid"=>$entry->id))) {
                        $co .= glossarylv_start_tag("ALIASES",4,true);
                        foreach ($aliases as $alias) {
                            $co .= glossarylv_start_tag("ALIAS",5,true);
                                $co .= glossarylv_full_tag("NAME",6,false,trim($alias->alias));
                            $co .= glossarylv_end_tag("ALIAS",5,true);
                        }
                        $co .= glossarylv_end_tag("ALIASES",4,true);
                    }
                    if ( $catentries = $DB->get_records("glossarylv_entries_categ", array("entryid"=>$entry->id))) {
                        $co .= glossarylv_start_tag("CATEGORIES",4,true);
                        foreach ($catentries as $catentry) {
                            $category = $DB->get_record("glossarylv_categories", array("id"=>$catentry->categoryid));

                            $co .= glossarylv_start_tag("CATEGORY",5,true);
                                $co .= glossarylv_full_tag("NAME",6,false,$category->name);
                                $co .= glossarylv_full_tag("USEDYNALINK",6,false,$category->usedynalink);
                            $co .= glossarylv_end_tag("CATEGORY",5,true);
                        }
                        $co .= glossarylv_end_tag("CATEGORIES",4,true);
                    }

                    // Export files embedded in entries.
                    $co .= glossarylv_xml_export_files('ENTRYFILES', 4, $context->id, 'entry', $entry->id);

                    // Export attachments.
                    $co .= glossarylv_xml_export_files('ATTACHMENTFILES', 4, $context->id, 'attachment', $entry->id);

                    $co .= glossarylv_end_tag("ENTRY",3,true);
                }
            }
            $co .= glossarylv_end_tag("ENTRIES",2,true);

        }


    $co .= glossarylv_end_tag("INFO",1,true);
    $co .= glossarylv_end_tag("GLOSSARYLV",0,true);

    return $co;
}
/// Functions designed by Eloy Lafuente
/// Functions to create, open and write header of the xml file

/**
 * Read import file and convert to current charset
 *
 * @global object
 * @param string $file
 * @return string
 */
function glossarylv_read_imported_file($file_content) {
    require_once "../../lib/xmlize.php";
    global $CFG;

    return xmlize($file_content, 0);
}

/**
 * Return the xml start tag
 *
 * @param string $tag
 * @param int $level
 * @param bool $endline
 * @return string
 */
function glossarylv_start_tag($tag,$level=0,$endline=false) {
        if ($endline) {
           $endchar = "\n";
        } else {
           $endchar = "";
        }
        return str_repeat(" ",$level*2)."<".strtoupper($tag).">".$endchar;
}

/**
 * Return the xml end tag
 * @param string $tag
 * @param int $level
 * @param bool $endline
 * @return string
 */
function glossarylv_end_tag($tag,$level=0,$endline=true) {
        if ($endline) {
           $endchar = "\n";
        } else {
           $endchar = "";
        }
        return str_repeat(" ",$level*2)."</".strtoupper($tag).">".$endchar;
}

/**
 * Return the start tag, the contents and the end tag
 *
 * @global object
 * @param string $tag
 * @param int $level
 * @param bool $endline
 * @param string $content
 * @return string
 */
function glossarylv_full_tag($tag,$level=0,$endline=true,$content) {
        global $CFG;

        $st = glossarylv_start_tag($tag,$level,$endline);
        $co = preg_replace("/\r\n|\r/", "\n", s($content));
        $et = glossarylv_end_tag($tag,0,true);
        return $st.$co.$et;
}

/**
 * Prepares file area to export as part of XML export
 *
 * @param string $tag XML tag to use for the group
 * @param int $taglevel
 * @param int $contextid
 * @param string $filearea
 * @param int $itemid
 * @return string
 */
function glossarylv_xml_export_files($tag, $taglevel, $contextid, $filearea, $itemid) {
    $co = '';
    $fs = get_file_storage();
    if ($files = $fs->get_area_files(
        $contextid, 'mod_glossarylv', $filearea, $itemid, 'itemid,filepath,filename', false)) {
        $co .= glossarylv_start_tag($tag, $taglevel, true);
        foreach ($files as $file) {
            $co .= glossarylv_start_tag('FILE', $taglevel + 1, true);
            $co .= glossarylv_full_tag('FILENAME', $taglevel + 2, false, $file->get_filename());
            $co .= glossarylv_full_tag('FILEPATH', $taglevel + 2, false, $file->get_filepath());
            $co .= glossarylv_full_tag('CONTENTS', $taglevel + 2, false, base64_encode($file->get_content()));
            $co .= glossarylv_full_tag('FILEAUTHOR', $taglevel + 2, false, $file->get_author());
            $co .= glossarylv_full_tag('FILELICENSE', $taglevel + 2, false, $file->get_license());
            $co .= glossarylv_end_tag('FILE', $taglevel + 1);
        }
        $co .= glossarylv_end_tag($tag, $taglevel);
    }
    return $co;
}

/**
 * Parses files from XML import and inserts them into file system
 *
 * @param array $xmlparent parent element in parsed XML tree
 * @param string $tag
 * @param int $contextid
 * @param string $filearea
 * @param int $itemid
 * @return int
 */
function glossarylv_xml_import_files($xmlparent, $tag, $contextid, $filearea, $itemid) {
    global $USER, $CFG;
    $count = 0;
    if (isset($xmlparent[$tag][0]['#']['FILE'])) {
        $fs = get_file_storage();
        $files = $xmlparent[$tag][0]['#']['FILE'];
        foreach ($files as $file) {
            $filerecord = array(
                'contextid' => $contextid,
                'component' => 'mod_glossarylv',
                'filearea'  => $filearea,
                'itemid'    => $itemid,
                'filepath'  => $file['#']['FILEPATH'][0]['#'],
                'filename'  => $file['#']['FILENAME'][0]['#'],
                'userid'    => $USER->id
            );
            if (array_key_exists('FILEAUTHOR', $file['#'])) {
                $filerecord['author'] = $file['#']['FILEAUTHOR'][0]['#'];
            }
            if (array_key_exists('FILELICENSE', $file['#'])) {
                $license = $file['#']['FILELICENSE'][0]['#'];
                require_once($CFG->libdir . "/licenselib.php");
                if (license_manager::get_license_by_shortname($license)) {
                    $filerecord['license'] = $license;
                }
            }
            $content =  $file['#']['CONTENTS'][0]['#'];
            $fs->create_file_from_string($filerecord, base64_decode($content));
            $count++;
        }
    }
    return $count;
}

/**
 * How many unrated entries are in the given glossarylv for a given user?
 *
 * @global moodle_database $DB
 * @param int $glossarylvid
 * @param int $userid
 * @return int
 */
function glossarylv_count_unrated_entries($glossarylvid, $userid) {
    global $DB;

    $sql = "SELECT COUNT('x') as num
              FROM {glossarylv_entries}
             WHERE glossarylvid = :glossarylvid AND
                   userid <> :userid";
    $params = array('glossarylvid' => $glossarylvid, 'userid' => $userid);
    $entries = $DB->count_records_sql($sql, $params);

    if ($entries) {
        // We need to get the contextid for the glossarylvid we have been given.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {glossarylv} g ON g.id = cm.instance
                 WHERE ctx.contextlevel = :contextlevel AND
                       m.name = 'glossarylv' AND
                       g.id = :glossarylvid";
        $contextid = $DB->get_field_sql($sql, array('glossarylvid' => $glossarylvid, 'contextlevel' => CONTEXT_MODULE));

        // Now we need to count the ratings that this user has made
        $sql = "SELECT COUNT('x') AS num
                  FROM {glossarylv_entries} e
                  JOIN {rating} r ON r.itemid = e.id
                 WHERE e.glossarylvid = :glossarylvid AND
                       r.userid = :userid AND
                       r.component = 'mod_glossarylv' AND
                       r.ratingarea = 'entry' AND
                       r.contextid = :contextid";
        $params = array('glossarylvid' => $glossarylvid, 'userid' => $userid, 'contextid' => $contextid);
        $rated = $DB->count_records_sql($sql, $params);
        if ($rated) {
            // The number or enties minus the number or rated entries equals the number of unrated
            // entries
            if ($entries > $rated) {
                return $entries - $rated;
            } else {
                return 0;    // Just in case there was a counting error
            }
        } else {
            return (int)$entries;
        }
    } else {
        return 0;
    }
}

/**
 *
 * Returns the html code to represent any pagging bar. Paramenters are:
 *
 * The function dinamically show the first and last pages, and "scroll" over pages.
 * Fully compatible with Moodle's print_paging_bar() function. Perhaps some day this
 * could replace the general one. ;-)
 *
 * @param int $totalcount total number of records to be displayed
 * @param int $page page currently selected (0 based)
 * @param int $perpage number of records per page
 * @param string $baseurl url to link in each page, the string 'page=XX' will be added automatically.
 *
 * @param int $maxpageallowed Optional maximum number of page allowed.
 * @param int $maxdisplay Optional maximum number of page links to show in the bar
 * @param string $separator Optional string to be used between pages in the bar
 * @param string $specialtext Optional string to be showed as an special link
 * @param string $specialvalue Optional value (page) to be used in the special link
 * @param bool $previousandnext Optional to decide if we want the previous and next links
 * @return string
 */
function glossarylv_get_paging_bar($totalcount, $page, $perpage, $baseurl, $maxpageallowed=99999, $maxdisplay=20, $separator="&nbsp;", $specialtext="", $specialvalue=-1, $previousandnext = true) {

    $code = '';

    $showspecial = false;
    $specialselected = false;

    //Check if we have to show the special link
    if (!empty($specialtext)) {
        $showspecial = true;
    }
    //Check if we are with the special link selected
    if ($showspecial && $page == $specialvalue) {
        $specialselected = true;
    }

    //If there are results (more than 1 page)
    if ($totalcount > $perpage) {
        $code .= "<div style=\"text-align:center\">";
        $code .= "<p>".get_string("page").":";

        $maxpage = (int)(($totalcount-1)/$perpage);

        //Lower and upper limit of page
        if ($page < 0) {
            $page = 0;
        }
        if ($page > $maxpageallowed) {
            $page = $maxpageallowed;
        }
        if ($page > $maxpage) {
            $page = $maxpage;
        }

        //Calculate the window of pages
        $pagefrom = $page - ((int)($maxdisplay / 2));
        if ($pagefrom < 0) {
            $pagefrom = 0;
        }
        $pageto = $pagefrom + $maxdisplay - 1;
        if ($pageto > $maxpageallowed) {
            $pageto = $maxpageallowed;
        }
        if ($pageto > $maxpage) {
            $pageto = $maxpage;
        }

        //Some movements can be necessary if don't see enought pages
        if ($pageto - $pagefrom < $maxdisplay - 1) {
            if ($pageto - $maxdisplay + 1 > 0) {
                $pagefrom = $pageto - $maxdisplay + 1;
            }
        }

        //Calculate first and last if necessary
        $firstpagecode = '';
        $lastpagecode = '';
        if ($pagefrom > 0) {
            $firstpagecode = "$separator<a href=\"{$baseurl}page=0\">1</a>";
            if ($pagefrom > 1) {
                $firstpagecode .= "$separator...";
            }
        }
        if ($pageto < $maxpage) {
            if ($pageto < $maxpage -1) {
                $lastpagecode = "$separator...";
            }
            $lastpagecode .= "$separator<a href=\"{$baseurl}page=$maxpage\">".($maxpage+1)."</a>";
        }

        //Previous
        if ($page > 0 && $previousandnext) {
            $pagenum = $page - 1;
            $code .= "&nbsp;(<a  href=\"{$baseurl}page=$pagenum\">".get_string("previous")."</a>)&nbsp;";
        }

        //Add first
        $code .= $firstpagecode;

        $pagenum = $pagefrom;

        //List of maxdisplay pages
        while ($pagenum <= $pageto) {
            $pagetoshow = $pagenum +1;
            if ($pagenum == $page && !$specialselected) {
                $code .= "$separator<b>$pagetoshow</b>";
            } else {
                $code .= "$separator<a href=\"{$baseurl}page=$pagenum\">$pagetoshow</a>";
            }
            $pagenum++;
        }

        //Add last
        $code .= $lastpagecode;

        //Next
        if ($page < $maxpage && $page < $maxpageallowed && $previousandnext) {
            $pagenum = $page + 1;
            $code .= "$separator(<a href=\"{$baseurl}page=$pagenum\">".get_string("next")."</a>)";
        }

        //Add special
        if ($showspecial) {
            $code .= '<br />';
            if ($specialselected) {
                $code .= "<b>$specialtext</b>";
            } else {
                $code .= "$separator<a href=\"{$baseurl}page=$specialvalue\">$specialtext</a>";
            }
        }

        //End html
        $code .= "</p>";
        $code .= "</div>";
    }

    return $code;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function glossarylv_get_view_actions() {
    return array('view','view all','view entry');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function glossarylv_get_post_actions() {
    return array('add category','add entry','approve entry','delete category','delete entry','edit category','update entry');
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the glossarylv.
 * @param object $mform form passed by reference
 */
function glossarylv_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'glossarylvheader', get_string('modulenameplural', 'glossarylv'));
    $mform->addElement('checkbox', 'reset_glossarylv_all', get_string('resetglossariesall','glossarylv'));

    $mform->addElement('select', 'reset_glossarylv_types', get_string('resetglossaries', 'glossarylv'),
                       array('main'=>get_string('mainglossarylv', 'glossarylv'), 'secondary'=>get_string('secondaryglossarylv', 'glossarylv')), array('multiple' => 'multiple'));
    $mform->setAdvanced('reset_glossarylv_types');
    $mform->disabledIf('reset_glossarylv_types', 'reset_glossarylv_all', 'checked');

    $mform->addElement('checkbox', 'reset_glossarylv_notenrolled', get_string('deletenotenrolled', 'glossarylv'));
    $mform->disabledIf('reset_glossarylv_notenrolled', 'reset_glossarylv_all', 'checked');

    $mform->addElement('checkbox', 'reset_glossarylv_ratings', get_string('deleteallratings'));
    $mform->disabledIf('reset_glossarylv_ratings', 'reset_glossarylv_all', 'checked');

    $mform->addElement('checkbox', 'reset_glossarylv_comments', get_string('deleteallcomments'));
    $mform->disabledIf('reset_glossarylv_comments', 'reset_glossarylv_all', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function glossarylv_reset_course_form_defaults($course) {
    return array('reset_glossarylv_all'=>0, 'reset_glossarylv_ratings'=>1, 'reset_glossarylv_comments'=>1, 'reset_glossarylv_notenrolled'=>0);
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid The ID of the course to reset
 * @param string $type The optional type of glossarylv. 'main', 'secondary' or ''
 */
function glossarylv_reset_gradebook($courseid, $type='') {
    global $DB;

    switch ($type) {
        case 'main'      : $type = "AND g.mainglossarylv=1"; break;
        case 'secondary' : $type = "AND g.mainglossarylv=0"; break;
        default          : $type = ""; //all
    }

    $sql = "SELECT g.*, cm.idnumber as cmidnumber, g.course as courseid
              FROM {glossarylv} g, {course_modules} cm, {modules} m
             WHERE m.name='glossarylv' AND m.id=cm.module AND cm.instance=g.id AND g.course=? $type";

    if ($glossarylvs = $DB->get_records_sql($sql, array($courseid))) {
        foreach ($glossarylvs as $glossarylv) {
            glossarylv_grade_item_update($glossarylv, 'reset');
        }
    }
}
/**
 * Actual implementation of the reset course functionality, delete all the
 * glossarylv responses for course $data->courseid.
 *
 * @global object
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function glossarylv_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/rating/lib.php');

    $componentstr = get_string('modulenameplural', 'glossarylv');
    $status = array();

    $allentriessql = "SELECT e.id
                        FROM {glossarylv_entries} e
                             JOIN {glossarylv} g ON e.glossarylvid = g.id
                       WHERE g.course = ?";

    $allglossariessql = "SELECT g.id
                           FROM {glossarylv} g
                          WHERE g.course = ?";

    $params = array($data->courseid);

    $fs = get_file_storage();

    $rm = new rating_manager();
    $ratingdeloptions = new stdClass;
    $ratingdeloptions->component = 'mod_glossarylv';
    $ratingdeloptions->ratingarea = 'entry';

    // delete entries if requested
    if (!empty($data->reset_glossarylv_all)
         or (!empty($data->reset_glossarylv_types) and in_array('main', $data->reset_glossarylv_types) and in_array('secondary', $data->reset_glossarylv_types))) {

        $params[] = 'glossarylv_entry';
        $DB->delete_records_select('comments', "itemid IN ($allentriessql) AND commentarea=?", $params);
        $DB->delete_records_select('glossarylv_alias',    "entryid IN ($allentriessql)", $params);
        $DB->delete_records_select('glossarylv_entries', "glossarylvid IN ($allglossariessql)", $params);

        // now get rid of all attachments
        if ($glossaries = $DB->get_records_sql($allglossariessql, $params)) {
            foreach ($glossaries as $glossarylvid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('glossarylv', $glossarylvid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $fs->delete_area_files($context->id, 'mod_glossarylv', 'attachment');

                //delete ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            glossarylv_reset_gradebook($data->courseid);
        }

        $status[] = array('component'=>$componentstr, 'item'=>get_string('resetglossariesall', 'glossarylv'), 'error'=>false);

    } else if (!empty($data->reset_glossarylv_types)) {
        $mainentriessql         = "$allentriessql AND g.mainglossarylv=1";
        $secondaryentriessql    = "$allentriessql AND g.mainglossarylv=0";

        $mainglossariessql      = "$allglossariessql AND g.mainglossarylv=1";
        $secondaryglossariessql = "$allglossariessql AND g.mainglossarylv=0";

        if (in_array('main', $data->reset_glossarylv_types)) {
            $params[] = 'glossarylv_entry';
            $DB->delete_records_select('comments', "itemid IN ($mainentriessql) AND commentarea=?", $params);
            $DB->delete_records_select('glossarylv_entries', "glossarylvid IN ($mainglossariessql)", $params);

            if ($glossaries = $DB->get_records_sql($mainglossariessql, $params)) {
                foreach ($glossaries as $glossarylvid=>$unused) {
                    if (!$cm = get_coursemodule_from_instance('glossarylv', $glossarylvid)) {
                        continue;
                    }
                    $context = context_module::instance($cm->id);
                    $fs->delete_area_files($context->id, 'mod_glossarylv', 'attachment');

                    //delete ratings
                    $ratingdeloptions->contextid = $context->id;
                    $rm->delete_ratings($ratingdeloptions);
                }
            }

            // remove all grades from gradebook
            if (empty($data->reset_gradebook_grades)) {
                glossarylv_reset_gradebook($data->courseid, 'main');
            }

            $status[] = array('component'=>$componentstr, 'item'=>get_string('resetglossaries', 'glossarylv').': '.get_string('mainglossarylv', 'glossarylv'), 'error'=>false);

        } else if (in_array('secondary', $data->reset_glossarylv_types)) {
            $params[] = 'glossarylv_entry';
            $DB->delete_records_select('comments', "itemid IN ($secondaryentriessql) AND commentarea=?", $params);
            $DB->delete_records_select('glossarylv_entries', "glossarylvid IN ($secondaryglossariessql)", $params);
            // remove exported source flag from entries in main glossarylv
            $DB->execute("UPDATE {glossarylv_entries}
                             SET sourceglossarylvid=0
                           WHERE glossarylvid IN ($mainglossariessql)", $params);

            if ($glossaries = $DB->get_records_sql($secondaryglossariessql, $params)) {
                foreach ($glossaries as $glossarylvid=>$unused) {
                    if (!$cm = get_coursemodule_from_instance('glossarylv', $glossarylvid)) {
                        continue;
                    }
                    $context = context_module::instance($cm->id);
                    $fs->delete_area_files($context->id, 'mod_glossarylv', 'attachment');

                    //delete ratings
                    $ratingdeloptions->contextid = $context->id;
                    $rm->delete_ratings($ratingdeloptions);
                }
            }

            // remove all grades from gradebook
            if (empty($data->reset_gradebook_grades)) {
                glossarylv_reset_gradebook($data->courseid, 'secondary');
            }

            $status[] = array('component'=>$componentstr, 'item'=>get_string('resetglossaries', 'glossarylv').': '.get_string('secondaryglossarylv', 'glossarylv'), 'error'=>false);
        }
    }

    // remove entries by users not enrolled into course
    if (!empty($data->reset_glossarylv_notenrolled)) {
        $entriessql = "SELECT e.id, e.userid, e.glossarylvid, u.id AS userexists, u.deleted AS userdeleted
                         FROM {glossarylv_entries} e
                              JOIN {glossarylv} g ON e.glossarylvid = g.id
                              LEFT JOIN {user} u ON e.userid = u.id
                        WHERE g.course = ? AND e.userid > 0";

        $course_context = context_course::instance($data->courseid);
        $notenrolled = array();
        $rs = $DB->get_recordset_sql($entriessql, $params);
        if ($rs->valid()) {
            foreach ($rs as $entry) {
                if (array_key_exists($entry->userid, $notenrolled) or !$entry->userexists or $entry->userdeleted
                  or !is_enrolled($course_context , $entry->userid)) {
                    $DB->delete_records('comments', array('commentarea'=>'glossarylv_entry', 'itemid'=>$entry->id));
                    $DB->delete_records('glossarylv_entries', array('id'=>$entry->id));

                    if ($cm = get_coursemodule_from_instance('glossarylv', $entry->glossarylvid)) {
                        $context = context_module::instance($cm->id);
                        $fs->delete_area_files($context->id, 'mod_glossarylv', 'attachment', $entry->id);

                        //delete ratings
                        $ratingdeloptions->contextid = $context->id;
                        $rm->delete_ratings($ratingdeloptions);
                    }
                }
            }
            $status[] = array('component'=>$componentstr, 'item'=>get_string('deletenotenrolled', 'glossarylv'), 'error'=>false);
        }
        $rs->close();
    }

    // remove all ratings
    if (!empty($data->reset_glossarylv_ratings)) {
        //remove ratings
        if ($glossaries = $DB->get_records_sql($allglossariessql, $params)) {
            foreach ($glossaries as $glossarylvid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('glossarylv', $glossarylvid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);

                //delete ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            glossarylv_reset_gradebook($data->courseid);
        }
        $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallratings'), 'error'=>false);
    }

    // remove comments
    if (!empty($data->reset_glossarylv_comments)) {
        $params[] = 'glossarylv_entry';
        $DB->delete_records_select('comments', "itemid IN ($allentriessql) AND commentarea= ? ", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallcomments'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('glossarylv', array('assesstimestart', 'assesstimefinish'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * Returns all other caps used in module
 * @return array
 */
function glossarylv_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/site:trustcontent', 'moodle/rating:view', 'moodle/rating:viewany', 'moodle/rating:viewall', 'moodle/rating:rate', 'moodle/comment:view', 'moodle/comment:post', 'moodle/comment:delete');
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function glossarylv_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_RATE:                    return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_COMMENT:                 return true;

        default: return null;
    }
}

/**
 * Obtains the automatic completion state for this glossarylv based on any conditions
 * in glossarylv settings.
 *
 * @global object
 * @global object
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function glossarylv_get_completion_state($course,$cm,$userid,$type) {
    global $CFG, $DB;

    // Get glossarylv details
    if (!($glossarylv=$DB->get_record('glossarylv',array('id'=>$cm->instance)))) {
        throw new Exception("Can't find glossarylv {$cm->instance}");
    }

    $result=$type; // Default return value

    if ($glossarylv->completionentries) {
        $value = $glossarylv->completionentries <=
                 $DB->count_records('glossarylv_entries',array('glossarylvid'=>$glossarylv->id, 'userid'=>$userid, 'approved'=>1));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    return $result;
}

function glossarylv_extend_navigation($navigation, $course, $module, $cm) {
    global $CFG, $DB;

    $displayformat = $DB->get_record('glossarylv_formats', array('name' => $module->displayformat));
    // Get visible tabs for the format and check if the menu needs to be displayed.
    $showtabs = glossarylv_get_visible_tabs($displayformat);

    foreach ($showtabs as $showtabkey => $showtabvalue) {

        switch($showtabvalue) {
            case GLOSSARYLV_STANDARD :
                $navigation->add(get_string('standardview', 'glossarylv'), new moodle_url('/mod/glossarylv/view.php',
                        array('id' => $cm->id, 'mode' => 'letter')));
                break;
            case GLOSSARYLV_CATEGORY :
                $navigation->add(get_string('categoryview', 'glossarylv'), new moodle_url('/mod/glossarylv/view.php',
                        array('id' => $cm->id, 'mode' => 'cat')));
                break;
            case GLOSSARYLV_DATE :
                $navigation->add(get_string('dateview', 'glossarylv'), new moodle_url('/mod/glossarylv/view.php',
                        array('id' => $cm->id, 'mode' => 'date')));
                break;
            case GLOSSARYLV_AUTHOR :
                $navigation->add(get_string('authorview', 'glossarylv'), new moodle_url('/mod/glossarylv/view.php',
                        array('id' => $cm->id, 'mode' => 'author')));
                break;
        }
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $glossarylvnode The node to add module settings to
 */
function glossarylv_extend_settings_navigation(settings_navigation $settings, navigation_node $glossarylvnode) {
    global $PAGE, $DB, $CFG, $USER;

    $mode = optional_param('mode', '', PARAM_ALPHA);
    $hook = optional_param('hook', 'ALL', PARAM_CLEAN);

    if (has_capability('mod/glossarylv:import', $PAGE->cm->context)) {
        $glossarylvnode->add(get_string('importentries', 'glossarylv'), new moodle_url('/mod/glossarylv/import.php', array('id'=>$PAGE->cm->id)));
    }

    if (has_capability('mod/glossarylv:export', $PAGE->cm->context)) {
        $glossarylvnode->add(get_string('exportentries', 'glossarylv'), new moodle_url('/mod/glossarylv/export.php', array('id'=>$PAGE->cm->id, 'mode'=>$mode, 'hook'=>$hook)));
    }

    if (has_capability('mod/glossarylv:approve', $PAGE->cm->context) && ($hiddenentries = $DB->count_records('glossarylv_entries', array('glossarylvid'=>$PAGE->cm->instance, 'approved'=>0)))) {
        $glossarylvnode->add(get_string('waitingapproval', 'glossarylv'), new moodle_url('/mod/glossarylv/view.php', array('id'=>$PAGE->cm->id, 'mode'=>'approval')));
    }

    if (has_capability('mod/glossarylv:write', $PAGE->cm->context)) {
        $glossarylvnode->add(get_string('addentry', 'glossarylv'), new moodle_url('/mod/glossarylv/edit.php', array('cmid'=>$PAGE->cm->id)));
    }

    $glossarylv = $DB->get_record('glossarylv', array("id" => $PAGE->cm->instance));

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->glossarylv_enablerssfeeds) && $glossarylv->rsstype && $glossarylv->rssarticles && has_capability('mod/glossarylv:view', $PAGE->cm->context)) {
        require_once("$CFG->libdir/rsslib.php");

        $string = get_string('rsstype','forum');

        $url = new moodle_url(rss_get_url($PAGE->cm->context->id, $USER->id, 'mod_glossarylv', $glossarylv->id));
        $glossarylvnode->add($string, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', ''));
    }
}

/**
 * Running addtional permission check on plugin, for example, plugins
 * may have switch to turn on/off comments option, this callback will
 * affect UI display, not like pluginname_comment_validate only throw
 * exceptions.
 * Capability check has been done in comment->check_permissions(), we
 * don't need to do it again here.
 *
 * @package  mod_glossarylv
 * @category comment
 *
 * @param stdClass $comment_param {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 * @return array
 */
function glossarylv_comment_permissions($comment_param) {
    return array('post'=>true, 'view'=>true);
}

/**
 * Validate comment parameter before perform other comments actions
 *
 * @package  mod_glossarylv
 * @category comment
 *
 * @param stdClass $comment_param {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 * @return boolean
 */
function glossarylv_comment_validate($comment_param) {

    global $DB;
    // validate comment area
    if ($comment_param->commentarea != 'glossarylv_entry') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$record = $DB->get_record('glossarylv_entries', array('id'=>$comment_param->itemid))) {
        throw new comment_exception('invalidcommentitemid');
    }
    if ($record->sourceglossarylvid && $record->sourceglossarylvid == $comment_param->cm->instance) {
        $glossarylv = $DB->get_record('glossarylv', array('id'=>$record->sourceglossarylvid));
    } else {
        $glossarylv = $DB->get_record('glossarylv', array('id'=>$record->glossarylvid));
    }
    if (!$glossarylv) {
        throw new comment_exception('invalidid', 'data');
    }
    if (!$course = $DB->get_record('course', array('id'=>$glossarylv->course))) {
        throw new comment_exception('coursemisconf');
    }
    if (!$cm = get_coursemodule_from_instance('glossarylv', $glossarylv->id, $course->id)) {
        throw new comment_exception('invalidcoursemodule');
    }
    $context = context_module::instance($cm->id);

    if ($glossarylv->defaultapproval and !$record->approved and !has_capability('mod/glossarylv:approve', $context)) {
        throw new comment_exception('notapproved', 'glossarylv');
    }
    // validate context id
    if ($context->id != $comment_param->context->id) {
        throw new comment_exception('invalidcontext');
    }
    // validation for comment deletion
    if (!empty($comment_param->commentid)) {
        if ($comment = $DB->get_record('comments', array('id'=>$comment_param->commentid))) {
            if ($comment->commentarea != 'glossarylv_entry') {
                throw new comment_exception('invalidcommentarea');
            }
            if ($comment->contextid != $comment_param->context->id) {
                throw new comment_exception('invalidcontext');
            }
            if ($comment->itemid != $comment_param->itemid) {
                throw new comment_exception('invalidcommentitemid');
            }
        } else {
            throw new comment_exception('invalidcommentid');
        }
    }
    return true;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function glossarylv_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array(
        'mod-glossarylv-*'=>get_string('page-mod-glossarylv-x', 'glossarylv'),
        'mod-glossarylv-view'=>get_string('page-mod-glossarylv-view', 'glossarylv'),
        'mod-glossarylv-edit'=>get_string('page-mod-glossarylv-edit', 'glossarylv'));
    return $module_pagetype;
}

/**
 * Return list of all glossarylv tabs.
 * @throws coding_exception
 * @return array
 */
function glossarylv_get_all_tabs() {

    return array (
        GLOSSARYLV_AUTHOR => get_string('authorview', 'glossarylv'),
        GLOSSARYLV_CATEGORY => get_string('categoryview', 'glossarylv'),
        GLOSSARYLV_DATE => get_string('dateview', 'glossarylv')
    );
}

/**
 * Set 'showtabs' value for glossarylv formats
 * @param stdClass $glossarylvformat record from 'glossarylv_formats' table
 */
function glossarylv_set_default_visible_tabs($glossarylvformat) {
    global $DB;

    switch($glossarylvformat->name) {
        case GLOSSARYLV_CONTINUOUS:
            $showtabs = 'standard,category,date';
            break;
        case GLOSSARYLV_DICTIONARY:
            $showtabs = 'standard';
            // Special code for upgraded instances that already had categories set up
            // in this format - enable "category" tab.
            // In new instances only 'standard' tab will be visible.
            if ($DB->record_exists_sql("SELECT 1
                    FROM {glossarylv} g, {glossarylv_categories} gc
                    WHERE g.id = gc.glossarylvid and g.displayformat = ?",
                    array(GLOSSARYLV_DICTIONARY))) {
                $showtabs .= ',category';
            }
            break;
        case GLOSSARYLV_FULLWITHOUTAUTHOR:
            $showtabs = 'standard,category,date';
            break;
        default:
            $showtabs = 'standard,category,date,author';
            break;
    }

    $DB->set_field('glossarylv_formats', 'showtabs', $showtabs, array('id' => $glossarylvformat->id));
    $glossarylvformat->showtabs = $showtabs;
}

/**
 * Convert 'showtabs' string to array
 * @param stdClass $displayformat record from 'glossarylv_formats' table
 * @return array
 */
function glossarylv_get_visible_tabs($displayformat) {
    if (empty($displayformat->showtabs)) {
        glossarylv_set_default_visible_tabs($displayformat);
    }
    $showtabs = preg_split('/,/', $displayformat->showtabs, -1, PREG_SPLIT_NO_EMPTY);

    return $showtabs;
}

/**
 * Notify that the glossarylv was viewed.
 *
 * This will trigger relevant events and activity completion.
 *
 * @param stdClass $glossarylv The glossarylv object.
 * @param stdClass $course   The course object.
 * @param stdClass $cm       The course module object.
 * @param stdClass $context  The context object.
 * @param string   $mode     The mode in which the glossarylv was viewed.
 * @since Moodle 3.1
 */
function glossarylv_view($glossarylv, $course, $cm, $context, $mode) {

    // Completion trigger.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // Trigger the course module viewed event.
    $event = \mod_glossarylv\event\course_module_viewed::create(array(
        'objectid' => $glossarylv->id,
        'context' => $context,
        'other' => array('mode' => $mode)
    ));
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('glossarylv', $glossarylv);
    $event->trigger();
}

/**
 * Notify that a glossarylv entry was viewed.
 *
 * This will trigger relevant events.
 *
 * @param stdClass $entry    The entry object.
 * @param stdClass $context  The context object.
 * @since Moodle 3.1
 */
function glossarylv_entry_view($entry, $context) {

    // Trigger the entry viewed event.
    $event = \mod_glossarylv\event\entry_viewed::create(array(
        'objectid' => $entry->id,
        'context' => $context
    ));
    $event->add_record_snapshot('glossarylv_entries', $entry);
    $event->trigger();

}

/**
 * Returns the entries of a glossarylv by letter.
 *
 * @param  object $glossarylv The glossarylv.
 * @param  context $context The context of the glossarylv.
 * @param  string $letter The letter, or ALL, or SPECIAL.
 * @param  int $from Fetch records from.
 * @param  int $limit Number of records to fetch.
 * @param  array $options Accepts:
 *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
 *                          the current user. When true, also includes the ones that the user has the permission to approve.
 * @return array The first element being the recordset, the second the number of entries.
 * @since Moodle 3.1
 */
function glossarylv_get_entries_by_letter($glossarylv, $context, $letter, $from, $limit, $options = array()) {

    $qb = new mod_glossarylv_entry_query_builder($glossarylv);
    if ($letter != 'ALL' && $letter != 'SPECIAL' && core_text::strlen($letter)) {
        $qb->filter_by_concept_letter($letter);
    }
    if ($letter == 'SPECIAL') {
        $qb->filter_by_concept_non_letter();
    }

    if (!empty($options['includenotapproved']) && has_capability('mod/glossarylv:approve', $context)) {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_ALL);
    } else {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_SELF);
    }

    $qb->add_field('*', 'entries');
    $qb->join_user();
    $qb->add_user_fields();
    $qb->order_by('concept', 'entries');
    $qb->order_by('id', 'entries', 'ASC'); // Sort on ID to avoid random ordering when entries share an ordering value.
    $qb->limit($from, $limit);

    // Fetching the entries.
    $count = $qb->count_records();
    $entries = $qb->get_records();

    return array($entries, $count);
}

/**
 * Returns the entries of a glossarylv by date.
 *
 * @param  object $glossarylv The glossarylv.
 * @param  context $context The context of the glossarylv.
 * @param  string $order The mode of ordering: CREATION or UPDATE.
 * @param  string $sort The direction of the ordering: ASC or DESC.
 * @param  int $from Fetch records from.
 * @param  int $limit Number of records to fetch.
 * @param  array $options Accepts:
 *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
 *                          the current user. When true, also includes the ones that the user has the permission to approve.
 * @return array The first element being the recordset, the second the number of entries.
 * @since Moodle 3.1
 */
function glossarylv_get_entries_by_date($glossarylv, $context, $order, $sort, $from, $limit, $options = array()) {

    $qb = new mod_glossarylv_entry_query_builder($glossarylv);
    if (!empty($options['includenotapproved']) && has_capability('mod/glossarylv:approve', $context)) {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_ALL);
    } else {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_SELF);
    }

    $qb->add_field('*', 'entries');
    $qb->join_user();
    $qb->add_user_fields();
    $qb->limit($from, $limit);

    if ($order == 'CREATION') {
        $qb->order_by('timecreated', 'entries', $sort);
    } else {
        $qb->order_by('timemodified', 'entries', $sort);
    }
    $qb->order_by('id', 'entries', $sort); // Sort on ID to avoid random ordering when entries share an ordering value.

    // Fetching the entries.
    $count = $qb->count_records();
    $entries = $qb->get_records();

    return array($entries, $count);
}

/**
 * Returns the entries of a glossarylv by category.
 *
 * @param  object $glossarylv The glossarylv.
 * @param  context $context The context of the glossarylv.
 * @param  int $categoryid The category ID, or GLOSSARYLV_SHOW_* constant.
 * @param  int $from Fetch records from.
 * @param  int $limit Number of records to fetch.
 * @param  array $options Accepts:
 *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
 *                          the current user. When true, also includes the ones that the user has the permission to approve.
 * @return array The first element being the recordset, the second the number of entries.
 * @since Moodle 3.1
 */
function glossarylv_get_entries_by_category($glossarylv, $context, $categoryid, $from, $limit, $options = array()) {

    $qb = new mod_glossarylv_entry_query_builder($glossarylv);
    if (!empty($options['includenotapproved']) && has_capability('mod/glossarylv:approve', $context)) {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_ALL);
    } else {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_SELF);
    }

    $qb->join_category($categoryid);
    $qb->join_user();

    // The first field must be the relationship ID when viewing all categories.
    if ($categoryid === GLOSSARYLV_SHOW_ALL_CATEGORIES) {
        $qb->add_field('id', 'entries_categories', 'cid');
    }

    $qb->add_field('*', 'entries');
    $qb->add_field('categoryid', 'entries_categories');
    $qb->add_user_fields();

    if ($categoryid === GLOSSARYLV_SHOW_ALL_CATEGORIES) {
        $qb->add_field('name', 'categories', 'categoryname');
        $qb->order_by('name', 'categories');

    } else if ($categoryid === GLOSSARYLV_SHOW_NOT_CATEGORISED) {
        $qb->where('categoryid', 'entries_categories', null);
    }

    // Sort on additional fields to avoid random ordering when entries share an ordering value.
    $qb->order_by('concept', 'entries');
    $qb->order_by('id', 'entries', 'ASC');
    $qb->limit($from, $limit);

    // Fetching the entries.
    $count = $qb->count_records();
    $entries = $qb->get_records();

    return array($entries, $count);
}

/**
 * Returns the entries of a glossarylv by author.
 *
 * @param  object $glossarylv The glossarylv.
 * @param  context $context The context of the glossarylv.
 * @param  string $letter The letter
 * @param  string $field The field to search: FIRSTNAME or LASTNAME.
 * @param  string $sort The sorting: ASC or DESC.
 * @param  int $from Fetch records from.
 * @param  int $limit Number of records to fetch.
 * @param  array $options Accepts:
 *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
 *                          the current user. When true, also includes the ones that the user has the permission to approve.
 * @return array The first element being the recordset, the second the number of entries.
 * @since Moodle 3.1
 */
function glossarylv_get_entries_by_author($glossarylv, $context, $letter, $field, $sort, $from, $limit, $options = array()) {

    $firstnamefirst = $field === 'FIRSTNAME';
    $qb = new mod_glossarylv_entry_query_builder($glossarylv);
    if ($letter != 'ALL' && $letter != 'SPECIAL' && core_text::strlen($letter)) {
        $qb->filter_by_author_letter($letter, $firstnamefirst);
    }
    if ($letter == 'SPECIAL') {
        $qb->filter_by_author_non_letter($firstnamefirst);
    }

    if (!empty($options['includenotapproved']) && has_capability('mod/glossarylv:approve', $context)) {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_ALL);
    } else {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_SELF);
    }

    $qb->add_field('*', 'entries');
    $qb->join_user(true);
    $qb->add_user_fields();
    $qb->order_by_author($firstnamefirst, $sort);
    $qb->order_by('concept', 'entries');
    $qb->order_by('id', 'entries', 'ASC'); // Sort on ID to avoid random ordering when entries share an ordering value.
    $qb->limit($from, $limit);

    // Fetching the entries.
    $count = $qb->count_records();
    $entries = $qb->get_records();

    return array($entries, $count);
}

/**
 * Returns the entries of a glossarylv by category.
 *
 * @param  object $glossarylv The glossarylv.
 * @param  context $context The context of the glossarylv.
 * @param  int $authorid The author ID.
 * @param  string $order The mode of ordering: CONCEPT, CREATION or UPDATE.
 * @param  string $sort The direction of the ordering: ASC or DESC.
 * @param  int $from Fetch records from.
 * @param  int $limit Number of records to fetch.
 * @param  array $options Accepts:
 *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
 *                          the current user. When true, also includes the ones that the user has the permission to approve.
 * @return array The first element being the recordset, the second the number of entries.
 * @since Moodle 3.1
 */
function glossarylv_get_entries_by_author_id($glossarylv, $context, $authorid, $order, $sort, $from, $limit, $options = array()) {

    $qb = new mod_glossarylv_entry_query_builder($glossarylv);
    if (!empty($options['includenotapproved']) && has_capability('mod/glossarylv:approve', $context)) {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_ALL);
    } else {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_SELF);
    }

    $qb->add_field('*', 'entries');
    $qb->join_user(true);
    $qb->add_user_fields();
    $qb->where('id', 'user', $authorid);

    if ($order == 'CREATION') {
        $qb->order_by('timecreated', 'entries', $sort);
    } else if ($order == 'UPDATE') {
        $qb->order_by('timemodified', 'entries', $sort);
    } else {
        $qb->order_by('concept', 'entries', $sort);
    }
    $qb->order_by('id', 'entries', $sort); // Sort on ID to avoid random ordering when entries share an ordering value.

    $qb->limit($from, $limit);

    // Fetching the entries.
    $count = $qb->count_records();
    $entries = $qb->get_records();

    return array($entries, $count);
}

/**
 * Returns the authors in a glossarylv
 *
 * @param  object $glossarylv The glossarylv.
 * @param  context $context The context of the glossarylv.
 * @param  int $limit Number of records to fetch.
 * @param  int $from Fetch records from.
 * @param  array $options Accepts:
 *                        - (bool) includenotapproved. When false, includes self even if all of their entries require approval.
 *                          When true, also includes authors only having entries pending approval.
 * @return array The first element being the recordset, the second the number of entries.
 * @since Moodle 3.1
 */
function glossarylv_get_authors($glossarylv, $context, $limit, $from, $options = array()) {
    global $DB, $USER;

    $params = array();
    $userfields = user_picture::fields('u', null);

    $approvedsql = '(ge.approved <> 0 OR ge.userid = :myid)';
    $params['myid'] = $USER->id;
    if (!empty($options['includenotapproved']) && has_capability('mod/glossarylv:approve', $context)) {
        $approvedsql = '1 = 1';
    }

    $sqlselectcount = "SELECT COUNT(DISTINCT(u.id))";
    $sqlselect = "SELECT DISTINCT(u.id) AS userId, $userfields";
    $sql = "  FROM {user} u
              JOIN {glossarylv_entries} ge
                ON ge.userid = u.id
               AND (ge.glossarylvid = :gid1 OR ge.sourceglossarylvid = :gid2)
               AND $approvedsql";
    $ordersql = " ORDER BY u.lastname, u.firstname";

    $params['gid1'] = $glossarylv->id;
    $params['gid2'] = $glossarylv->id;

    $count = $DB->count_records_sql($sqlselectcount . $sql, $params);
    $users = $DB->get_recordset_sql($sqlselect . $sql . $ordersql, $params, $from, $limit);

    return array($users, $count);
}

/**
 * Returns the categories of a glossarylv.
 *
 * @param  object $glossarylv The glossarylv.
 * @param  int $from Fetch records from.
 * @param  int $limit Number of records to fetch.
 * @return array The first element being the recordset, the second the number of entries.
 * @since Moodle 3.1
 */
function glossarylv_get_categories($glossarylv, $from, $limit) {
    global $DB;

    $count = $DB->count_records('glossarylv_categories', array('glossarylvid' => $glossarylv->id));
    $categories = $DB->get_recordset('glossarylv_categories', array('glossarylvid' => $glossarylv->id), 'name ASC', '*', $from, $limit);

    return array($categories, $count);
}

/**
 * Get the SQL where clause for searching terms.
 *
 * Note that this does not handle invalid or too short terms.
 *
 * @param array   $terms      Array of terms.
 * @param bool    $fullsearch Whether or not full search should be enabled.
 * @param int     $glossarylvid The ID of a glossarylv to reduce the search results.
 * @return array The first element being the where clause, the second array of parameters.
 * @since Moodle 3.1
 */
function glossarylv_get_search_terms_sql(array $terms, $fullsearch = true, $glossarylvid = null) {
    global $DB;
    static $i = 0;

    if ($DB->sql_regex_supported()) {
        $regexp = $DB->sql_regex(true);
        $notregexp = $DB->sql_regex(false);
    }

    $params = array();
    $conditions = array();

    foreach ($terms as $searchterm) {
        $i++;

        $not = false; // Initially we aren't going to perform NOT LIKE searches, only MSSQL and Oracle
                      // will use it to simulate the "-" operator with LIKE clause.

        if (empty($fullsearch)) {
            // With fullsearch disabled, look only within concepts and aliases.
            $concat = $DB->sql_concat('ge.concept', "' '", "COALESCE(al.alias, :emptychar{$i})");
        } else {
            // With fullsearch enabled, look also within definitions.
            $concat = $DB->sql_concat('ge.concept', "' '", 'ge.definition', "' '", "COALESCE(al.alias, :emptychar{$i})");
        }
        $params['emptychar' . $i] = '';

        // Under Oracle and MSSQL, trim the + and - operators and perform simpler LIKE (or NOT LIKE) queries.
        if (!$DB->sql_regex_supported()) {
            if (substr($searchterm, 0, 1) === '-') {
                $not = true;
            }
            $searchterm = trim($searchterm, '+-');
        }

        if (substr($searchterm, 0, 1) === '+') {
            $searchterm = trim($searchterm, '+-');
            $conditions[] = "$concat $regexp :searchterm{$i}";
            $params['searchterm' . $i] = '(^|[^a-zA-Z0-9])' . preg_quote($searchterm, '|') . '([^a-zA-Z0-9]|$)';

        } else if (substr($searchterm, 0, 1) === "-") {
            $searchterm = trim($searchterm, '+-');
            $conditions[] = "$concat $notregexp :searchterm{$i}";
            $params['searchterm' . $i] = '(^|[^a-zA-Z0-9])' . preg_quote($searchterm, '|') . '([^a-zA-Z0-9]|$)';

        } else {
            $conditions[] = $DB->sql_like($concat, ":searchterm{$i}", false, true, $not);
            $params['searchterm' . $i] = '%' . $DB->sql_like_escape($searchterm) . '%';
        }
    }

    // Reduce the search results by restricting it to one glossarylv.
    if (isset($glossarylvid)) {
        $conditions[] = 'ge.glossarylvid = :glossarylvid';
        $params['glossarylvid'] = $glossarylvid;
    }

    // When there are no conditions we add a negative one to ensure that we don't return anything.
    if (empty($conditions)) {
        $conditions[] = '1 = 2';
    }

    $where = implode(' AND ', $conditions);
    return array($where, $params);
}


/**
 * Returns the entries of a glossarylv by search.
 *
 * @param  object $glossarylv The glossarylv.
 * @param  context $context The context of the glossarylv.
 * @param  string $query The search query.
 * @param  bool $fullsearch Whether or not full search is required.
 * @param  string $order The mode of ordering: CONCEPT, CREATION or UPDATE.
 * @param  string $sort The direction of the ordering: ASC or DESC.
 * @param  int $from Fetch records from.
 * @param  int $limit Number of records to fetch.
 * @param  array $options Accepts:
 *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
 *                          the current user. When true, also includes the ones that the user has the permission to approve.
 * @return array The first element being the recordset, the second the number of entries.
 * @since Moodle 3.1
 */
function glossarylv_get_entries_by_search($glossarylv, $context, $query, $fullsearch, $order, $sort, $from, $limit,
                                        $options = array()) {
    global $DB, $USER;

    // Remove too little terms.
    $terms = explode(' ', $query);
    foreach ($terms as $key => $term) {
        if (strlen(trim($term, '+-')) < 2) {
            unset($terms[$key]);
        }
    }

    list($searchcond, $params) = glossarylv_get_search_terms_sql($terms, $fullsearch, $glossarylv->id);

    $userfields = user_picture::fields('u', null, 'userdataid', 'userdata');

    // Need one inner view here to avoid distinct + text.
    $sqlwrapheader = 'SELECT ge.*, ge.concept AS glossarylvpivot, ' . $userfields . '
                        FROM {glossarylv_entries} ge
                        LEFT JOIN {user} u ON u.id = ge.userid
                        JOIN ( ';
    $sqlwrapfooter = ' ) gei ON (ge.id = gei.id)';
    $sqlselect  = "SELECT DISTINCT ge.id";
    $sqlfrom    = "FROM {glossarylv_entries} ge
                   LEFT JOIN {glossarylv_alias} al ON al.entryid = ge.id";

    if (!empty($options['includenotapproved']) && has_capability('mod/glossarylv:approve', $context)) {
        $approvedsql = '';
    } else {
        $approvedsql = 'AND (ge.approved <> 0 OR ge.userid = :myid)';
        $params['myid'] = $USER->id;
    }

    if ($order == 'CREATION') {
        $sqlorderby = "ORDER BY ge.timecreated $sort";
    } else if ($order == 'UPDATE') {
        $sqlorderby = "ORDER BY ge.timemodified $sort";
    } else {
        $sqlorderby = "ORDER BY ge.concept $sort";
    }
    $sqlorderby .= " , ge.id ASC"; // Sort on ID to avoid random ordering when entries share an ordering value.

    $sqlwhere = "WHERE ($searchcond) $approvedsql";

    // Fetching the entries.
    $count = $DB->count_records_sql("SELECT COUNT(DISTINCT(ge.id)) $sqlfrom $sqlwhere", $params);

    $query = "$sqlwrapheader $sqlselect $sqlfrom $sqlwhere $sqlwrapfooter $sqlorderby";
    $entries = $DB->get_recordset_sql($query, $params, $from, $limit);

    return array($entries, $count);
}

/**
 * Returns the entries of a glossarylv by term.
 *
 * @param  object $glossarylv The glossarylv.
 * @param  context $context The context of the glossarylv.
 * @param  string $term The term we are searching for, a concept or alias.
 * @param  int $from Fetch records from.
 * @param  int $limit Number of records to fetch.
 * @param  array $options Accepts:
 *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
 *                          the current user. When true, also includes the ones that the user has the permission to approve.
 * @return array The first element being the recordset, the second the number of entries.
 * @since Moodle 3.1
 */
function glossarylv_get_entries_by_term($glossarylv, $context, $term, $from, $limit, $options = array()) {

    // Build the query.
    $qb = new mod_glossarylv_entry_query_builder($glossarylv);
    if (!empty($options['includenotapproved']) && has_capability('mod/glossarylv:approve', $context)) {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_ALL);
    } else {
        $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_SELF);
    }

    $qb->add_field('*', 'entries');
    $qb->join_alias();
    $qb->join_user();
    $qb->add_user_fields();
    $qb->filter_by_term($term);

    $qb->order_by('concept', 'entries');
    $qb->order_by('id', 'entries');     // Sort on ID to avoid random ordering when entries share an ordering value.
    $qb->limit($from, $limit);

    // Fetching the entries.
    $count = $qb->count_records();
    $entries = $qb->get_records();

    return array($entries, $count);
}

/**
 * Returns the entries to be approved.
 *
 * @param  object $glossarylv The glossarylv.
 * @param  context $context The context of the glossarylv.
 * @param  string $letter The letter, or ALL, or SPECIAL.
 * @param  string $order The mode of ordering: CONCEPT, CREATION or UPDATE.
 * @param  string $sort The direction of the ordering: ASC or DESC.
 * @param  int $from Fetch records from.
 * @param  int $limit Number of records to fetch.
 * @return array The first element being the recordset, the second the number of entries.
 * @since Moodle 3.1
 */
function glossarylv_get_entries_to_approve($glossarylv, $context, $letter, $order, $sort, $from, $limit) {

    $qb = new mod_glossarylv_entry_query_builder($glossarylv);
    if ($letter != 'ALL' && $letter != 'SPECIAL' && core_text::strlen($letter)) {
        $qb->filter_by_concept_letter($letter);
    }
    if ($letter == 'SPECIAL') {
        $qb->filter_by_concept_non_letter();
    }

    $qb->add_field('*', 'entries');
    $qb->join_user();
    $qb->add_user_fields();
    $qb->filter_by_non_approved(mod_glossarylv_entry_query_builder::NON_APPROVED_ONLY);
    if ($order == 'CREATION') {
        $qb->order_by('timecreated', 'entries', $sort);
    } else if ($order == 'UPDATE') {
        $qb->order_by('timemodified', 'entries', $sort);
    } else {
        $qb->order_by('concept', 'entries', $sort);
    }
    $qb->order_by('id', 'entries', $sort); // Sort on ID to avoid random ordering when entries share an ordering value.
    $qb->limit($from, $limit);

    // Fetching the entries.
    $count = $qb->count_records();
    $entries = $qb->get_records();

    return array($entries, $count);
}

/**
 * Fetch an entry.
 *
 * @param  int $id The entry ID.
 * @return object|false The entry, or false when not found.
 * @since Moodle 3.1
 */
function glossarylv_get_entry_by_id($id) {

    // Build the query.
    $qb = new mod_glossarylv_entry_query_builder();
    $qb->add_field('*', 'entries');
    $qb->join_user();
    $qb->add_user_fields();
    $qb->where('id', 'entries', $id);

    // Fetching the entries.
    $entries = $qb->get_records();
    if (empty($entries)) {
        return false;
    }
    return array_pop($entries);
}

/**
 * Checks if the current user can see the glossarylv entry.
 *
 * @since Moodle 3.1
 * @param stdClass $entry
 * @param cm_info  $cminfo
 * @return bool
 */
function glossarylv_can_view_entry($entry, $cminfo) {
    global $USER;

    $cm = $cminfo->get_course_module_record();
    $context = \context_module::instance($cm->id);

    // Recheck uservisible although it should have already been checked in core_search.
    if ($cminfo->uservisible === false) {
        return false;
    }

    // Check approval.
    if (empty($entry->approved) && $entry->userid != $USER->id && !has_capability('mod/glossarylv:approve', $context)) {
        return false;
    }

    return true;
}

/**
 * Check if a concept exists in a glossarylv.
 *
 * @param  stdClass $glossarylv glossarylv object
 * @param  string $concept the concept to check
 * @return bool true if exists
 * @since  Moodle 3.2
 */
function glossarylv_concept_exists($glossarylv, $concept) {
    global $DB;

    return $DB->record_exists_select('glossarylv_entries', 'glossarylvid = :glossarylvid AND LOWER(concept) = :concept',
        array(
            'glossarylvid' => $glossarylv->id,
            'concept'    => core_text::strtolower($concept)
        )
    );
}

/**
 * Return the editor and attachment options when editing a glossarylv entry
 *
 * @param  stdClass $course  course object
 * @param  stdClass $context context object
 * @param  stdClass $entry   entry object
 * @return array array containing the editor and attachment options
 * @since  Moodle 3.2
 */
function glossarylv_get_editor_and_attachment_options($course, $context, $entry) {
    $maxfiles = 99;                // TODO: add some setting.
    $maxbytes = $course->maxbytes; // TODO: add some setting.

    $definitionoptions = array('trusttext' => true, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes, 'context' => $context,
        'subdirs' => file_area_contains_subdirs($context, 'mod_glossarylv', 'entry', $entry->id));
    $attachmentoptions = array('subdirs' => false, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes);
    return array($definitionoptions, $attachmentoptions);
}

/**
 * Creates or updates a glossarylv entry
 *
 * @param  stdClass $entry entry data
 * @param  stdClass $course course object
 * @param  stdClass $cm course module object
 * @param  stdClass $glossarylv glossarylv object
 * @param  stdClass $context context object
 * @return stdClass the complete new or updated entry
 * @since  Moodle 3.2
 */
function glossarylv_edit_entry($entry, $course, $cm, $glossarylv, $context) {
    global $DB, $USER;

    list($definitionoptions, $attachmentoptions) = glossarylv_get_editor_and_attachment_options($course, $context, $entry);

    $timenow = time();

    $categories = empty($entry->categories) ? array() : $entry->categories;
    unset($entry->categories);
    $aliases = trim($entry->aliases);
    unset($entry->aliases);

    if (empty($entry->id)) {
        $entry->glossarylvid       = $glossarylv->id;
        $entry->timecreated      = $timenow;
        $entry->userid           = $USER->id;
        $entry->timecreated      = $timenow;
        $entry->sourceglossarylvid = 0;
        $entry->teacherentry     = has_capability('mod/glossarylv:manageentries', $context);
        $isnewentry              = true;
    } else {
        $isnewentry              = false;
    }

    $entry->concept          = trim($entry->concept);
    $entry->definition       = '';          // Updated later.
    $entry->definitionformat = FORMAT_HTML; // Updated later.
    $entry->definitiontrust  = 0;           // Updated later.
    $entry->timemodified     = $timenow;
    $entry->approved         = 0;
    $entry->usedynalink      = isset($entry->usedynalink) ? $entry->usedynalink : 0;
    $entry->casesensitive    = isset($entry->casesensitive) ? $entry->casesensitive : 0;
    $entry->fullmatch        = isset($entry->fullmatch) ? $entry->fullmatch : 0;

    if ($glossarylv->defaultapproval or has_capability('mod/glossarylv:approve', $context)) {
        $entry->approved = 1;
    }

    if ($isnewentry) {
        // Add new entry.
        $entry->id = $DB->insert_record('glossarylv_entries', $entry);
    } else {
        // Update existing entry.
        $DB->update_record('glossarylv_entries', $entry);
    }

    // Save and relink embedded images and save attachments.
    if (!empty($entry->definition_editor)) {
        $entry = file_postupdate_standard_editor($entry, 'definition', $definitionoptions, $context, 'mod_glossarylv', 'entry',
            $entry->id);
    }
    if (!empty($entry->attachment_filemanager)) {
        $entry = file_postupdate_standard_filemanager($entry, 'attachment', $attachmentoptions, $context, 'mod_glossarylv',
            'attachment', $entry->id);
    }

    // Store the updated value values.
    $DB->update_record('glossarylv_entries', $entry);

    // Refetch complete entry.
    $entry = $DB->get_record('glossarylv_entries', array('id' => $entry->id));

    // Update entry categories.
    $DB->delete_records('glossarylv_entries_categ', array('entryid' => $entry->id));
    // TODO: this deletes cats from both both main and secondary glossarylv :-(.
    if (!empty($categories) and array_search(0, $categories) === false) {
        foreach ($categories as $catid) {
            $newcategory = new stdClass();
            $newcategory->entryid    = $entry->id;
            $newcategory->categoryid = $catid;
            $DB->insert_record('glossarylv_entries_categ', $newcategory, false);
        }
    }

    // Update aliases.
    $DB->delete_records('glossarylv_alias', array('entryid' => $entry->id));
    if ($aliases !== '') {
        $aliases = explode("\n", $aliases);
        foreach ($aliases as $alias) {
            $alias = trim($alias);
            if ($alias !== '') {
                $newalias = new stdClass();
                $newalias->entryid = $entry->id;
                $newalias->alias   = $alias;
                $DB->insert_record('glossarylv_alias', $newalias, false);
            }
        }
    }

    // Trigger event and update completion (if entry was created).
    $eventparams = array(
        'context' => $context,
        'objectid' => $entry->id,
        'other' => array('concept' => $entry->concept)
    );
    if ($isnewentry) {
        $event = \mod_glossarylv\event\entry_created::create($eventparams);
    } else {
        $event = \mod_glossarylv\event\entry_updated::create($eventparams);
    }
    $event->add_record_snapshot('glossarylv_entries', $entry);
    $event->trigger();
    if ($isnewentry) {
        // Update completion state.
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC && $glossarylv->completionentries && $entry->approved) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }
    }

    // Reset caches.
    if ($isnewentry) {
        if ($entry->usedynalink and $entry->approved) {
            \mod_glossarylv\local\concept_cache::reset_glossarylv($glossarylv);
        }
    } else {
        // So many things may affect the linking, let's just purge the cache always on edit.
        \mod_glossarylv\local\concept_cache::reset_glossarylv($glossarylv);
    }
    return $entry;
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function glossarylv_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB;

    $updates = course_check_module_updates_since($cm, $from, array('attachment', 'entry'), $filter);

    $updates->entries = (object) array('updated' => false);
    $select = 'glossarylvid = :id AND (timecreated > :since1 OR timemodified > :since2)';
    $params = array('id' => $cm->instance, 'since1' => $from, 'since2' => $from);
    if (!has_capability('mod/glossarylv:approve', $cm->context)) {
        $select .= ' AND approved = 1';
    }

    $entries = $DB->get_records_select('glossarylv_entries', $select, $params, '', 'id');
    if (!empty($entries)) {
        $updates->entries->updated = true;
        $updates->entries->itemids = array_keys($entries);
    }

    return $updates;
}

/**
 * Get icon mapping for font-awesome.
 *
 * @return array
 */
function mod_glossarylv_get_fontawesome_icon_map() {
    return [
        'mod_glossarylv:export' => 'fa-download',
        'mod_glossarylv:minus' => 'fa-minus'
    ];
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_glossarylv_core_calendar_provide_event_action(calendar_event $event,
                                                      \core_calendar\action_factory $factory) {
    $cm = get_fast_modinfo($event->courseid)->instances['glossarylv'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/glossarylv/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Add a get_coursemodule_info function in case any glossarylv type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function glossarylv_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionentries';
    if (!$glossarylv = $DB->get_record('glossarylv', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $glossarylv->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('glossarylv', $glossarylv, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionentries'] = $glossarylv->completionentries;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_glossarylv_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionentries':
                if (empty($val)) {
                    continue;
                }
                $descriptions[] = get_string('completionentriesdesc', 'glossarylv', $val);
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

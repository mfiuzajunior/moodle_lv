<?php

require_once('../../config.php');
require_once('lib.php');

$concept  = optional_param('concept', '', PARAM_CLEAN);
$courseid = optional_param('courseid', 0, PARAM_INT);
$eid      = optional_param('eid', 0, PARAM_INT); // glossarylv entry id
$displayformat = optional_param('displayformat',-1, PARAM_SAFEDIR);

$url = new moodle_url('/mod/glossarylv/showentry.php');
$url->param('concept', $concept);
$url->param('courseid', $courseid);
$url->param('eid', $eid);
$url->param('displayformat', $displayformat);
$PAGE->set_url($url);

if ($CFG->forcelogin) {
    require_login();
}

if ($eid) {
    $entry = $DB->get_record('glossarylv_entries', array('id'=>$eid), '*', MUST_EXIST);
    $glossarylv = $DB->get_record('glossarylv', array('id'=>$entry->glossarylvid), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('glossarylv', $glossarylv->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    require_course_login($course, true, $cm);
    $entry->glossarylvname = $glossarylv->name;
    $entry->cmid = $cm->id;
    $entry->courseid = $cm->course;
    $entries = array($entry);

} else if ($concept) {
    $course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
    require_course_login($course);
    $entries = glossarylv_get_entries_search($concept, $courseid);

} else {
    print_error('invalidelementid');
}

$PAGE->set_pagelayout('incourse');

if ($entries) {
    foreach ($entries as $key => $entry) {
        // Need to get the course where the entry is,
        // in order to check for visibility/approve permissions there
        $entrycourse = $DB->get_record('course', array('id' => $entry->courseid), '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($entrycourse);
        // make sure the entry is visible
        if (empty($modinfo->cms[$entry->cmid]->uservisible)) {
            unset($entries[$key]);
            continue;
        }
        // make sure the entry is approved (or approvable by current user)
        if (!$entry->approved and ($USER->id != $entry->userid)) {
            $context = context_module::instance($entry->cmid);
            if (!has_capability('mod/glossarylv:approve', $context)) {
                unset($entries[$key]);
                continue;
            }
        }
        $entries[$key]->footer = "<p style=\"text-align:right\">&raquo;&nbsp;<a href=\"$CFG->wwwroot/mod/glossarylv/view.php?g=$entry->glossarylvid\">".format_string($entry->glossarylvname,true)."</a></p>";
        glossarylv_entry_view($entry, $modinfo->cms[$entry->cmid]->context);
    }
}

if (!empty($courseid)) {
    $strglossaries = get_string('modulenameplural', 'glossarylv');
    $strsearch = get_string('search');

    $PAGE->navbar->add($strglossaries);
    $PAGE->navbar->add($strsearch);
    $PAGE->set_title(strip_tags("$course->shortname: $strglossaries $strsearch"));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
} else {
    echo $OUTPUT->header();    // Needs to be something here to allow linking back to the whole glossarylv
}

if ($entries) {
    glossarylv_print_dynaentry($courseid, $entries, $displayformat);
}

/// Show one reduced footer
echo $OUTPUT->footer();

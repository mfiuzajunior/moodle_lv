<?php

require_once('../../config.php');
require_once('lib.php');

$id       = required_param('id', PARAM_INT);          // Entry ID
$confirm  = optional_param('confirm', 0, PARAM_BOOL); // export confirmation
$prevmode = required_param('prevmode', PARAM_ALPHA);
$hook     = optional_param('hook', '', PARAM_CLEAN);

$url = new moodle_url('/mod/glossarylv/exportentry.php', array('id'=>$id,'prevmode'=>$prevmode));
if ($confirm !== 0) {
    $url->param('confirm', $confirm);
}
if ($hook !== 'ALL') {
    $url->param('hook', $hook);
}
$PAGE->set_url($url);

if (!$entry = $DB->get_record('glossarylv_entries', array('id'=>$id))) {
    print_error('invalidentry');
}

if ($entry->sourceglossarylvid) {
    //already exported
    if (!$cm = get_coursemodule_from_id('glossarylv', $entry->sourceglossarylvid)) {
        print_error('invalidcoursemodule');
    }
    redirect('view.php?id='.$cm->id.'&amp;mode=entry&amp;hook='.$entry->id);
}

if (!$cm = get_coursemodule_from_instance('glossarylv', $entry->glossarylvid)) {
    print_error('invalidcoursemodule');
}

if (!$glossarylv = $DB->get_record('glossarylv', array('id'=>$cm->instance))) {
    print_error('invalidid', 'glossarylv');
}

if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
    print_error('coursemisconf');
}

require_course_login($course->id, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/glossarylv:export', $context);

$returnurl = "view.php?id=$cm->id&amp;mode=$prevmode&amp;hook=".urlencode($hook);

if (!$mainglossarylv = $DB->get_record('glossarylv', array('course'=>$cm->course, 'mainglossarylv'=>1))) {
    //main glossarylv not present
    redirect($returnurl);
}

if (!$maincm = get_coursemodule_from_instance('glossarylv', $mainglossarylv->id)) {
    print_error('invalidcoursemodule');
}

$context     = context_module::instance($cm->id);
$maincontext = context_module::instance($maincm->id);

if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
    print_error('coursemisconf');
}


$strglossaries     = get_string('modulenameplural', 'glossarylv');
$entryalreadyexist = get_string('entryalreadyexist','glossarylv');
$entryexported     = get_string('entryexported','glossarylv');

if (!$mainglossarylv->allowduplicatedentries) {
    if ($DB->record_exists_select('glossarylv_entries',
            'glossarylvid = :glossarylvid AND LOWER(concept) = :concept', array(
                'glossarylvid' => $mainglossarylv->id,
                'concept'    => core_text::strtolower($entry->concept)))) {
        $PAGE->set_title($glossarylv->name);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('errconceptalreadyexists', 'glossarylv'));
        echo $OUTPUT->continue_button($returnurl);
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        die;
    }
}

if (!data_submitted() or !$confirm or !confirm_sesskey()) {
    $PAGE->set_title($glossarylv->name);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo '<div class="boxaligncenter">';
    $areyousure = '<h2>'.format_string($entry->concept).'</h2><p align="center">'.get_string('areyousureexport','glossarylv').'<br /><b>'.format_string($mainglossarylv->name).'</b>?';
    $linkyes    = 'exportentry.php';
    $linkno     = 'view.php';
    $optionsyes = array('id'=>$entry->id, 'confirm'=>1, 'sesskey'=>sesskey(), 'prevmode'=>$prevmode, 'hook'=>$hook);
    $optionsno  = array('id'=>$cm->id, 'mode'=>$prevmode, 'hook'=>$hook);

    echo $OUTPUT->confirm($areyousure, new moodle_url($linkyes, $optionsyes), new moodle_url($linkno, $optionsno));
    echo '</div>';
    echo $OUTPUT->footer();
    die;

} else {
    $entry->glossarylvid       = $mainglossarylv->id;
    $entry->sourceglossarylvid = $glossarylv->id;

    $DB->update_record('glossarylv_entries', $entry);

    // move attachments too
    $fs = get_file_storage();

    if ($oldfiles = $fs->get_area_files($context->id, 'mod_glossarylv', 'attachment', $entry->id)) {
        foreach ($oldfiles as $oldfile) {
            $file_record = new stdClass();
            $file_record->contextid = $maincontext->id;
            $fs->create_file_from_storedfile($file_record, $oldfile);
        }
        $fs->delete_area_files($context->id, 'mod_glossarylv', 'attachment', $entry->id);
        $entry->attachment = '1';
    } else {
        $entry->attachment = '0';
    }
    $DB->update_record('glossarylv_entries', $entry);

    redirect ($returnurl);
}


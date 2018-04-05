<?php

require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);      // Course Module ID

$mode= optional_param('mode', '', PARAM_ALPHA);           // term entry cat date letter search author approval
$hook= optional_param('hook', '', PARAM_CLEAN);           // the term, entry, cat, etc... to look for based on mode
$cat = optional_param('cat',0, PARAM_ALPHANUM);

$url = new moodle_url('/mod/glossarylv/export.php', array('id'=>$id));
if ($cat !== 0) {
    $url->param('cat', $cat);
}
if ($mode !== '') {
    $url->param('mode', $mode);
}

$PAGE->set_url($url);

if (! $cm = get_coursemodule_from_id('glossarylv', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
    print_error('coursemisconf');
}

if (! $glossarylv = $DB->get_record("glossarylv", array("id"=>$cm->instance))) {
    print_error('invalidid', 'glossarylv');
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/glossarylv:export', $context);

$strglossaries = get_string("modulenameplural", "glossarylv");
$strglossarylv = get_string("modulename", "glossarylv");
$strallcategories = get_string("allcategories", "glossarylv");
$straddentrylv = get_string("addentry", "glossarylv");
$strnoentries = get_string("noentries", "glossarylv");
$strsearchindefinition = get_string("searchindefinition", "glossarylv");
$strsearch = get_string("search");
$strexportfile = get_string("exportfile", "glossarylv");
$strexportentries = get_string('exportentriestoxml', 'glossarylv');

$PAGE->set_url('/mod/glossarylv/export.php', array('id'=>$cm->id));
$PAGE->navbar->add($strexportentries);
$PAGE->set_title($glossarylv->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($strexportentries);
echo $OUTPUT->box_start('glossarylvdisplay generalbox');
$exporturl = moodle_url::make_pluginfile_url($context->id, 'mod_glossarylv', 'export', 0, "/$cat/", 'export.xml', true);

?>
    <form action="<?php echo $exporturl->out(); ?>" method="post">
        <input class="btn btn-primary" type="submit" value="<?php p($strexportfile)?>" />
    </form>
<?php
    // don't need cap check here, we share with the general export.
    if (!empty($CFG->enableportfolios) && $DB->count_records('glossarylv_entries', array('glossarylvid' => $glossarylv->id))) {
        require_once($CFG->libdir . '/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('glossarylv_full_portfolio_caller', array('id' => $cm->id), 'mod_glossarylv');
        $button->render();
    }
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
?>

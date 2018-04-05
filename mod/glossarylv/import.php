<?php

require_once("../../config.php");
require_once("lib.php");
require_once("$CFG->dirroot/course/lib.php");
require_once("$CFG->dirroot/course/modlib.php");
require_once('import_form.php');

$id = required_param('id', PARAM_INT);    // Course Module ID

$mode     = optional_param('mode', 'letter', PARAM_ALPHA );
$hook     = optional_param('hook', 'ALL', PARAM_ALPHANUM);

$url = new moodle_url('/mod/glossarylv/import.php', array('id'=>$id));
if ($mode !== 'letter') {
    $url->param('mode', $mode);
}
if ($hook !== 'ALL') {
    $url->param('hook', $hook);
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
require_capability('mod/glossarylv:import', $context);

$strglossaries = get_string("modulenameplural", "glossarylv");
$strglossarylv = get_string("modulename", "glossarylv");
$strallcategories = get_string("allcategories", "glossarylv");
$straddentry = get_string("addentry", "glossarylv");
$strnoentries = get_string("noentries", "glossarylv");
$strsearchindefinition = get_string("searchindefinition", "glossarylv");
$strsearch = get_string("search");
$strimportentries = get_string('importentriesfromxml', 'glossarylv');

$PAGE->navbar->add($strimportentries);
$PAGE->set_title($glossarylv->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($strimportentries);

$form = new mod_glossarylv_import_form();

if ( !$data = $form->get_data() ) {
    echo $OUTPUT->box_start('glossarylvdisplay generalbox');
    // display upload form
    $data = new stdClass();
    $data->id = $id;
    $form->set_data($data);
    $form->display();
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

$result = $form->get_file_content('file');

if (empty($result)) {
    echo $OUTPUT->box_start('glossarylvdisplay generalbox');
    echo $OUTPUT->continue_button('import.php?id='.$id);
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    die();
}

// Large exports are likely to take their time and memory.
core_php_time_limit::raise();
raise_memory_limit(MEMORY_EXTRA);

if ($xml = glossarylv_read_imported_file($result)) {
    $importedentries = 0;
    $importedcats    = 0;
    $entriesrejected = 0;
    $rejections      = '';
    $glossarylvcontext = $context;

    if ($data->dest == 'newglossarylv') {
        // If the user chose to create a new glossarylv
        $xmlglossarylv = $xml['GLOSSARYLV']['#']['INFO'][0]['#'];

        if ( $xmlglossarylv['NAME'][0]['#'] ) {
            $glossarylv = new stdClass();
            $glossarylv->modulename = 'glossarylv';
            $glossarylv->module = $cm->module;
            $glossarylv->name = ($xmlglossarylv['NAME'][0]['#']);
            $glossarylv->globalglossarylv = ($xmlglossarylv['GLOBALGLOSSARYLV'][0]['#']);
            $glossarylv->intro = ($xmlglossarylv['INTRO'][0]['#']);
            $glossarylv->introformat = isset($xmlglossarylv['INTROFORMAT'][0]['#']) ? $xmlglossarylv['INTROFORMAT'][0]['#'] : FORMAT_MOODLE;
            $glossarylv->showspecial = ($xmlglossarylv['SHOWSPECIAL'][0]['#']);
            $glossarylv->showalphabet = ($xmlglossarylv['SHOWALPHABET'][0]['#']);
            $glossarylv->showall = ($xmlglossarylv['SHOWALL'][0]['#']);
            $glossarylv->cmidnumber = null;

            // Setting the default values if no values were passed
            if ( isset($xmlglossarylv['ENTBYPAGE'][0]['#']) ) {
                $glossarylv->entbypage = ($xmlglossarylv['ENTBYPAGE'][0]['#']);
            } else {
                $glossarylv->entbypage = $CFG->glossarylv_entbypage;
            }
            if ( isset($xmlglossarylv['ALLOWDUPLICATEDENTRIES'][0]['#']) ) {
                $glossarylv->allowduplicatedentries = ($xmlglossarylv['ALLOWDUPLICATEDENTRIES'][0]['#']);
            } else {
                $glossarylv->allowduplicatedentries = $CFG->glossarylv_dupentries;
            }
            if ( isset($xmlglossarylv['DISPLAYFORMAT'][0]['#']) ) {
                $glossarylv->displayformat = ($xmlglossarylv['DISPLAYFORMAT'][0]['#']);
            } else {
                $glossarylv->displayformat = 2;
            }
            if ( isset($xmlglossarylv['ALLOWCOMMENTS'][0]['#']) ) {
                $glossarylv->allowcomments = ($xmlglossarylv['ALLOWCOMMENTS'][0]['#']);
            } else {
                $glossarylv->allowcomments = $CFG->glossarylv_allowcomments;
            }
            if ( isset($xmlglossarylv['USEDYNALINK'][0]['#']) ) {
                $glossarylv->usedynalink = ($xmlglossarylv['USEDYNALINK'][0]['#']);
            } else {
                $glossarylv->usedynalink = $CFG->glossarylv_linkentries;
            }
            if ( isset($xmlglossarylv['DEFAULTAPPROVAL'][0]['#']) ) {
                $glossarylv->defaultapproval = ($xmlglossarylv['DEFAULTAPPROVAL'][0]['#']);
            } else {
                $glossarylv->defaultapproval = $CFG->glossarylv_defaultapproval;
            }

            // These fields were not included in export, assume zero.
            $glossarylv->assessed = 0;
            $glossarylv->availability = null;

            // New glossarylv is to be inserted in section 0, it is always visible.
            $glossarylv->section = 0;
            $glossarylv->visible = 1;
            $glossarylv->visibleoncoursepage = 1;

            // Include new glossarylv and return the new ID
            if ( !($glossarylv = add_moduleinfo($glossarylv, $course)) ) {
                echo $OUTPUT->notification("Error while trying to create the new glossarylv.");
                glossarylv_print_tabbed_table_end();
                echo $OUTPUT->footer();
                exit;
            } else {
                $glossarylvcontext = context_module::instance($glossarylv->coursemodule);
                glossarylv_xml_import_files($xmlglossarylv, 'INTROFILES', $glossarylvcontext->id, 'intro', 0);
                echo $OUTPUT->box(get_string("newglossarylvcreated","glossarylv"),'generalbox boxaligncenter boxwidthnormal');
            }
        } else {
            echo $OUTPUT->notification("Error while trying to create the new glossarylv.");
            echo $OUTPUT->footer();
            exit;
        }
    }

    $xmlentries = $xml['GLOSSARYLV']['#']['INFO'][0]['#']['ENTRIES'][0]['#']['ENTRY'];
    $sizeofxmlentries = sizeof($xmlentries);
    for($i = 0; $i < $sizeofxmlentries; $i++) {
        // Inserting the entries
        $xmlentry = $xmlentries[$i];
        $newentry = new stdClass();
        $newentry->concept = trim($xmlentry['#']['CONCEPT'][0]['#']);
        $definition = $xmlentry['#']['DEFINITION'][0]['#'];
        if (!is_string($definition)) {
            print_error('errorparsingxml', 'glossarylv');
        }
        $newentry->definition = trusttext_strip($definition);
        if ( isset($xmlentry['#']['CASESENSITIVE'][0]['#']) ) {
            $newentry->casesensitive = $xmlentry['#']['CASESENSITIVE'][0]['#'];
        } else {
            $newentry->casesensitive = $CFG->glossarylv_casesensitive;
        }

        $permissiongranted = 1;
        if ( $newentry->concept and $newentry->definition ) {
            if ( !$glossarylv->allowduplicatedentries ) {
                // checking if the entry is valid (checking if it is duplicated when should not be)
                if ( $newentry->casesensitive ) {
                    $dupentry = $DB->record_exists_select('glossarylv_entries',
                                    'glossarylvid = :glossarylvid AND concept = :concept', array(
                                        'glossarylvid' => $glossarylv->id,
                                        'concept'    => $newentry->concept));
                } else {
                    $dupentry = $DB->record_exists_select('glossarylv_entries',
                                    'glossarylvid = :glossarylvid AND LOWER(concept) = :concept', array(
                                        'glossarylvid' => $glossarylv->id,
                                        'concept'    => core_text::strtolower($newentry->concept)));
                }
                if ($dupentry) {
                    $permissiongranted = 0;
                }
            }
        } else {
            $permissiongranted = 0;
        }
        if ($permissiongranted) {
            $newentry->glossarylvid       = $glossarylv->id;
            $newentry->sourceglossarylvid = 0;
            $newentry->approved         = 1;
            $newentry->userid           = $USER->id;
            $newentry->teacherentry     = 1;
            $newentry->definitionformat = $xmlentry['#']['FORMAT'][0]['#'];
            $newentry->timecreated      = time();
            $newentry->timemodified     = time();

            // Setting the default values if no values were passed
            if ( isset($xmlentry['#']['USEDYNALINK'][0]['#']) ) {
                $newentry->usedynalink      = $xmlentry['#']['USEDYNALINK'][0]['#'];
            } else {
                $newentry->usedynalink      = $CFG->glossarylv_linkentries;
            }
            if ( isset($xmlentry['#']['FULLMATCH'][0]['#']) ) {
                $newentry->fullmatch        = $xmlentry['#']['FULLMATCH'][0]['#'];
            } else {
                $newentry->fullmatch      = $CFG->glossarylv_fullmatch;
            }

            $newentry->id = $DB->insert_record("glossarylv_entries",$newentry);
            $importedentries++;

            $xmlaliases = @$xmlentry['#']['ALIASES'][0]['#']['ALIAS']; // ignore missing ALIASES
            $sizeofxmlaliases = sizeof($xmlaliases);
            for($k = 0; $k < $sizeofxmlaliases; $k++) {
            /// Importing aliases
                $xmlalias = $xmlaliases[$k];
                $aliasname = $xmlalias['#']['NAME'][0]['#'];

                if (!empty($aliasname)) {
                    $newalias = new stdClass();
                    $newalias->entryid = $newentry->id;
                    $newalias->alias = trim($aliasname);
                    $newalias->id = $DB->insert_record("glossarylv_alias",$newalias);
                }
            }

            if (!empty($data->catsincl)) {
                // If the categories must be imported...
                $xmlcats = @$xmlentry['#']['CATEGORIES'][0]['#']['CATEGORY']; // ignore missing CATEGORIES
                $sizeofxmlcats = sizeof($xmlcats);
                for($k = 0; $k < $sizeofxmlcats; $k++) {
                    $xmlcat = $xmlcats[$k];

                    $newcat = new stdClass();
                    $newcat->name = $xmlcat['#']['NAME'][0]['#'];
                    $newcat->usedynalink = $xmlcat['#']['USEDYNALINK'][0]['#'];
                    if ( !$category = $DB->get_record("glossarylv_categories", array("glossarylvid"=>$glossarylv->id,"name"=>$newcat->name))) {
                        // Create the category if it does not exist
                        $category = new stdClass();
                        $category->name = $newcat->name;
                        $category->glossarylvid = $glossarylv->id;
                        $category->id = $DB->insert_record("glossarylv_categories",$category);
                        $importedcats++;
                    }
                    if ( $category ) {
                        // inserting the new relation
                        $entrycat = new stdClass();
                        $entrycat->entryid    = $newentry->id;
                        $entrycat->categoryid = $category->id;
                        $DB->insert_record("glossarylv_entries_categ",$entrycat);
                    }
                }
            }

            // Import files embedded in the entry text.
            glossarylv_xml_import_files($xmlentry['#'], 'ENTRYFILES', $glossarylvcontext->id, 'entry', $newentry->id);

            // Import files attached to the entry.
            if (glossarylv_xml_import_files($xmlentry['#'], 'ATTACHMENTFILES', $glossarylvcontext->id, 'attachment', $newentry->id)) {
                $DB->update_record("glossarylv_entries", array('id' => $newentry->id, 'attachment' => '1'));
            }

        } else {
            $entriesrejected++;
            if ( $newentry->concept and $newentry->definition ) {
                // add to exception report (duplicated entry))
                $rejections .= "<tr><td>$newentry->concept</td>" .
                               "<td>" . get_string("duplicateentry","glossarylv"). "</td></tr>";
            } else {
                // add to exception report (no concept or definition found))
                $rejections .= "<tr><td>---</td>" .
                               "<td>" . get_string("noconceptfound","glossarylv"). "</td></tr>";
            }
        }
    }

    // Reset caches.
    \mod_glossarylv\local\concept_cache::reset_glossarylv($glossarylv);

    // processed entries
    echo $OUTPUT->box_start('glossarylvdisplay generalbox');
    echo '<table class="glossarylvimportexport">';
    echo '<tr>';
    echo '<td width="50%" align="right">';
    echo get_string("totalentries","glossarylv");
    echo ':</td>';
    echo '<td width="50%" align="left">';
    echo $importedentries + $entriesrejected;
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td width="50%" align="right">';
    echo get_string("importedentries","glossarylv");
    echo ':</td>';
    echo '<td width="50%" align="left">';
    echo $importedentries;
    if ( $entriesrejected ) {
        echo ' <small>(' . get_string("rejectedentries","glossarylv") . ": $entriesrejected)</small>";
    }
    echo '</td>';
    echo '</tr>';
    if (!empty($data->catsincl)) {
        echo '<tr>';
        echo '<td width="50%" align="right">';
        echo get_string("importedcategories","glossarylv");
        echo ':</td>';
        echo '<td width="50%">';
        echo $importedcats;
        echo '</td>';
        echo '</tr>';
    }
    echo '</table><hr />';

    // rejected entries
    if ($rejections) {
        echo $OUTPUT->heading(get_string("rejectionrpt","glossarylv"), 4);
        echo '<table class="glossarylvimportexport">';
        echo $rejections;
        echo '</table><hr />';
    }
/// Print continue button, based on results
    if ($importedentries) {
        echo $OUTPUT->continue_button('view.php?id='.$id);
    } else {
        echo $OUTPUT->continue_button('import.php?id='.$id);
    }
    echo $OUTPUT->box_end();
} else {
    echo $OUTPUT->box_start('glossarylvdisplay generalbox');
    echo get_string('errorparsingxml', 'glossarylv');
    echo $OUTPUT->continue_button('import.php?id='.$id);
    echo $OUTPUT->box_end();
}

/// Finish the page
echo $OUTPUT->footer();

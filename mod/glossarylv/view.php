<?php

/// This page prints a particular instance of glossarylv
require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');
require_once("$CFG->libdir/rsslib.php");

// @lvs Classes LVs
use uab\ifce\lvs\business\Item;
// ---

$id = optional_param('id', 0, PARAM_INT);           // Course Module ID
$g  = optional_param('g', 0, PARAM_INT);            // Glossarylv ID

$tab  = optional_param('tab', GLOSSARYLV_NO_VIEW, PARAM_ALPHA);    // browsing entries by categories?
$displayformat = optional_param('displayformat',-1, PARAM_INT);  // override of the glossarylv display format

$mode       = optional_param('mode', '', PARAM_ALPHA);           // term entry cat date letter search author approval
$hook       = optional_param('hook', '', PARAM_CLEAN);           // the term, entry, cat, etc... to look for based on mode
$fullsearch = optional_param('fullsearch', 0,PARAM_INT);         // full search (concept and definition) when searching?
$sortkey    = optional_param('sortkey', '', PARAM_ALPHA);// Sorted view: CREATION | UPDATE | FIRSTNAME | LASTNAME...
$sortorder  = optional_param('sortorder', 'ASC', PARAM_ALPHA);   // it defines the order of the sorting (ASC or DESC)
$offset     = optional_param('offset', 0,PARAM_INT);             // entries to bypass (for paging purposes)
$page       = optional_param('page', 0,PARAM_INT);               // Page to show (for paging purposes)
$show       = optional_param('show', '', PARAM_ALPHA);           // [ concept | alias ] => mode=term hook=$show

if (!empty($id)) {
    if (! $cm = get_coursemodule_from_id('glossarylv', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }
    if (! $glossarylv = $DB->get_record("glossarylv", array("id"=>$cm->instance))) {
        print_error('invalidid', 'glossarylv');
    }

} else if (!empty($g)) {
    if (! $glossarylv = $DB->get_record("glossarylv", array("id"=>$g))) {
        print_error('invalidid', 'glossarylv');
    }
    if (! $course = $DB->get_record("course", array("id"=>$glossarylv->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("glossarylv", $glossarylv->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $id = $cm->id;
} else {
    print_error('invalidid', 'glossarylv');
}

require_course_login($course->id, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/glossarylv:view', $context);

// Prepare format_string/text options
$fmtoptions = array(
    'context' => $context);

require_once($CFG->dirroot . '/comment/lib.php');
comment::init();

/// redirecting if adding a new entry
if ($tab == GLOSSARYLV_ADDENTRY_VIEW ) {
    redirect("edit.php?cmid=$cm->id&amp;mode=$mode");
}

/// setting the defaut number of entries per page if not set
if ( !$entriesbypage = $glossarylv->entbypage ) {
    $entriesbypage = $CFG->glossarylv_entbypage;
}

// If we have received a page, recalculate offset and page size.
$pagelimit = $entriesbypage;
if ($page > 0 && $offset == 0) {
    $offset = $page * $entriesbypage;
} else if ($page < 0) {
    $offset = 0;
    $pagelimit = 0;
}

/// setting the default values for the display mode of the current glossarylv
/// only if the glossarylv is viewed by the first time
if ( $dp = $DB->get_record('glossarylv_formats', array('name'=>$glossarylv->displayformat)) ) {
/// Based on format->defaultmode, we build the defaulttab to be showed sometimes
    $showtabs = glossarylv_get_visible_tabs($dp);
    switch ($dp->defaultmode) {
        case 'cat':
            $defaulttab = GLOSSARYLV_CATEGORY_VIEW;

            // Handle defaultmode if 'category' tab is disabled. Fallback to 'standard' tab.
            if (!in_array(GLOSSARYLV_CATEGORY, $showtabs)) {
                $defaulttab = GLOSSARYLV_STANDARD_VIEW;
            }

            break;
        case 'date':
            $defaulttab = GLOSSARYLV_DATE_VIEW;

            // Handle defaultmode if 'date' tab is disabled. Fallback to 'standard' tab.
            if (!in_array(GLOSSARYLV_DATE, $showtabs)) {
            }

            break;
        case 'author':
            $defaulttab = GLOSSARYLV_AUTHOR_VIEW;

            // Handle defaultmode if 'author' tab is disabled. Fallback to 'standard' tab.
            if (!in_array(GLOSSARYLV_AUTHOR, $showtabs)) {
                $defaulttab = GLOSSARYLV_STANDARD_VIEW;
            }

            break;
        default:
            $defaulttab = GLOSSARYLV_STANDARD_VIEW;
    }
/// Fetch the rest of variables
    $printpivot = $dp->showgroup;
    if ( $mode == '' and $hook == '' and $show == '') {
        $mode      = $dp->defaultmode;
        $hook      = $dp->defaulthook;
        $sortkey   = $dp->sortkey;
        $sortorder = $dp->sortorder;
    }
} else {
    $defaulttab = GLOSSARYLV_STANDARD_VIEW;
    $showtabs = array($defaulttab);
    $printpivot = 1;
    if ( $mode == '' and $hook == '' and $show == '') {
        $mode = 'letter';
        $hook = 'ALL';
    }
}

if ( $displayformat == -1 ) {
     $displayformat = $glossarylv->displayformat;
}

if ( $show ) {
    $mode = 'term';
    $hook = $show;
    $show = '';
}

/// stablishing flag variables
if ( $sortorder = strtolower($sortorder) ) {
    if ($sortorder != 'asc' and $sortorder != 'desc') {
        $sortorder = '';
    }
}
if ( $sortkey = strtoupper($sortkey) ) {
    if ($sortkey != 'CREATION' and
        $sortkey != 'UPDATE' and
        $sortkey != 'FIRSTNAME' and
        $sortkey != 'LASTNAME'
        ) {
        $sortkey = '';
    }
}

switch ( $mode = strtolower($mode) ) {
case 'search': /// looking for terms containing certain word(s)
    $tab = GLOSSARYLV_STANDARD_VIEW;

    //Clean a bit the search string
    $hook = trim(strip_tags($hook));

break;

case 'entry':  /// Looking for a certain entry id
    $tab = GLOSSARYLV_STANDARD_VIEW;
    if ( $dp = $DB->get_record("glossarylv_formats", array("name"=>$glossarylv->displayformat)) ) {
        $displayformat = $dp->popupformatname;
    }
break;

case 'cat':    /// Looking for a certain cat
    $tab = GLOSSARYLV_CATEGORY_VIEW;

    // Validation - we don't want to display 'category' tab if it is disabled.
    if (!in_array(GLOSSARYLV_CATEGORY, $showtabs)) {
        $tab = GLOSSARYLV_STANDARD_VIEW;
    }

    if ( $hook > 0 ) {
        $category = $DB->get_record("glossarylv_categories", array("id"=>$hook));
    }
break;

case 'approval':    /// Looking for entries waiting for approval
    $tab = GLOSSARYLV_APPROVAL_VIEW;
    // Override the display format with the approvaldisplayformat
    if ($glossarylv->approvaldisplayformat !== 'default' && ($df = $DB->get_record("glossarylv_formats",
            array("name" => $glossarylv->approvaldisplayformat)))) {
        $displayformat = $df->popupformatname;
    }
    if ( !$hook and !$sortkey and !$sortorder) {
        $hook = 'ALL';
    }
break;

case 'term':   /// Looking for entries that include certain term in its concept, definition or aliases
    $tab = GLOSSARYLV_STANDARD_VIEW;
break;

case 'date':
    $tab = GLOSSARYLV_DATE_VIEW;

    // Validation - we dont want to display 'date' tab if it is disabled.
    if (!in_array(GLOSSARYLV_DATE, $showtabs)) {
        $tab = GLOSSARYLV_STANDARD_VIEW;
    }

    if ( !$sortkey ) {
        $sortkey = 'UPDATE';
    }
    if ( !$sortorder ) {
        $sortorder = 'desc';
    }
break;

case 'author':  /// Looking for entries, browsed by author
    $tab = GLOSSARYLV_AUTHOR_VIEW;

    // Validation - we dont want to display 'author' tab if it is disabled.
    if (!in_array(GLOSSARYLV_AUTHOR, $showtabs)) {
        $tab = GLOSSARYLV_STANDARD_VIEW;
    }

    if ( !$hook ) {
        $hook = 'ALL';
    }
    if ( !$sortkey ) {
        $sortkey = 'FIRSTNAME';
    }
    if ( !$sortorder ) {
        $sortorder = 'asc';
    }
break;

case 'letter':  /// Looking for entries that begin with a certain letter, ALL or SPECIAL characters
default:
    $tab = GLOSSARYLV_STANDARD_VIEW;
    if ( !$hook ) {
        $hook = 'ALL';
    }
break;
}

switch ( $tab ) {
case GLOSSARYLV_IMPORT_VIEW:
case GLOSSARYLV_EXPORT_VIEW:
case GLOSSARYLV_APPROVAL_VIEW:
    $showcommonelements = 0;
break;

default:
    $showcommonelements = 1;
break;
}

// Trigger module viewed event.
glossarylv_view($glossarylv, $course, $cm, $context, $mode);

/// Printing the heading
$strglossaries = get_string("modulenameplural", "glossarylv");
$strglossarylv = get_string("modulename", "glossarylv");
$strallcategories = get_string("allcategories", "glossarylv");
$straddentry = get_string("addentry", "glossarylv");
$strnoentries = get_string("noentries", "glossarylv");
$strsearchindefinition = get_string("searchindefinition", "glossarylv");
$strsearch = get_string("search");
$strwaitingapproval = get_string('waitingapproval', 'glossarylv');

/// If we are in approval mode, prit special header
$PAGE->set_title($glossarylv->name);
$PAGE->set_heading($course->fullname);
$url = new moodle_url('/mod/glossarylv/view.php', array('id'=>$cm->id));
if (isset($mode)) {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);

if (!empty($CFG->enablerssfeeds) && !empty($CFG->glossarylv_enablerssfeeds)
    && $glossarylv->rsstype && $glossarylv->rssarticles) {

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': '. format_string($glossarylv->name);
    rss_add_http_header($context, 'mod_glossarylv', $glossarylv, $rsstitle);
}

if ($tab == GLOSSARYLV_APPROVAL_VIEW) {
    require_capability('mod/glossarylv:approve', $context);
    $PAGE->navbar->add($strwaitingapproval);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strwaitingapproval);
} else { /// Print standard header
    echo $OUTPUT->header();
}
echo $OUTPUT->heading(format_string($glossarylv->name), 2);

/// All this depends if whe have $showcommonelements
if ($showcommonelements) {
/// To calculate available options
    $availableoptions = '';

/// Decide about to print the import link
    /*if (has_capability('mod/glossarylv:import', $context)) {
        $availableoptions = '<span class="helplink">' .
                            '<a href="' . $CFG->wwwroot . '/mod/glossarylv/import.php?id=' . $cm->id . '"' .
                            '  title="' . s(get_string('importentries', 'glossarylv')) . '">' .
                            get_string('importentries', 'glossarylv') . '</a>' .
                            '</span>';
    }
/// Decide about to print the export link
    if (has_capability('mod/glossarylv:export', $context)) {
        if ($availableoptions) {
            $availableoptions .= '&nbsp;/&nbsp;';
        }
        $availableoptions .='<span class="helplink">' .
                            '<a href="' . $CFG->wwwroot . '/mod/glossarylv/export.php?id=' . $cm->id .
                            '&amp;mode='.$mode . '&amp;hook=' . urlencode($hook) . '"' .
                            '  title="' . s(get_string('exportentries', 'glossarylv')) . '">' .
                            get_string('exportentries', 'glossarylv') . '</a>' .
                            '</span>';
    }*/

/// Decide about to print the approval link
    if (has_capability('mod/glossarylv:approve', $context)) {
    /// Check we have pending entries
        if ($hiddenentries = $DB->count_records('glossarylv_entries', array('glossarylvid'=>$glossarylv->id, 'approved'=>0))) {
            if ($availableoptions) {
                $availableoptions .= '<br />';
            }
            $availableoptions .='<span class="helplink">' .
                                '<a href="' . $CFG->wwwroot . '/mod/glossarylv/view.php?id=' . $cm->id .
                                '&amp;mode=approval' . '"' .
                                '  title="' . s(get_string('waitingapproval', 'glossarylv')) . '">' .
                                get_string('waitingapproval', 'glossarylv') . ' ('.$hiddenentries.')</a>' .
                                '</span>';
        }
    }

/// Start to print glossarylv controls
//        print_box_start('glossarylvcontrol clearfix');
    echo '<div class="glossarylvcontrol" style="text-align: right">';
    echo $availableoptions;

/// The print icon
    if ( $showcommonelements and $mode != 'search') {
        if (has_capability('mod/glossarylv:manageentries', $context) or $glossarylv->allowprintview) {
            $params = array(
                'id'        => $cm->id,
                'mode'      => $mode,
                'hook'      => $hook,
                'sortkey'   => $sortkey,
                'sortorder' => $sortorder,
                'offset'    => $offset,
                'pagelimit' => $pagelimit
            );
            $printurl = new moodle_url('/mod/glossarylv/print.php', $params);
            $printtitle = get_string('printerfriendly', 'glossarylv');
            $printattributes = array(
                'class' => 'printicon',
                'title' => $printtitle
            );
            echo html_writer::link($printurl, $printtitle, $printattributes);
        }
    }
/// End glossarylv controls
//        print_box_end(); /// glossarylvcontrol
    echo '</div><br />';

//        print_box('&nbsp;', 'clearer');
}

/// Info box
if ($glossarylv->intro && $showcommonelements) {
    echo $OUTPUT->box(format_module_intro('glossarylv', $glossarylv, $cm->id), 'generalbox', 'intro');
}

/// Search box
if ($showcommonelements ) {
    echo '<form method="post" class="form form-inline m-b-1" action="view.php">';


    if ($mode == 'search') {
        echo '<input type="text" name="hook" size="20" value="'.s($hook).'" alt="'.$strsearch.'" class="form-control"/> ';
    } else {
        echo '<input type="text" name="hook" size="20" value="" alt="'.$strsearch.'" class="form-control"/> ';
    }
    echo '<input type="submit" value="'.$strsearch.'" name="searchbutton" class="btn btn-secondary m-r-1"/> ';
    if ($fullsearch || $mode != 'search') {
        $fullsearchchecked = 'checked="checked"';
    } else {
        $fullsearchchecked = '';
    }
    echo '<span class="checkbox"><label for="fullsearch">';
    echo ' <input type="checkbox" name="fullsearch" id="fullsearch" value="1" '.$fullsearchchecked.'/> ';
    echo '<input type="hidden" name="mode" value="search" />';
    echo '<input type="hidden" name="id" value="'.$cm->id.'" />';
    echo $strsearchindefinition.'</label></span>';

    echo '</form>';
}

/// Show the add entry button if allowed
if (has_capability('mod/glossarylv:write', $context) && $showcommonelements ) {
    echo '<div class="singlebutton glossarylvaddentry">';
    echo "<form class=\"form form-inline m-b-1\" id=\"newentryform\" method=\"get\" action=\"$CFG->wwwroot/mod/glossarylv/edit.php\">";
    echo '<div>';
    echo "<input type=\"hidden\" name=\"cmid\" value=\"$cm->id\" />";
    echo '<input type="submit" value="'.get_string('addentry', 'glossarylv').'" class="btn btn-secondary" />';
    echo '</div>';
    echo '</form>';
    echo "</div>\n";
}


require("tabs.php");

require("sql.php");

/// printing the entries
$entriesshown = 0;
$currentpivot = '';
$paging = NULL;

if ($allentries) {

    //Decide if we must show the ALL link in the pagebar
    $specialtext = '';
    if ($glossarylv->showall) {
        $specialtext = get_string("allentries","glossarylv");
    }

    //Build paging bar
    $paging = glossarylv_get_paging_bar($count, $page, $entriesbypage, "view.php?id=$id&amp;mode=$mode&amp;hook=".urlencode($hook)."&amp;sortkey=$sortkey&amp;sortorder=$sortorder&amp;fullsearch=$fullsearch&amp;",9999,10,'&nbsp;&nbsp;', $specialtext, -1);

    echo '<div class="paging">';
    echo $paging;
    echo '</div>';

    //load ratings
    require_once($CFG->dirroot.'/rating/lib.php');
    if ($glossarylv->assessed != RATING_AGGREGATE_NONE) {
        $ratingoptions = new stdClass;
        $ratingoptions->context = $context;
        $ratingoptions->component = 'mod_glossarylv';
        $ratingoptions->ratingarea = 'entry';
        $ratingoptions->items = $allentries;
        $ratingoptions->aggregate = $glossarylv->assessed;//the aggregation method
        $ratingoptions->scaleid = $glossarylv->scale;
        $ratingoptions->userid = $USER->id;
        $ratingoptions->returnurl = $CFG->wwwroot.'/mod/glossarylv/view.php?id='.$cm->id;
        $ratingoptions->assesstimestart = $glossarylv->assesstimestart;
        $ratingoptions->assesstimefinish = $glossarylv->assesstimefinish;

        $rm = new rating_manager();
        $allentries = $rm->get_ratings($ratingoptions);
    }

   foreach ($allentries as $entry) {
        // @lvs Adiciona um Item LV na entrada atual
        $wrappedItem = new stdClass();
        $wrappedItem->id = $entry->id;
        $wrappedItem->userid = $entry->userid;
        $wrappedItem->created = $entry->created;
        $entry->itemlv = new Item('glossariolv', 'entry', $wrappedItem);
        // ----
 
        // Setting the pivot for the current entry
        if ($printpivot) {
            $pivot = $entry->{$pivotkey};
            $upperpivot = core_text::strtoupper($pivot);
            $pivottoshow = core_text::strtoupper(format_string($pivot, true, $fmtoptions));

            // Reduce pivot to 1cc if necessary.
            if (!$fullpivot) {
                $upperpivot = core_text::substr($upperpivot, 0, 1);
                $pivottoshow = core_text::substr($pivottoshow, 0, 1);
            }

            // If there's a group break.
            if ($currentpivot != $upperpivot) {
                $currentpivot = $upperpivot;

                // print the group break if apply

                echo '<div>';
                echo '<table cellspacing="0" class="glossarylvcategoryheader">';

                echo '<tr>';
                if ($userispivot) {
                // printing the user icon if defined (only when browsing authors)
                    echo '<th align="left">';
                    $user = mod_glossarylv_entry_query_builder::get_user_from_record($entry);
                    echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                    $pivottoshow = fullname($user, has_capability('moodle/site:viewfullnames', context_course::instance($course->id)));
                } else {
                    echo '<th >';
                }

                echo $OUTPUT->heading($pivottoshow, 3);
                echo "</th></tr></table></div>\n";
            }
        }

        /// highlight the term if necessary
        if ($mode == 'search') {
            //We have to strip any word starting by + and take out words starting by -
            //to make highlight works properly
            $searchterms = explode(' ', $hook);    // Search for words independently
            foreach ($searchterms as $key => $searchterm) {
                if (preg_match('/^\-/',$searchterm)) {
                    unset($searchterms[$key]);
                } else {
                    $searchterms[$key] = preg_replace('/^\+/','',$searchterm);
                }
                //Avoid highlight of <2 len strings. It's a well known hilight limitation.
                if (strlen($searchterm) < 2) {
                    unset($searchterms[$key]);
                }
            }
            $strippedsearch = implode(' ', $searchterms);    // Rebuild the string
            $entry->highlight = $strippedsearch;
        }

        /// and finally print the entry.
        glossarylv_print_entry($course, $cm, $glossarylv, $entry, $mode, $hook,1,$displayformat);
        $entriesshown++;
    }
}
if ( !$entriesshown ) {
    echo $OUTPUT->box(get_string("noentries","glossarylv"), "generalbox boxaligncenter boxwidthwide");
}

if (!empty($formsent)) {
    // close the form properly if used
    echo "</div>";
    echo "</form>";
}

if ( $paging ) {
    echo '<hr />';
    echo '<div class="paging">';
    echo $paging;
    echo '</div>';
}
echo '<br />';
glossarylv_print_tabbed_table_end();

/// Finish the page
echo $OUTPUT->footer();

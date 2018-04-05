<?php
    if (!isset($sortorder)) {
        $sortorder = '';
    }
    if (!isset($sortkey)) {
        $sortkey = '';
    }

    //make sure variables are properly cleaned
    $sortkey   = clean_param($sortkey, PARAM_ALPHA);// Sorted view: CREATION | UPDATE | FIRSTNAME | LASTNAME...
    $sortorder = clean_param($sortorder, PARAM_ALPHA);   // it defines the order of the sorting (ASC or DESC)

    $toolsrow = array();
    $browserow = array();
    $inactive = array();
    $activated = array();

    if (!has_capability('mod/glossarylv:approve', $context) && $tab == GLOSSARYLV_APPROVAL_VIEW) {
    /// Non-teachers going to approval view go to defaulttab
        $tab = $defaulttab;
    }

    // Get visible tabs for the format and check tab needs to be displayed.
    $dt = glossarylv_get_visible_tabs($dp);

    if (in_array(GLOSSARYLV_STANDARD, $dt)) {
        $browserow[] = new tabobject(GLOSSARYLV_STANDARD_VIEW,
            $CFG->wwwroot.'/mod/glossarylv/view.php?id='.$id.'&amp;mode=letter',
            get_string('standardview', 'glossarylv'));
    }

    if (in_array(GLOSSARYLV_CATEGORY, $dt)) {
        $browserow[] = new tabobject(GLOSSARYLV_CATEGORY_VIEW,
            $CFG->wwwroot.'/mod/glossarylv/view.php?id='.$id.'&amp;mode=cat',
            get_string('categoryview', 'glossarylv'));
    }

    if (in_array(GLOSSARYLV_DATE, $dt)) {
        $browserow[] = new tabobject(GLOSSARYLV_DATE_VIEW,
            $CFG->wwwroot.'/mod/glossarylv/view.php?id='.$id.'&amp;mode=date',
            get_string('dateview', 'glossarylv'));
    }

    if (in_array(GLOSSARYLV_AUTHOR, $dt)) {
        $browserow[] = new tabobject(GLOSSARYLV_AUTHOR_VIEW,
            $CFG->wwwroot.'/mod/glossarylv/view.php?id='.$id.'&amp;mode=author',
            get_string('authorview', 'glossarylv'));
    }

    if ($tab < GLOSSARYLV_STANDARD_VIEW || $tab > GLOSSARYLV_AUTHOR_VIEW) {   // We are on second row
        $inactive = array('edit');
        $activated = array('edit');

        $browserow[] = new tabobject('edit', '#', get_string('edit'));
    }

/// Put all this info together

    $tabrows = array();
    $tabrows[] = $browserow;     // Always put these at the top
    if ($toolsrow) {
        $tabrows[] = $toolsrow;
    }

?>
  <div class="glossarylvdisplay">


<?php
if ($showcommonelements && (count($tabrows[0]) > 1)) {
    print_tabs($tabrows, $tab, $inactive, $activated);
}
?>

  <div class="entrybox">

<?php

    if (!isset($category)) {
        $category = "";
    }


    switch ($tab) {
        case GLOSSARYLV_CATEGORY_VIEW:
            glossarylv_print_categories_menu($cm, $glossarylv, $hook, $category);
        break;
        case GLOSSARYLV_APPROVAL_VIEW:
            glossarylv_print_approval_menu($cm, $glossarylv, $mode, $hook, $sortkey, $sortorder);
        break;
        case GLOSSARYLV_AUTHOR_VIEW:
            $search = "";
            glossarylv_print_author_menu($cm, $glossarylv, "author", $hook, $sortkey, $sortorder, 'print');
        break;
        case GLOSSARYLV_IMPORT_VIEW:
            $search = "";
            $l = "";
            glossarylv_print_import_menu($cm, $glossarylv, 'import', $hook, $sortkey, $sortorder);
        break;
        case GLOSSARYLV_EXPORT_VIEW:
            $search = "";
            $l = "";
            glossarylv_print_export_menu($cm, $glossarylv, 'export', $hook, $sortkey, $sortorder);
        break;
        case GLOSSARYLV_DATE_VIEW:
            if (!$sortkey) {
                $sortkey = 'UPDATE';
            }
            if (!$sortorder) {
                $sortorder = 'desc';
            }
            glossarylv_print_alphabet_menu($cm, $glossarylv, "date", $hook, $sortkey, $sortorder);
        break;
        case GLOSSARYLV_STANDARD_VIEW:
        default:
            glossarylv_print_alphabet_menu($cm, $glossarylv, "letter", $hook, $sortkey, $sortorder);
            if ($mode == 'search' and $hook) {
                echo html_writer::tag('div', "$strsearch: $hook");
            }
        break;
    }
    echo html_writer::empty_tag('hr');
?>

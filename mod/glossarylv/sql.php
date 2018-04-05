<?php

/**
 * SQL.PHP
 *    This file is include from view.php and print.php
 * @copyright 2003
 **/

/**
 * This file defines, or redefines, the following variables:
 *
 * bool $userispivot Whether the user is the pivot.
 * bool $fullpivot Whether the pivot should be displayed in full.
 * bool $printpivot Whether the pivot should be displayed.
 * string $pivotkey The property of the record at which the pivot is.
 * int $count The number of records matching the request.
 * array $allentries The entries matching the request.
 * mixed $field Unset in this file.
 * mixed $entry Unset in this file.
 * mixed $canapprove Unset in this file.
 *
 * It relies on the following variables:
 *
 * object $glossarylv The glossarylv object.
 * context $context The glossarylv context.
 * mixed $hook The hook for the selected tab.
 * string $sortkey The key to sort the records.
 * string $sortorder The order of the sorting.
 * int $offset The number of records to skip.
 * int $pagelimit The number of entries on this page, or 0 if unlimited.
 * string $mode The mode of browsing.
 * string $tab The tab selected.
 */

$userispivot = false;
$fullpivot = true;
$pivotkey = 'concept';

switch ($tab) {

    case GLOSSARYLV_AUTHOR_VIEW:
        $userispivot = true;
        $pivotkey = 'userid';
        $field = ($sortkey == 'LASTNAME' ? 'LASTNAME' : 'FIRSTNAME');
        list($allentries, $count) = glossarylv_get_entries_by_author($glossarylv, $context, $hook,
            $field, $sortorder, $offset, $pagelimit);
        unset($field);
        break;

    case GLOSSARYLV_CATEGORY_VIEW:
        $hook = (int) $hook; // Make sure it's properly casted to int.
        list($allentries, $count) = glossarylv_get_entries_by_category($glossarylv, $context, $hook, $offset, $pagelimit);
        $pivotkey = 'categoryname';
        if ($hook != GLOSSARYLV_SHOW_ALL_CATEGORIES) {
            $printpivot = false;
        }
        break;

    case GLOSSARYLV_DATE_VIEW:
        $printpivot = false;
        $field = ($sortkey == 'CREATION' ? 'CREATION' : 'UPDATE');
        list($allentries, $count) = glossarylv_get_entries_by_date($glossarylv, $context, $field, $sortorder,
            $offset, $pagelimit);
        unset($field);
        break;

    case GLOSSARYLV_APPROVAL_VIEW:
        $fullpivot = false;
        $printpivot = false;
        list($allentries, $count) = glossarylv_get_entries_to_approve($glossarylv, $context, $hook, $sortkey, $sortorder,
            $offset, $pagelimit);
        break;

    case GLOSSARYLV_STANDARD_VIEW:
    default:
        $fullpivot = false;
        switch ($mode) {
            case 'search':
                list($allentries, $count) = glossarylv_get_entries_by_search($glossarylv, $context, $hook, $fullsearch,
                    $sortkey, $sortorder, $offset, $pagelimit);
                break;

            case 'term':
                $printpivot = false;
                list($allentries, $count) = glossarylv_get_entries_by_term($glossarylv, $context, $hook, $offset, $pagelimit);
                break;

            case 'entry':
                $printpivot = false;
                $entry = glossarylv_get_entry_by_id($hook);
                $canapprove = has_capability('mod/glossarylv:approve', $context);
                if ($entry && ($entry->glossarylvid == $glossarylv->id || $entry->sourceglossarylvid != $glossarylv->id)
                        && (!empty($entry->approved) || $entry->userid == $USER->id || $canapprove)) {
                    $count = 1;
                    $allentries = array($entry);
                } else {
                    $count = 0;
                    $allentries = array();
                }
                unset($entry, $canapprove);
                break;

            case 'letter':
            default:
                list($allentries, $count) = glossarylv_get_entries_by_letter($glossarylv, $context, $hook, $offset, $pagelimit);
                break;
        }
        break;
}

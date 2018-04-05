<?php

function glossarylv_show_entry_dictionary($course, $cm, $glossarylv, $entry, $mode='', $hook='', $printicons=1, $aliases=true) {

    global $CFG, $USER, $OUTPUT;

    echo '<table class="glossarylvpost dictionary" cellspacing="0">';
    echo '<tr valign="top">';
    echo '<td class="entry">';
    glossarylv_print_entry_approval($cm, $entry, $mode);
    echo '<div class="concept">';
    glossarylv_print_entry_concept($entry);
    echo '</div> ';
    glossarylv_print_entry_definition($entry, $glossarylv, $cm);
    glossarylv_print_entry_attachment($entry, $cm, 'html');
    if (core_tag_tag::is_enabled('mod_glossarylv', 'glossarylv_entries')) {
        echo $OUTPUT->tag_list(core_tag_tag::get_item_tags('mod_glossarylv', 'glossarylv_entries', $entry->id), null, 'glossarylv-tags');
    }
    echo '</td></tr>';
    echo '<tr valign="top"><td class="entrylowersection">';
    glossarylv_print_entry_lower_section($course, $cm, $glossarylv, $entry, $mode, $hook, $printicons, $aliases);
    echo '</td>';
    echo '</tr>';
    echo "</table>\n";
}

function glossarylv_print_entry_dictionary($course, $cm, $glossarylv, $entry, $mode='', $hook='', $printicons=1) {

    //The print view for this format is exactly the normal view, so we use it

    //Take out autolinking in definitions in print view
    $entry->definition = '<span class="nolink">'.$entry->definition.'</span>';

    //Call to view function (without icons, ratings and aliases) and return its result
    return glossarylv_show_entry_dictionary($course, $cm, $glossarylv, $entry, $mode, $hook, false, false, false);
}



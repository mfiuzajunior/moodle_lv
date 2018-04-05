<?php

function glossarylv_show_entry_faq($course, $cm, $glossarylv, $entry, $mode="", $hook="", $printicons=1, $aliases=true) {
    global $USER, $OUTPUT;
    if ( $entry ) {

        echo '<table class="glossarylvpost faq" cellspacing="0">';

        echo '<tr valign="top">';
        echo '<th class="entryheader">';
        $entry->course = $course->id;

        echo '<div class="concept">' . get_string('question','glossarylv') . ': ';
        glossarylv_print_entry_concept($entry);
        echo '</div>';

        echo '<span class="time">('.get_string('lastedited').': '.
             userdate($entry->timemodified).')</span>';
        echo '</th>';
        echo '<td class="entryattachment">';

        glossarylv_print_entry_approval($cm, $entry, $mode);
        echo '</td>';

        echo '</tr>';

        echo "\n<tr>";
        echo '<td colspan="2" class="entry">';
        echo '<b>'.get_string('answer','glossarylv').':</b> ';

        glossarylv_print_entry_definition($entry, $glossarylv, $cm);
        glossarylv_print_entry_attachment($entry, $cm, 'html');

        if (core_tag_tag::is_enabled('mod_glossarylv', 'glossarylv_entries')) {
            echo $OUTPUT->tag_list(
                core_tag_tag::get_item_tags('mod_glossarylv', 'glossarylv_entries', $entry->id), null, 'glossarylv-tags');
        }

        echo '</td></tr>';
        echo '<tr valign="top"><td colspan="3" class="entrylowersection">';
        glossarylv_print_entry_lower_section($course, $cm, $glossarylv, $entry, $mode, $hook, $printicons, $aliases);
        echo '</td></tr></table>';

    } else {
        echo '<div style="text-align:center">';
        print_string('noentry', 'glossarylv');
        echo '</div>';
    }
}

function glossarylv_print_entry_faq($course, $cm, $glossarylv, $entry, $mode='', $hook='', $printicons=1) {

    //The print view for this format is exactly the normal view, so we use it

    //Take out autolinking in definitions un print view
    $entry->definition = '<span class="nolink">'.$entry->definition.'</span>';

    //Call to view function (without icons, ratings and aliases) and return its result
    return glossarylv_show_entry_faq($course, $cm, $glossarylv, $entry, $mode, $hook, false, false, false);

}



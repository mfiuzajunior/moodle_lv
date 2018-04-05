<?php

function glossarylv_show_entry_fullwithauthor($course, $cm, $glossarylv, $entry, $mode="", $hook="", $printicons=1, $aliases=true) {
    global $CFG, $USER, $DB, $OUTPUT;


    $user = $DB->get_record('user', array('id'=>$entry->userid));
    $strby = get_string('writtenby', 'glossarylv');

    if ($entry) {
        echo '<table class="glossarylvpost fullwithauthor" cellspacing="0">';
        echo '<tr valign="top">';

        echo '<td class="picture">';
        echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
        echo '</td>';

        echo '<th class="entryheader">';

        echo '<div class="concept">';
        glossarylv_print_entry_concept($entry);
        echo '</div>';

        $fullname = fullname($user);
        $by = new stdClass();
        $by->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&amp;course='.$course->id.'">'.$fullname.'</a>';
        $by->date = userdate($entry->timemodified);
        echo '<span class="author">'.get_string('bynameondate', 'forum', $by).'</span>';

        echo '</th>';
        echo '<td class="entryattachment">';

        glossarylv_print_entry_approval($cm, $entry, $mode);
        echo '</td>';

        echo '</tr>';

        echo '<tr valign="top">';
        echo '<td class="left">&nbsp;</td>';
        echo '<td colspan="2" class="entry">';

        glossarylv_print_entry_definition($entry, $glossarylv, $cm);
        glossarylv_print_entry_attachment($entry, $cm, 'html');

        if (core_tag_tag::is_enabled('mod_glossarylv', 'glossarylv_entries')) {
            echo $OUTPUT->tag_list(
                core_tag_tag::get_item_tags('mod_glossarylv', 'glossarylv_entries', $entry->id), null, 'glossarylv-tags');
        }

        echo '</td></tr>';
        echo '<tr valign="top">';
        echo '<td class="left">&nbsp;</td>';
        echo '<td colspan="2" class="entrylowersection">';

        glossarylv_print_entry_lower_section($course, $cm, $glossarylv, $entry, $mode, $hook, $printicons, $aliases);
        echo ' ';
        echo '</td></tr>';
        echo "</table>\n";
    } else {
        echo '<div style="text-align:center">';
        print_string('noentry', 'glossarylv');
        echo '</div>';
    }
}

function glossarylv_print_entry_fullwithauthor($course, $cm, $glossarylv, $entry, $mode="", $hook="", $printicons=1) {

    //The print view for this format is exactly the normal view, so we use it

    //Take out autolinking in definitions un print view
    $entry->definition = '<span class="nolink">'.$entry->definition.'</span>';

    //Call to view function (without icons, ratings and aliases) and return its result
    return glossarylv_show_entry_fullwithauthor($course, $cm, $glossarylv, $entry, $mode, $hook, false, false, false);

}



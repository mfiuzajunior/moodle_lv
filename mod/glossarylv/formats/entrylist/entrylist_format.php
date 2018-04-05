<?php

function glossarylv_show_entry_entrylist($course, $cm, $glossarylv, $entry, $mode='', $hook='', $printicons=1, $aliases=true) {
    global $USER, $OUTPUT;

    $return = false;

    echo '<table class="glossarylvpost entrylist" cellspacing="0">';

    echo '<tr valign="top">';
    echo '<td class="entry">';
    if ($entry) {
        glossarylv_print_entry_approval($cm, $entry, $mode);

        $anchortagcontents = glossarylv_print_entry_concept($entry, true);

        $link = new moodle_url('/mod/glossarylv/showentry.php', array('courseid' => $course->id,
                'eid' => $entry->id, 'displayformat' => 'dictionary'));
        $anchor = html_writer::link($link, $anchortagcontents);

        echo "<div class=\"concept\">$anchor</div> ";
        echo '</td><td align="right" class="entrylowersection">';
        if ($printicons) {
            glossarylv_print_entry_icons($course, $cm, $glossarylv, $entry, $mode, $hook,'print');
        }
        if (!empty($entry->rating)) {
            echo '<br />';
            echo '<span class="ratings">';
            $return = glossarylv_print_entry_ratings($course, $entry);
            echo '</span>';
        }
        echo '<br />';
    } else {
        echo '<div style="text-align:center">';
        print_string('noentry', 'glossarylv');
        echo '</div>';
    }
    echo '</td></tr>';

    echo "</table>\n";
    return $return;
}

function glossarylv_print_entry_entrylist($course, $cm, $glossarylv, $entry, $mode='', $hook='', $printicons=1) {
    //Take out autolinking in definitions un print view
    // TODO use <nolink> tags MDL-15555.
    $entry->definition = '<span class="nolink">'.$entry->definition.'</span>';

    echo html_writer::start_tag('table', array('class' => 'glossarylvpost entrylist mod-glossarylv-entrylist'));
    echo html_writer::start_tag('tr');
    echo html_writer::start_tag('td', array('class' => 'entry mod-glossarylv-entry'));
    echo html_writer::start_tag('div', array('class' => 'mod-glossarylv-concept'));
    glossarylv_print_entry_concept($entry);
    echo html_writer::end_tag('div');
    echo html_writer::start_tag('div', array('class' => 'mod-glossarylv-definition'));
    glossarylv_print_entry_definition($entry, $glossarylv, $cm);
    echo html_writer::end_tag('div');
    echo html_writer::start_tag('div', array('class' => 'mod-glossarylv-lower-section'));
    glossarylv_print_entry_lower_section($course, $cm, $glossarylv, $entry, $mode, $hook, false, false);
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('table');
}



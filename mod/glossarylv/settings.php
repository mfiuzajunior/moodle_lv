<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/glossarylv/lib.php');

    $settings->add(new admin_setting_heading('glossarylv_normal_header', get_string('glossarylvleveldefaultsettings', 'glossarylv'), ''));

    $settings->add(new admin_setting_configtext('glossarylv_entbypage', get_string('entbypage', 'glossarylv'),
                       get_string('entbypage', 'glossarylv'), 10, PARAM_INT));


    $settings->add(new admin_setting_configcheckbox('glossarylv_dupentries', get_string('allowduplicatedentries', 'glossarylv'),
                       get_string('cnfallowdupentries', 'glossarylv'), 0));

    $settings->add(new admin_setting_configcheckbox('glossarylv_allowcomments', get_string('allowcomments', 'glossarylv'),
                       get_string('cnfallowcomments', 'glossarylv'), 0));

    $settings->add(new admin_setting_configcheckbox('glossarylv_linkbydefault', get_string('usedynalink', 'glossarylv'),
                       get_string('cnflinkglossaries', 'glossarylv'), 1));

    $settings->add(new admin_setting_configcheckbox('glossarylv_defaultapproval', get_string('defaultapproval', 'glossarylv'),
                       get_string('cnfapprovalstatus', 'glossarylv'), 1));


    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'glossarylv').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'glossarylv');
    }
    $settings->add(new admin_setting_configselect('glossarylv_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));


    $settings->add(new admin_setting_heading('glossarylv_levdev_header', get_string('entryleveldefaultsettings', 'glossarylv'), ''));

    $settings->add(new admin_setting_configcheckbox('glossarylv_linkentries', get_string('usedynalink', 'glossarylv'),
                       get_string('cnflinkentry', 'glossarylv'), 0));

    $settings->add(new admin_setting_configcheckbox('glossarylv_casesensitive', get_string('casesensitive', 'glossarylv'),
                       get_string('cnfcasesensitive', 'glossarylv'), 0));

    $settings->add(new admin_setting_configcheckbox('glossarylv_fullmatch', get_string('fullmatch', 'glossarylv'),
                       get_string('cnffullmatch', 'glossarylv'), 0));


    //Update and get available formats
    $recformats = glossarylv_get_available_formats();
    $formats = array();
    //Take names
    foreach ($recformats as $format) {
        $formats[$format->id] = get_string("displayformat$format->name", "glossarylv");
    }
    asort($formats);

    $str = '<table>';
    foreach ($formats as $formatid=>$formatname) {
        $recformat = $DB->get_record('glossarylv_formats', array('id'=>$formatid));
        $str .= '<tr>';
        $str .= '<td>' . $formatname . '</td>';
        $eicon = "<a title=\"".get_string("edit")."\" href=\"$CFG->wwwroot/mod/glossarylv/formats.php?id=$formatid&amp;mode=edit\">";
        $eicon .= $OUTPUT->pix_icon('t/edit', get_string('edit')). "</a>";
        if ( $recformat->visible ) {
            $vtitle = get_string("hide");
            $vicon  = "t/hide";
        } else {
            $vtitle = get_string("show");
            $vicon  = "t/show";
        }
        $url = "$CFG->wwwroot/mod/glossarylv/formats.php?id=$formatid&amp;mode=visible&amp;sesskey=".sesskey();
        $viconlink = "<a title=\"$vtitle\" href=\"$url\">";
        $viconlink .= $OUTPUT->pix_icon($vicon, $vtitle) . "</a>";

        $str .= '<td align="center">' . $eicon . '&nbsp;&nbsp;' . $viconlink . '</td>';
        $str .= '</tr>';
    }
    $str .= '</table>';

    $settings->add(new admin_setting_heading('glossarylv_formats_header', get_string('displayformatssetup', 'glossarylv'), $str));
}

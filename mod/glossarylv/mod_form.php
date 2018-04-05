<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_glossarylv_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE, $DB;

        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        if (has_capability('mod/glossarylv:manageentries', context_system::instance())) {
            $mform->addElement('checkbox', 'globalglossarylv', get_string('isglobal', 'glossarylv'));
            $mform->addHelpButton('globalglossarylv', 'isglobal', 'glossarylv');

        }else{
            $mform->addElement('hidden', 'globalglossarylv');
            $mform->setType('globalglossarylv', PARAM_INT);
        }

        $options = array(1=>get_string('mainglossarylv', 'glossarylv'), 0=>get_string('secondaryglossarylv', 'glossarylv'));
        $mform->addElement('select', 'mainglossarylv', get_string('glossarylvtype', 'glossarylv'), $options);
        $mform->addHelpButton('mainglossarylv', 'glossarylvtype', 'glossarylv');
        $mform->setDefault('mainglossarylv', 0);

        // ----------------------------------------------------------------------
        $mform->addElement('header', 'entrieshdr', get_string('entries', 'glossarylv'));

        $mform->addElement('selectyesno', 'defaultapproval', get_string('defaultapproval', 'glossarylv'));
        $mform->setDefault('defaultapproval', $CFG->glossarylv_defaultapproval);
        $mform->addHelpButton('defaultapproval', 'defaultapproval', 'glossarylv');

        $mform->addElement('selectyesno', 'editalways', get_string('editalways', 'glossarylv'));
        $mform->setDefault('editalways', 0);
        $mform->addHelpButton('editalways', 'editalways', 'glossarylv');

        $mform->addElement('selectyesno', 'allowduplicatedentries', get_string('allowduplicatedentries', 'glossarylv'));
        $mform->setDefault('allowduplicatedentries', $CFG->glossarylv_dupentries);
        $mform->addHelpButton('allowduplicatedentries', 'allowduplicatedentries', 'glossarylv');

        $mform->addElement('selectyesno', 'allowcomments', get_string('allowcomments', 'glossarylv'));
        $mform->setDefault('allowcomments', $CFG->glossarylv_allowcomments);
        $mform->addHelpButton('allowcomments', 'allowcomments', 'glossarylv');

        $mform->addElement('selectyesno', 'usedynalink', get_string('usedynalink', 'glossarylv'));
        $mform->setDefault('usedynalink', $CFG->glossarylv_linkbydefault);
        $mform->addHelpButton('usedynalink', 'usedynalink', 'glossarylv');

        // ----------------------------------------------------------------------
        $mform->addElement('header', 'appearancehdr', get_string('appearance'));

        // Get and update available formats.
        $recformats = glossarylv_get_available_formats();
        $formats = array();
        foreach ($recformats as $format) {
           $formats[$format->name] = get_string('displayformat'.$format->name, 'glossarylv');
        }
        asort($formats);
        $mform->addElement('select', 'displayformat', get_string('displayformat', 'glossarylv'), $formats);
        $mform->setDefault('displayformat', 'dictionary');
        $mform->addHelpButton('displayformat', 'displayformat', 'glossarylv');

        $displayformats['default'] = get_string('displayformatdefault', 'glossarylv');
        $displayformats = array_merge($displayformats, $formats);
        $mform->addElement('select', 'approvaldisplayformat', get_string('approvaldisplayformat', 'glossarylv'), $displayformats);
        $mform->setDefault('approvaldisplayformat', 'default');
        $mform->addHelpButton('approvaldisplayformat', 'approvaldisplayformat', 'glossarylv');

        $mform->addElement('text', 'entbypage', get_string('entbypage', 'glossarylv'));
        $mform->setDefault('entbypage', $this->get_default_entbypage());
        $mform->addRule('entbypage', null, 'numeric', null, 'client');
        $mform->setType('entbypage', PARAM_INT);

        $mform->addElement('selectyesno', 'showalphabet', get_string('showalphabet', 'glossarylv'));
        $mform->setDefault('showalphabet', 1);
        $mform->addHelpButton('showalphabet', 'showalphabet', 'glossarylv');

        $mform->addElement('selectyesno', 'showall', get_string('showall', 'glossarylv'));
        $mform->setDefault('showall', 1);
        $mform->addHelpButton('showall', 'showall', 'glossarylv');

        $mform->addElement('selectyesno', 'showspecial', get_string('showspecial', 'glossarylv'));
        $mform->setDefault('showspecial', 1);
        $mform->addHelpButton('showspecial', 'showspecial', 'glossarylv');

        $mform->addElement('selectyesno', 'allowprintview', get_string('allowprintview', 'glossarylv'));
        $mform->setDefault('allowprintview', 1);
        $mform->addHelpButton('allowprintview', 'allowprintview', 'glossarylv');

        if ($CFG->enablerssfeeds && isset($CFG->glossarylv_enablerssfeeds) && $CFG->glossarylv_enablerssfeeds) {
//-------------------------------------------------------------------------------
            $mform->addElement('header', 'rssheader', get_string('rss'));
            $choices = array();
            $choices[0] = get_string('none');
            $choices[1] = get_string('withauthor', 'glossarylv');
            $choices[2] = get_string('withoutauthor', 'glossarylv');
            $mform->addElement('select', 'rsstype', get_string('rsstype'), $choices);
            $mform->addHelpButton('rsstype', 'rsstype', 'glossarylv');

            $choices = array();
            $choices[0] = '0';
            $choices[1] = '1';
            $choices[2] = '2';
            $choices[3] = '3';
            $choices[4] = '4';
            $choices[5] = '5';
            $choices[10] = '10';
            $choices[15] = '15';
            $choices[20] = '20';
            $choices[25] = '25';
            $choices[30] = '30';
            $choices[40] = '40';
            $choices[50] = '50';
            $mform->addElement('select', 'rssarticles', get_string('rssarticles'), $choices);
            $mform->addHelpButton('rssarticles', 'rssarticles', 'glossarylv');
            $mform->disabledIf('rssarticles', 'rsstype', 'eq', 0);
        }

//-------------------------------------------------------------------------------

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

//-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    function definition_after_data() {
        global $COURSE, $DB;

        parent::definition_after_data();
        $mform    =& $this->_form;
        $mainglossarylvel =& $mform->getElement('mainglossarylv');
        $mainglossarylv = $DB->get_record('glossarylv', array('mainglossarylv'=>1, 'course'=>$COURSE->id));
        if ($mainglossarylv && ($mainglossarylv->id != $mform->getElementValue('instance'))){
            //secondary glossarylv, a main one already exists in this course.
            $mainglossarylvel->setValue(0);
            $mainglossarylvel->freeze();
            $mainglossarylvel->setPersistantFreeze(true);
        } else {
            $mainglossarylvel->unfreeze();
            $mainglossarylvel->setPersistantFreeze(false);

        }
    }

    function data_preprocessing(&$default_values){
        parent::data_preprocessing($default_values);

        // Fallsback on the default setting if 'Entries shown per page' has been left blank.
        // This prevents the field from being required and expand its section which should not
        // be the case if there is a default value defined.
        if (empty($default_values['entbypage']) || $default_values['entbypage'] < 0) {
            $default_values['entbypage'] = $this->get_default_entbypage();
        }

        // Set up the completion checkboxes which aren't part of standard data.
        // Tick by default if Add mode or if completion entries settings is set to 1 or more.
        if (empty($this->_instance) || !empty($default_values['completionentries'])) {
            $default_values['completionentriesenabled'] = 1;
        } else {
            $default_values['completionentriesenabled'] = 0;
        }
        if (empty($default_values['completionentries'])) {
            $default_values['completionentries']=1;
        }
    }

    function add_completion_rules() {
        $mform =& $this->_form;

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionentriesenabled', '', get_string('completionentries','glossarylv'));
        $group[] =& $mform->createElement('text', 'completionentries', '', array('size'=>3));
        $mform->setType('completionentries', PARAM_INT);
        $mform->addGroup($group, 'completionentriesgroup', get_string('completionentriesgroup','glossarylv'), array(' '), false);
        $mform->disabledIf('completionentries','completionentriesenabled','notchecked');

        return array('completionentriesgroup');
    }

    function completion_rule_enabled($data) {
        return (!empty($data['completionentriesenabled']) && $data['completionentries']!=0);
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked
            $autocompletion = !empty($data->completion) && $data->completion==COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionentriesenabled) || !$autocompletion) {
                $data->completionentries = 0;
            }
        }
    }

    /**
     * Returns the default value for 'Entries shown per page'.
     *
     * @return int default for number of entries per page.
     */
    protected function get_default_entbypage() {
        global $CFG;
        return !empty($CFG->glossarylv_entbypage) ? $CFG->glossarylv_entbypage : 10;
    }

}


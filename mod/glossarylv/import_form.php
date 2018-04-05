<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');

class mod_glossarylv_import_form extends moodleform {

    function definition() {
        global $CFG;
        $mform =& $this->_form;
        $cmid = $this->_customdata['id'];

        $mform->addElement('filepicker', 'file', get_string('filetoimport', 'glossarylv'));
        $mform->addHelpButton('file', 'filetoimport', 'glossarylv');
        $options = array();
        $options['current'] = get_string('currentglossarylv', 'glossarylv');
        $options['newglossarylv'] = get_string('newglossarylv', 'glossarylv');
        $mform->addElement('select', 'dest', get_string('destination', 'glossarylv'), $options);
        $mform->addHelpButton('dest', 'destination', 'glossarylv');
        $mform->addElement('checkbox', 'catsincl', get_string('importcategories', 'glossarylv'));
        $submit_string = get_string('submit');
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $this->add_action_buttons(false, $submit_string);
    }
}

<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/lib/formslib.php');

class mod_glossarylv_entry_form extends moodleform {

    function definition() {
        global $CFG, $DB;

        $mform = $this->_form;

        $currententry      = $this->_customdata['current'];
        $glossarylv          = $this->_customdata['glossarylv'];
        $cm                = $this->_customdata['cm'];
        $definitionoptions = $this->_customdata['definitionoptions'];
        $attachmentoptions = $this->_customdata['attachmentoptions'];

        $context  = context_module::instance($cm->id);
        // Prepare format_string/text options
        $fmtoptions = array(
            'context' => $context);

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'concept', get_string('concept', 'glossarylv'));
        $mform->setType('concept', PARAM_TEXT);
        $mform->addRule('concept', null, 'required', null, 'client');

        $mform->addElement('editor', 'definition_editor', get_string('definition', 'glossarylv'), null, $definitionoptions);
        $mform->setType('definition_editor', PARAM_RAW);
        $mform->addRule('definition_editor', get_string('required'), 'required', null, 'client');

        if ($categories = $DB->get_records_menu('glossarylv_categories', array('glossarylvid'=>$glossarylv->id), 'name ASC', 'id, name')){
            foreach ($categories as $id => $name) {
                $categories[$id] = format_string($name, true, $fmtoptions);
            }
            $categories = array(0 => get_string('notcategorised', 'glossarylv')) + $categories;
            $categoriesEl = $mform->addElement('select', 'categories', get_string('categories', 'glossarylv'), $categories);
            $categoriesEl->setMultiple(true);
            $categoriesEl->setSize(5);
        }

        $mform->addElement('textarea', 'aliases', get_string('aliases', 'glossarylv'), 'rows="2" cols="40"');
        $mform->setType('aliases', PARAM_TEXT);
        $mform->addHelpButton('aliases', 'aliases', 'glossarylv');

        $mform->addElement('filemanager', 'attachment_filemanager', get_string('attachment', 'glossarylv'), null, $attachmentoptions);
        $mform->addHelpButton('attachment_filemanager', 'attachment', 'glossarylv');

        if (!$glossarylv->usedynalink) {
            $mform->addElement('hidden', 'usedynalink',   $CFG->glossarylv_linkentries);
            $mform->setType('usedynalink', PARAM_INT);
            $mform->addElement('hidden', 'casesensitive', $CFG->glossarylv_casesensitive);
            $mform->setType('casesensitive', PARAM_INT);
            $mform->addElement('hidden', 'fullmatch',     $CFG->glossarylv_fullmatch);
            $mform->setType('fullmatch', PARAM_INT);

        } else {
//-------------------------------------------------------------------------------
            $mform->addElement('header', 'linkinghdr', get_string('linking', 'glossarylv'));

            $mform->addElement('checkbox', 'usedynalink', get_string('entryusedynalink', 'glossarylv'));
            $mform->addHelpButton('usedynalink', 'entryusedynalink', 'glossarylv');
            $mform->setDefault('usedynalink', $CFG->glossarylv_linkentries);

            $mform->addElement('checkbox', 'casesensitive', get_string('casesensitive', 'glossarylv'));
            $mform->addHelpButton('casesensitive', 'casesensitive', 'glossarylv');
            $mform->disabledIf('casesensitive', 'usedynalink');
            $mform->setDefault('casesensitive', $CFG->glossarylv_casesensitive);

            $mform->addElement('checkbox', 'fullmatch', get_string('fullmatch', 'glossarylv'));
            $mform->addHelpButton('fullmatch', 'fullmatch', 'glossarylv');
            $mform->disabledIf('fullmatch', 'usedynalink');
            $mform->setDefault('fullmatch', $CFG->glossarylv_fullmatch);
        }

        if (core_tag_tag::is_enabled('mod_glossarylv', 'glossarylv_entries')) {
            $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));

            $mform->addElement('tags', 'tags', get_string('tags'),
                array('itemtype' => 'glossarylv_entries', 'component' => 'mod_glossarylv'));
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

//-------------------------------------------------------------------------------
        $this->add_action_buttons();

//-------------------------------------------------------------------------------
        $this->set_data($currententry);
    }

    function validation($data, $files) {
        global $CFG, $USER, $DB;
        $errors = parent::validation($data, $files);

        $glossarylv = $this->_customdata['glossarylv'];
        $cm       = $this->_customdata['cm'];
        $context  = context_module::instance($cm->id);

        $id = (int)$data['id'];
        $data['concept'] = trim($data['concept']);

        if ($id) {
            //We are updating an entry, so we compare current session user with
            //existing entry user to avoid some potential problems if secureforms=off
            //Perhaps too much security? Anyway thanks to skodak (Bug 1823)
            $old = $DB->get_record('glossarylv_entries', array('id'=>$id));
            $ineditperiod = ((time() - $old->timecreated <  $CFG->maxeditingtime) || $glossarylv->editalways);
            if ((!$ineditperiod || $USER->id != $old->userid) and !has_capability('mod/glossarylv:manageentries', $context)) {
                if ($USER->id != $old->userid) {
                    $errors['concept'] = get_string('errcannoteditothers', 'glossarylv');
                } elseif (!$ineditperiod) {
                    $errors['concept'] = get_string('erredittimeexpired', 'glossarylv');
                }
            }
            if (!$glossarylv->allowduplicatedentries) {
                if ($DB->record_exists_select('glossarylv_entries',
                        'glossarylvid = :glossarylvid AND LOWER(concept) = :concept AND id != :id', array(
                            'glossarylvid' => $glossarylv->id,
                            'concept'    => core_text::strtolower($data['concept']),
                            'id'         => $id))) {
                    $errors['concept'] = get_string('errconceptalreadyexists', 'glossarylv');
                }
            }

        } else {
            if (!$glossarylv->allowduplicatedentries) {
                if (glossarylv_concept_exists($glossarylv, $data['concept'])) {
                    $errors['concept'] = get_string('errconceptalreadyexists', 'glossarylv');
                }
            }
        }

        return $errors;
    }
}


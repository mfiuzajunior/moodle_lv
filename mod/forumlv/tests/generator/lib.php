<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * mod_forumlv data generator
 *
 * @package    mod_forumlv
 * @category   test
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Forumlv module data generator class
 *
 * @package    mod_forumlv
 * @category   test
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_forumlv_generator extends testing_module_generator {

    /**
     * @var int keep track of how many forumlv discussions have been created.
     */
    protected $forumlvdiscussioncount = 0;

    /**
     * @var int keep track of how many forumlv posts have been created.
     */
    protected $forumlvpostcount = 0;

    /**
     * @var int keep track of how many forumlv subscriptions have been created.
     */
    protected $forumlvsubscriptionscount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->forumlvdiscussioncount = 0;
        $this->forumlvpostcount = 0;
        $this->forumlvsubscriptionscount = 0;

        parent::reset();
    }

    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/forumlv/lib.php');
        $record = (object)(array)$record;

        if (!isset($record->type)) {
            $record->type = 'general';
        }
        if (!isset($record->assessed)) {
            $record->assessed = 0;
        }
        if (!isset($record->scale)) {
            $record->scale = 0;
        }
        if (!isset($record->forcesubscribe)) {
            $record->forcesubscribe = FORUMLV_CHOOSESUBSCRIBE;
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * Function to create a dummy subscription.
     *
     * @param array|stdClass $record
     * @return stdClass the subscription object
     */
    public function create_subscription($record = null) {
        global $DB;

        // Increment the forumlv subscription count.
        $this->forumlvsubscriptionscount++;

        $record = (array)$record;

        if (!isset($record['course'])) {
            throw new coding_exception('course must be present in phpunit_util::create_subscription() $record');
        }

        if (!isset($record['forumlv'])) {
            throw new coding_exception('forumlv must be present in phpunit_util::create_subscription() $record');
        }

        if (!isset($record['userid'])) {
            throw new coding_exception('userid must be present in phpunit_util::create_subscription() $record');
        }

        $record = (object)$record;

        // Add the subscription.
        $record->id = $DB->insert_record('forumlv_subscriptions', $record);

        return $record;
    }

    /**
     * Function to create a dummy discussion.
     *
     * @param array|stdClass $record
     * @return stdClass the discussion object
     */
    public function create_discussion($record = null) {
        global $DB;

        // Increment the forumlv discussion count.
        $this->forumlvdiscussioncount++;

        $record = (array) $record;

        if (!isset($record['course'])) {
            throw new coding_exception('course must be present in phpunit_util::create_discussion() $record');
        }

        if (!isset($record['forumlv'])) {
            throw new coding_exception('forumlv must be present in phpunit_util::create_discussion() $record');
        }

        if (!isset($record['userid'])) {
            throw new coding_exception('userid must be present in phpunit_util::create_discussion() $record');
        }

        if (!isset($record['name'])) {
            $record['name'] = "Discussion " . $this->forumlvdiscussioncount;
        }

        if (!isset($record['subject'])) {
            $record['subject'] = "Subject for discussion " . $this->forumlvdiscussioncount;
        }

        if (!isset($record['message'])) {
            $record['message'] = html_writer::tag('p', 'Message for discussion ' . $this->forumlvdiscussioncount);
        }

        if (!isset($record['messageformat'])) {
            $record['messageformat'] = editors_get_preferred_format();
        }

        if (!isset($record['messagetrust'])) {
            $record['messagetrust'] = "";
        }

        if (!isset($record['assessed'])) {
            $record['assessed'] = '1';
        }

        if (!isset($record['groupid'])) {
            $record['groupid'] = "-1";
        }

        if (!isset($record['timestart'])) {
            $record['timestart'] = "0";
        }

        if (!isset($record['timeend'])) {
            $record['timeend'] = "0";
        }

        if (!isset($record['mailnow'])) {
            $record['mailnow'] = "0";
        }

        if (isset($record['timemodified'])) {
            $timemodified = $record['timemodified'];
        }

        if (!isset($record['pinned'])) {
            $record['pinned'] = FORUMLV_DISCUSSION_UNPINNED;
        }

        if (isset($record['mailed'])) {
            $mailed = $record['mailed'];
        }

        $record = (object) $record;

        // Add the discussion.
        $record->id = forumlv_add_discussion($record, null, null, $record->userid);

        if (isset($timemodified) || isset($mailed)) {
            $post = $DB->get_record('forumlv_posts', array('discussion' => $record->id));

            if (isset($mailed)) {
                $post->mailed = $mailed;
            }

            if (isset($timemodified)) {
                // Enforce the time modified.
                $record->timemodified = $timemodified;
                $post->modified = $post->created = $timemodified;

                $DB->update_record('forumlv_discussions', $record);
            }

            $DB->update_record('forumlv_posts', $post);
        }

        return $record;
    }

    /**
     * Function to create a dummy post.
     *
     * @param array|stdClass $record
     * @return stdClass the post object
     */
    public function create_post($record = null) {
        global $DB;

        // Increment the forumlv post count.
        $this->forumlvpostcount++;

        // Variable to store time.
        $time = time() + $this->forumlvpostcount;

        $record = (array) $record;

        if (!isset($record['discussion'])) {
            throw new coding_exception('discussion must be present in phpunit_util::create_post() $record');
        }

        if (!isset($record['userid'])) {
            throw new coding_exception('userid must be present in phpunit_util::create_post() $record');
        }

        if (!isset($record['parent'])) {
            $record['parent'] = 0;
        }

        if (!isset($record['subject'])) {
            $record['subject'] = 'Forumlv post subject ' . $this->forumlvpostcount;
        }

        if (!isset($record['message'])) {
            $record['message'] = html_writer::tag('p', 'Forumlv message post ' . $this->forumlvpostcount);
        }

        if (!isset($record['created'])) {
            $record['created'] = $time;
        }

        if (!isset($record['modified'])) {
            $record['modified'] = $time;
        }

        if (!isset($record['mailed'])) {
            $record['mailed'] = 0;
        }

        if (!isset($record['messageformat'])) {
            $record['messageformat'] = 0;
        }

        if (!isset($record['messagetrust'])) {
            $record['messagetrust'] = 0;
        }

        if (!isset($record['attachment'])) {
            $record['attachment'] = "";
        }

        if (!isset($record['totalscore'])) {
            $record['totalscore'] = 0;
        }

        if (!isset($record['mailnow'])) {
            $record['mailnow'] = 0;
        }

        $record = (object) $record;

        // Add the post.
        $record->id = $DB->insert_record('forumlv_posts', $record);

        // Update the last post.
        forumlv_discussion_update_last_post($record->discussion);

        return $record;
    }

    public function create_content($instance, $record = array()) {
        global $USER, $DB;
        $record = (array)$record + array(
            'forumlv' => $instance->id,
            'userid' => $USER->id,
            'course' => $instance->course
        );
        if (empty($record['discussion']) && empty($record['parent'])) {
            // Create discussion.
            $discussion = $this->create_discussion($record);
            $post = $DB->get_record('forumlv_posts', array('id' => $discussion->firstpost));
        } else {
            // Create post.
            if (empty($record['parent'])) {
                $record['parent'] = $DB->get_field('forumlv_discussions', 'firstpost', array('id' => $record['discussion']), MUST_EXIST);
            } else if (empty($record['discussion'])) {
                $record['discussion'] = $DB->get_field('forumlv_posts', 'discussion', array('id' => $record['parent']), MUST_EXIST);
            }
            $post = $this->create_post($record);
        }
        return $post;
    }
}

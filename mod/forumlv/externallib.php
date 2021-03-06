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
 * External forumlv API
 *
 * @package    mod_forumlv
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class mod_forumlv_external extends external_api {

    /**
     * Describes the parameters for get_forumlv.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_forumlvs_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'course ID',
                        VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Course IDs', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns a list of forumlvs in a provided list of courses,
     * if no list is provided all forumlvs that the user can view
     * will be returned.
     *
     * @param array $courseids the course ids
     * @return array the forumlv details
     * @since Moodle 2.5
     */
    public static function get_forumlvs_by_courses($courseids = array()) {
        global $CFG;

        require_once($CFG->dirroot . "/mod/forumlv/lib.php");

        $params = self::validate_parameters(self::get_forumlvs_by_courses_parameters(), array('courseids' => $courseids));

        $courses = array();
        if (empty($params['courseids'])) {
            $courses = enrol_get_my_courses();
            $params['courseids'] = array_keys($courses);
        }

        // Array to store the forumlvs to return.
        $arrforumlvs = array();
        $warnings = array();

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $courses);

            // Get the forumlvs in this course. This function checks users visibility permissions.
            $forumlvs = get_all_instances_in_courses("forumlv", $courses);
            foreach ($forumlvs as $forumlv) {

                $course = $courses[$forumlv->course];
                $cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id);
                $context = context_module::instance($cm->id);

                // Skip forumlvs we are not allowed to see discussions.
                if (!has_capability('mod/forumlv:viewdiscussion', $context)) {
                    continue;
                }

                $forumlv->name = external_format_string($forumlv->name, $context->id);
                // Format the intro before being returning using the format setting.
                list($forumlv->intro, $forumlv->introformat) = external_format_text($forumlv->intro, $forumlv->introformat,
                                                                                $context->id, 'mod_forumlv', 'intro', 0);
                // Discussions count. This function does static request cache.
                $forumlv->numdiscussions = forumlv_count_discussions($forumlv, $cm, $course);
                $forumlv->cmid = $forumlv->coursemodule;
                $forumlv->cancreatediscussions = forumlv_user_can_post_discussion($forumlv, null, -1, $cm, $context);

                // Add the forumlv to the array to return.
                $arrforumlvs[$forumlv->id] = $forumlv;
            }
        }

        return $arrforumlvs;
    }

    /**
     * Describes the get_forumlv return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
     public static function get_forumlvs_by_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forumlv id'),
                    'course' => new external_value(PARAM_INT, 'Course id'),
                    'type' => new external_value(PARAM_TEXT, 'The forumlv type'),
                    'name' => new external_value(PARAM_RAW, 'Forumlv name'),
                    'intro' => new external_value(PARAM_RAW, 'The forumlv intro'),
                    'introformat' => new external_format_value('intro'),
                    'assessed' => new external_value(PARAM_INT, 'Aggregate type'),
                    'assesstimestart' => new external_value(PARAM_INT, 'Assess start time'),
                    'assesstimefinish' => new external_value(PARAM_INT, 'Assess finish time'),
                    'scale' => new external_value(PARAM_INT, 'Scale'),
                    'maxbytes' => new external_value(PARAM_INT, 'Maximum attachment size'),
                    'maxattachments' => new external_value(PARAM_INT, 'Maximum number of attachments'),
                    'forcesubscribe' => new external_value(PARAM_INT, 'Force users to subscribe'),
                    'trackingtype' => new external_value(PARAM_INT, 'Subscription mode'),
                    'rsstype' => new external_value(PARAM_INT, 'RSS feed for this activity'),
                    'rssarticles' => new external_value(PARAM_INT, 'Number of RSS recent articles'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'warnafter' => new external_value(PARAM_INT, 'Post threshold for warning'),
                    'blockafter' => new external_value(PARAM_INT, 'Post threshold for blocking'),
                    'blockperiod' => new external_value(PARAM_INT, 'Time period for blocking'),
                    'completiondiscussions' => new external_value(PARAM_INT, 'Student must create discussions'),
                    'completionreplies' => new external_value(PARAM_INT, 'Student must post replies'),
                    'completionposts' => new external_value(PARAM_INT, 'Student must post discussions or replies'),
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'numdiscussions' => new external_value(PARAM_INT, 'Number of discussions in the forumlv', VALUE_OPTIONAL),
                    'cancreatediscussions' => new external_value(PARAM_BOOL, 'If the user can create discussions', VALUE_OPTIONAL),
                ), 'forumlv'
            )
        );
    }

    /**
     * Describes the parameters for get_forumlv_discussion_posts.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.7
     */
    public static function get_forumlv_discussion_posts_parameters() {
        return new external_function_parameters (
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion ID', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, created or modified', VALUE_DEFAULT, 'created'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC')
            )
        );
    }

    /**
     * Returns a list of forumlv posts for a discussion
     *
     * @param int $discussionid the post ids
     * @param string $sortby sort by this element (id, created or modified)
     * @param string $sortdirection sort direction: ASC or DESC
     *
     * @return array the forumlv post details
     * @since Moodle 2.7
     */
    public static function get_forumlv_discussion_posts($discussionid, $sortby = "created", $sortdirection = "DESC") {
        global $CFG, $DB, $USER, $PAGE;

        $posts = array();
        $warnings = array();

        // Validate the parameter.
        $params = self::validate_parameters(self::get_forumlv_discussion_posts_parameters(),
            array(
                'discussionid' => $discussionid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection));

        // Compact/extract functions are not recommended.
        $discussionid   = $params['discussionid'];
        $sortby         = $params['sortby'];
        $sortdirection  = $params['sortdirection'];

        $sortallowedvalues = array('id', 'created', 'modified');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $discussion = $DB->get_record('forumlv_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $forumlv = $DB->get_record('forumlv', array('id' => $discussion->forumlv), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $forumlv->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // This require must be here, see mod/forumlv/discuss.php.
        require_once($CFG->dirroot . "/mod/forumlv/lib.php");

        // Check they have the view forumlv capability.
        require_capability('mod/forumlv:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forumlv');

        if (! $post = forumlv_get_post_full($discussion->firstpost)) {
            throw new moodle_exception('notexists', 'forumlv');
        }

        // This function check groups, qanda, timed discussions, etc.
        if (!forumlv_user_can_see_post($forumlv, $discussion, $post, null, $cm)) {
            throw new moodle_exception('noviewdiscussionspermission', 'forumlv');
        }

        $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

        // We will add this field in the response.
        $canreply = forumlv_user_can_post($forumlv, $discussion, $USER, $cm, $course, $modcontext);

        $forumlvtracked = forumlv_tp_is_tracked($forumlv);

        $sort = 'p.' . $sortby . ' ' . $sortdirection;
        $allposts = forumlv_get_all_discussion_posts($discussion->id, $sort, $forumlvtracked);

        foreach ($allposts as $post) {

            if (!forumlv_user_can_see_post($forumlv, $discussion, $post, null, $cm)) {
                $warning = array();
                $warning['item'] = 'post';
                $warning['itemid'] = $post->id;
                $warning['warningcode'] = '1';
                $warning['message'] = 'You can\'t see this post';
                $warnings[] = $warning;
                continue;
            }

            // Function forumlv_get_all_discussion_posts adds postread field.
            // Note that the value returned can be a boolean or an integer. The WS expects a boolean.
            if (empty($post->postread)) {
                $post->postread = false;
            } else {
                $post->postread = true;
            }

            $post->canreply = $canreply;
            if (!empty($post->children)) {
                $post->children = array_keys($post->children);
            } else {
                $post->children = array();
            }

            $user = new stdclass();
            $user->id = $post->userid;
            $user = username_load_fields_from_object($user, $post, null, array('picture', 'imagealt', 'email'));
            $post->userfullname = fullname($user, $canviewfullname);

            $userpicture = new user_picture($user);
            $userpicture->size = 1; // Size f1.
            $post->userpictureurl = $userpicture->get_url($PAGE)->out(false);

            $post->subject = external_format_string($post->subject, $modcontext->id);
            // Rewrite embedded images URLs.
            list($post->message, $post->messageformat) =
                external_format_text($post->message, $post->messageformat, $modcontext->id, 'mod_forumlv', 'post', $post->id);

            // List attachments.
            if (!empty($post->attachment)) {
                $post->attachments = array();

                $fs = get_file_storage();
                if ($files = $fs->get_area_files($modcontext->id, 'mod_forumlv', 'attachment', $post->id, "filename", false)) {
                    foreach ($files as $file) {
                        $filename = $file->get_filename();
                        $fileurl = moodle_url::make_webservice_pluginfile_url(
                                        $modcontext->id, 'mod_forumlv', 'attachment', $post->id, '/', $filename);

                        $post->attachments[] = array(
                            'filename' => $filename,
                            'mimetype' => $file->get_mimetype(),
                            'fileurl'  => $fileurl->out(false)
                        );
                    }
                }
            }

            $posts[] = $post;
        }

        $result = array();
        $result['posts'] = $posts;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_forumlv_discussion_posts return value.
     *
     * @return external_single_structure
     * @since Moodle 2.7
     */
    public static function get_forumlv_discussion_posts_returns() {
        return new external_single_structure(
            array(
                'posts' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Post id'),
                                'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                                'parent' => new external_value(PARAM_INT, 'Parent id'),
                                'userid' => new external_value(PARAM_INT, 'User id'),
                                'created' => new external_value(PARAM_INT, 'Creation time'),
                                'modified' => new external_value(PARAM_INT, 'Time modified'),
                                'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                                'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                                'message' => new external_value(PARAM_RAW, 'The post message'),
                                'messageformat' => new external_format_value('message'),
                                'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                                'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                                'attachments' => new external_multiple_structure(
                                    new external_single_structure(
                                        array (
                                            'filename' => new external_value(PARAM_FILE, 'file name'),
                                            'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                                            'fileurl'  => new external_value(PARAM_URL, 'file download url')
                                        )
                                    ), 'attachments', VALUE_OPTIONAL
                                ),
                                'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                                'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                                'children' => new external_multiple_structure(new external_value(PARAM_INT, 'children post id')),
                                'canreply' => new external_value(PARAM_BOOL, 'The user can reply to posts?'),
                                'postread' => new external_value(PARAM_BOOL, 'The post was read'),
                                'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                                'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.', VALUE_OPTIONAL)
                            ), 'post'
                        )
                    ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_forumlv_discussions_paginated.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.8
     */
    public static function get_forumlv_discussions_paginated_parameters() {
        return new external_function_parameters (
            array(
                'forumlvid' => new external_value(PARAM_INT, 'forumlv instance id', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, timemodified, timestart or timeend', VALUE_DEFAULT, 'timemodified'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC'),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'perpage' => new external_value(PARAM_INT, 'items per page', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns a list of forumlv discussions optionally sorted and paginated.
     *
     * @param int $forumlvid the forumlv instance id
     * @param string $sortby sort by this element (id, timemodified, timestart or timeend)
     * @param string $sortdirection sort direction: ASC or DESC
     * @param int $page page number
     * @param int $perpage items per page
     *
     * @return array the forumlv discussion details including warnings
     * @since Moodle 2.8
     */
    public static function get_forumlv_discussions_paginated($forumlvid, $sortby = 'timemodified', $sortdirection = 'DESC',
                                                    $page = -1, $perpage = 0) {
        global $CFG, $DB, $USER, $PAGE;

        require_once($CFG->dirroot . "/mod/forumlv/lib.php");

        $warnings = array();
        $discussions = array();

        $params = self::validate_parameters(self::get_forumlv_discussions_paginated_parameters(),
            array(
                'forumlvid' => $forumlvid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection,
                'page' => $page,
                'perpage' => $perpage
            )
        );

        // Compact/extract functions are not recommended.
        $forumlvid        = $params['forumlvid'];
        $sortby         = $params['sortby'];
        $sortdirection  = $params['sortdirection'];
        $page           = $params['page'];
        $perpage        = $params['perpage'];

        $sortallowedvalues = array('id', 'timemodified', 'timestart', 'timeend');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $forumlv = $DB->get_record('forumlv', array('id' => $forumlvid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $forumlv->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // Check they have the view forumlv capability.
        require_capability('mod/forumlv:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forumlv');

        $sort = 'd.pinned DESC, d.' . $sortby . ' ' . $sortdirection;
        $alldiscussions = forumlv_get_discussions($cm, $sort, true, -1, -1, true, $page, $perpage, FORUMLV_POSTS_ALL_USER_GROUPS);

        if ($alldiscussions) {
            $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

            // Get the unreads array, this takes a forumlv id and returns data for all discussions.
            $unreads = array();
            if ($cantrack = forumlv_tp_can_track_forumlvs($forumlv)) {
                if ($forumlvtracked = forumlv_tp_is_tracked($forumlv)) {
                    $unreads = forumlv_get_discussions_unread($cm);
                }
            }
            // The forumlv function returns the replies for all the discussions in a given forumlv.
            $replies = forumlv_count_discussion_replies($forumlvid, $sort, -1, $page, $perpage);

            foreach ($alldiscussions as $discussion) {

                // This function checks for qanda forumlvs.
                // Note that the forumlv_get_discussions returns as id the post id, not the discussion id so we need to do this.
                $discussionrec = clone $discussion;
                $discussionrec->id = $discussion->discussion;
                if (!forumlv_user_can_see_discussion($forumlv, $discussionrec, $modcontext)) {
                    $warning = array();
                    // Function forumlv_get_discussions returns forumlv_posts ids not forumlv_discussions ones.
                    $warning['item'] = 'post';
                    $warning['itemid'] = $discussion->id;
                    $warning['warningcode'] = '1';
                    $warning['message'] = 'You can\'t see this discussion';
                    $warnings[] = $warning;
                    continue;
                }

                $discussion->numunread = 0;
                if ($cantrack && $forumlvtracked) {
                    if (isset($unreads[$discussion->discussion])) {
                        $discussion->numunread = (int) $unreads[$discussion->discussion];
                    }
                }

                $discussion->numreplies = 0;
                if (!empty($replies[$discussion->discussion])) {
                    $discussion->numreplies = (int) $replies[$discussion->discussion]->replies;
                }

                $picturefields = explode(',', user_picture::fields());

                // Load user objects from the results of the query.
                $user = new stdclass();
                $user->id = $discussion->userid;
                $user = username_load_fields_from_object($user, $discussion, null, $picturefields);
                // Preserve the id, it can be modified by username_load_fields_from_object.
                $user->id = $discussion->userid;
                $discussion->userfullname = fullname($user, $canviewfullname);

                $userpicture = new user_picture($user);
                $userpicture->size = 1; // Size f1.
                $discussion->userpictureurl = $userpicture->get_url($PAGE)->out(false);

                $usermodified = new stdclass();
                $usermodified->id = $discussion->usermodified;
                $usermodified = username_load_fields_from_object($usermodified, $discussion, 'um', $picturefields);
                // Preserve the id (it can be overwritten due to the prefixed $picturefields).
                $usermodified->id = $discussion->usermodified;
                $discussion->usermodifiedfullname = fullname($usermodified, $canviewfullname);

                $userpicture = new user_picture($usermodified);
                $userpicture->size = 1; // Size f1.
                $discussion->usermodifiedpictureurl = $userpicture->get_url($PAGE)->out(false);

                $discussion->name = external_format_string($discussion->name, $modcontext->id);
                $discussion->subject = external_format_string($discussion->subject, $modcontext->id);
                // Rewrite embedded images URLs.
                list($discussion->message, $discussion->messageformat) =
                    external_format_text($discussion->message, $discussion->messageformat,
                                            $modcontext->id, 'mod_forumlv', 'post', $discussion->id);

                // List attachments.
                if (!empty($discussion->attachment)) {
                    $discussion->attachments = array();

                    $fs = get_file_storage();
                    if ($files = $fs->get_area_files($modcontext->id, 'mod_forumlv', 'attachment',
                                                        $discussion->id, "filename", false)) {
                        foreach ($files as $file) {
                            $filename = $file->get_filename();

                            $discussion->attachments[] = array(
                                'filename' => $filename,
                                'mimetype' => $file->get_mimetype(),
                                'fileurl'  => file_encode_url($CFG->wwwroot.'/webservice/pluginfile.php',
                                                '/'.$modcontext->id.'/mod_forumlv/attachment/'.$discussion->id.'/'.$filename)
                            );
                        }
                    }
                }

                $discussions[] = $discussion;
            }
        }

        $result = array();
        $result['discussions'] = $discussions;
        $result['warnings'] = $warnings;
        return $result;

    }

    /**
     * Describes the get_forumlv_discussions_paginated return value.
     *
     * @return external_single_structure
     * @since Moodle 2.8
     */
    public static function get_forumlv_discussions_paginated_returns() {
        return new external_single_structure(
            array(
                'discussions' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Post id'),
                                'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                                'groupid' => new external_value(PARAM_INT, 'Group id'),
                                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                                'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                                'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                                'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                                'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                                'parent' => new external_value(PARAM_INT, 'Parent id'),
                                'userid' => new external_value(PARAM_INT, 'User who started the discussion id'),
                                'created' => new external_value(PARAM_INT, 'Creation time'),
                                'modified' => new external_value(PARAM_INT, 'Time modified'),
                                'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                                'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                                'message' => new external_value(PARAM_RAW, 'The post message'),
                                'messageformat' => new external_format_value('message'),
                                'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                                'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                                'attachments' => new external_multiple_structure(
                                    new external_single_structure(
                                        array (
                                            'filename' => new external_value(PARAM_FILE, 'file name'),
                                            'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                                            'fileurl'  => new external_value(PARAM_URL, 'file download url')
                                        )
                                    ), 'attachments', VALUE_OPTIONAL
                                ),
                                'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                                'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                                'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                                'usermodifiedfullname' => new external_value(PARAM_TEXT, 'Post modifier full name'),
                                'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.'),
                                'usermodifiedpictureurl' => new external_value(PARAM_URL, 'Post modifier picture.'),
                                'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                                'numunread' => new external_value(PARAM_INT, 'The number of unread discussions.'),
                                'pinned' => new external_value(PARAM_BOOL, 'Is the discussion pinned')
                            ), 'post'
                        )
                    ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function view_forumlv_parameters() {
        return new external_function_parameters(
            array(
                'forumlvid' => new external_value(PARAM_INT, 'forumlv instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $forumlvid the forumlv instance id
     * @return array of warnings and status result
     * @since Moodle 2.9
     * @throws moodle_exception
     */
    public static function view_forumlv($forumlvid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/forumlv/lib.php");

        $params = self::validate_parameters(self::view_forumlv_parameters(),
                                            array(
                                                'forumlvid' => $forumlvid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $forumlv = $DB->get_record('forumlv', array('id' => $params['forumlvid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forumlv, 'forumlv');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/forumlv:viewdiscussion', $context, null, true, 'noviewdiscussionspermission', 'forumlv');

        // Call the forumlv/lib API.
        forumlv_view($forumlv, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function view_forumlv_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function view_forumlv_discussion_parameters() {
        return new external_function_parameters(
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion id')
            )
        );
    }

    /**
     * Trigger the discussion viewed event.
     *
     * @param int $discussionid the discussion id
     * @return array of warnings and status result
     * @since Moodle 2.9
     * @throws moodle_exception
     */
    public static function view_forumlv_discussion($discussionid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/forumlv/lib.php");

        $params = self::validate_parameters(self::view_forumlv_discussion_parameters(),
                                            array(
                                                'discussionid' => $discussionid
                                            ));
        $warnings = array();

        $discussion = $DB->get_record('forumlv_discussions', array('id' => $params['discussionid']), '*', MUST_EXIST);
        $forumlv = $DB->get_record('forumlv', array('id' => $discussion->forumlv), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forumlv, 'forumlv');

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        require_capability('mod/forumlv:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forumlv');

        // Call the forumlv/lib API.
        forumlv_discussion_view($modcontext, $forumlv, $discussion);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function view_forumlv_discussion_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_discussion_post_parameters() {
        return new external_function_parameters(
            array(
                'postid' => new external_value(PARAM_INT, 'the post id we are going to reply to
                                                (can be the initial discussion post'),
                'subject' => new external_value(PARAM_TEXT, 'new post subject'),
                'message' => new external_value(PARAM_RAW, 'new post message (only html format allowed)'),
                'options' => new external_multiple_structure (
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUM,
                                        'The allowed keys (value format) are:
                                        discussionsubscribe (bool); subscribe to the discussion?, default to true
                                        inlineattachmentsid              (int); the draft file area id for inline attachments
                                        attachmentsid       (int); the draft file area id for attachments
                            '),
                            'value' => new external_value(PARAM_RAW, 'the value of the option,
                                                            this param is validated in the external function.'
                        )
                    )
                ), 'Options', VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Create new posts into an existing discussion.
     *
     * @param int $postid the post id we are going to reply to
     * @param string $subject new post subject
     * @param string $message new post message (only html format allowed)
     * @param array $options optional settings
     * @return array of warnings and the new post id
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function add_discussion_post($postid, $subject, $message, $options = array()) {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . "/mod/forumlv/lib.php");

        $params = self::validate_parameters(self::add_discussion_post_parameters(),
                                            array(
                                                'postid' => $postid,
                                                'subject' => $subject,
                                                'message' => $message,
                                                'options' => $options
                                            ));
        // Validate options.
        $options = array(
            'discussionsubscribe' => true,
            'inlineattachmentsid' => 0,
            'attachmentsid' => null
        );
        foreach ($params['options'] as $option) {
            $name = trim($option['name']);
            switch ($name) {
                case 'discussionsubscribe':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                case 'inlineattachmentsid':
                    $value = clean_param($option['value'], PARAM_INT);
                    break;
                case 'attachmentsid':
                    $value = clean_param($option['value'], PARAM_INT);
                    break;
                default:
                    throw new moodle_exception('errorinvalidparam', 'webservice', '', $name);
            }
            $options[$name] = $value;
        }

        $warnings = array();

        if (!$parent = forumlv_get_post_full($params['postid'])) {
            throw new moodle_exception('invalidparentpostid', 'forumlv');
        }

        if (!$discussion = $DB->get_record("forumlv_discussions", array("id" => $parent->discussion))) {
            throw new moodle_exception('notpartofdiscussion', 'forumlv');
        }

        // Request and permission validation.
        $forumlv = $DB->get_record('forumlv', array('id' => $discussion->forumlv), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forumlv, 'forumlv');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        if (!forumlv_user_can_post($forumlv, $discussion, $USER, $cm, $course, $context)) {
            throw new moodle_exception('nopostforumlv', 'forumlv');
        }

        $thresholdwarning = forumlv_check_throttling($forumlv, $cm);
        forumlv_check_blocking_threshold($thresholdwarning);

        // Create the post.
        $post = new stdClass();
        $post->discussion = $discussion->id;
        $post->parent = $parent->id;
        $post->subject = $params['subject'];
        $post->message = $params['message'];
        $post->messageformat = FORMAT_HTML;   // Force formatting for now.
        $post->messagetrust = trusttext_trusted($context);
        $post->itemid = $options['inlineattachmentsid'];
        $post->attachments   = $options['attachmentsid'];
        $fakemform = $post->attachments;
        if ($postid = forumlv_add_new_post($post, $fakemform)) {

            $post->id = $postid;

            // Trigger events and completion.
            $params = array(
                'context' => $context,
                'objectid' => $post->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'forumlvid' => $forumlv->id,
                    'forumlvtype' => $forumlv->type,
                )
            );
            $event = \mod_forumlv\event\post_created::create($params);
            $event->add_record_snapshot('forumlv_posts', $post);
            $event->add_record_snapshot('forumlv_discussions', $discussion);
            $event->trigger();

            // Update completion state.
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                    ($forumlv->completionreplies || $forumlv->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            $settings = new stdClass();
            $settings->discussionsubscribe = $options['discussionsubscribe'];
            forumlv_post_subscription($settings, $forumlv, $discussion);
        } else {
            throw new moodle_exception('couldnotadd', 'forumlv');
        }

        $result = array();
        $result['postid'] = $postid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function add_discussion_post_returns() {
        return new external_single_structure(
            array(
                'postid' => new external_value(PARAM_INT, 'new post id'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_discussion_parameters() {
        return new external_function_parameters(
            array(
                'forumlvid' => new external_value(PARAM_INT, 'Forumlv instance ID'),
                'subject' => new external_value(PARAM_TEXT, 'New Discussion subject'),
                'message' => new external_value(PARAM_RAW, 'New Discussion message (only html format allowed)'),
                'groupid' => new external_value(PARAM_INT, 'The group, default to -1', VALUE_DEFAULT, -1),
                'options' => new external_multiple_structure (
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUM,
                                        'The allowed keys (value format) are:
                                        discussionsubscribe (bool); subscribe to the discussion?, default to true
                                        discussionpinned    (bool); is the discussion pinned, default to false
                                        inlineattachmentsid              (int); the draft file area id for inline attachments
                                        attachmentsid       (int); the draft file area id for attachments
                            '),
                            'value' => new external_value(PARAM_RAW, 'The value of the option,
                                                            This param is validated in the external function.'
                        )
                    )
                ), 'Options', VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Add a new discussion into an existing forumlv.
     *
     * @param int $forumlvid the forumlv instance id
     * @param string $subject new discussion subject
     * @param string $message new discussion message (only html format allowed)
     * @param int $groupid the user course group
     * @param array $options optional settings
     * @return array of warnings and the new discussion id
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function add_discussion($forumlvid, $subject, $message, $groupid = -1, $options = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/forumlv/lib.php");

        $params = self::validate_parameters(self::add_discussion_parameters(),
                                            array(
                                                'forumlvid' => $forumlvid,
                                                'subject' => $subject,
                                                'message' => $message,
                                                'groupid' => $groupid,
                                                'options' => $options
                                            ));
        // Validate options.
        $options = array(
            'discussionsubscribe' => true,
            'discussionpinned' => false,
            'inlineattachmentsid' => 0,
            'attachmentsid' => null
        );
        foreach ($params['options'] as $option) {
            $name = trim($option['name']);
            switch ($name) {
                case 'discussionsubscribe':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                case 'discussionpinned':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                case 'inlineattachmentsid':
                    $value = clean_param($option['value'], PARAM_INT);
                    break;
                case 'attachmentsid':
                    $value = clean_param($option['value'], PARAM_INT);
                    break;
                default:
                    throw new moodle_exception('errorinvalidparam', 'webservice', '', $name);
            }
            $options[$name] = $value;
        }

        $warnings = array();

        // Request and permission validation.
        $forumlv = $DB->get_record('forumlv', array('id' => $params['forumlvid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forumlv, 'forumlv');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Normalize group.
        if (!groups_get_activity_groupmode($cm)) {
            // Groups not supported, force to -1.
            $groupid = -1;
        } else {
            // Check if we receive the default or and empty value for groupid,
            // in this case, get the group for the user in the activity.
            if ($groupid === -1 or empty($params['groupid'])) {
                $groupid = groups_get_activity_group($cm);
            } else {
                // Here we rely in the group passed, forumlv_user_can_post_discussion will validate the group.
                $groupid = $params['groupid'];
            }
        }

        if (!forumlv_user_can_post_discussion($forumlv, $groupid, -1, $cm, $context)) {
            throw new moodle_exception('cannotcreatediscussion', 'forumlv');
        }

        $thresholdwarning = forumlv_check_throttling($forumlv, $cm);
        forumlv_check_blocking_threshold($thresholdwarning);

        // Create the discussion.
        $discussion = new stdClass();
        $discussion->course = $course->id;
        $discussion->forumlv = $forumlv->id;
        $discussion->message = $params['message'];
        $discussion->messageformat = FORMAT_HTML;   // Force formatting for now.
        $discussion->messagetrust = trusttext_trusted($context);
        $discussion->itemid = $options['inlineattachmentsid'];
        $discussion->groupid = $groupid;
        $discussion->mailnow = 0;
        $discussion->subject = $params['subject'];
        $discussion->name = $discussion->subject;
        $discussion->timestart = 0;
        $discussion->timeend = 0;
        $discussion->attachments = $options['attachmentsid'];

        if (has_capability('mod/forumlv:pindiscussions', $context) && $options['discussionpinned']) {
            $discussion->pinned = FORUMLV_DISCUSSION_PINNED;
        } else {
            $discussion->pinned = FORUMLV_DISCUSSION_UNPINNED;
        }
        $fakemform = $options['attachmentsid'];
        if ($discussionid = forumlv_add_discussion($discussion, $fakemform)) {

            $discussion->id = $discussionid;

            // Trigger events and completion.

            $params = array(
                'context' => $context,
                'objectid' => $discussion->id,
                'other' => array(
                    'forumlvid' => $forumlv->id,
                )
            );
            $event = \mod_forumlv\event\discussion_created::create($params);
            $event->add_record_snapshot('forumlv_discussions', $discussion);
            $event->trigger();

            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                    ($forumlv->completiondiscussions || $forumlv->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            $settings = new stdClass();
            $settings->discussionsubscribe = $options['discussionsubscribe'];
            forumlv_post_subscription($settings, $forumlv, $discussion);
        } else {
            throw new moodle_exception('couldnotadd', 'forumlv');
        }

        $result = array();
        $result['discussionid'] = $discussionid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function add_discussion_returns() {
        return new external_single_structure(
            array(
                'discussionid' => new external_value(PARAM_INT, 'New Discussion ID'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function can_add_discussion_parameters() {
        return new external_function_parameters(
            array(
                'forumlvid' => new external_value(PARAM_INT, 'Forumlv instance ID'),
                'groupid' => new external_value(PARAM_INT, 'The group to check, default to active group.
                                                Use -1 to check if the user can post in all the groups.', VALUE_DEFAULT, null)
            )
        );
    }

    /**
     * Check if the current user can add discussions in the given forumlv (and optionally for the given group).
     *
     * @param int $forumlvid the forumlv instance id
     * @param int $groupid the group to check, default to active group. Use -1 to check if the user can post in all the groups.
     * @return array of warnings and the status (true if the user can add discussions)
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function can_add_discussion($forumlvid, $groupid = null) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/forumlv/lib.php");

        $params = self::validate_parameters(self::can_add_discussion_parameters(),
                                            array(
                                                'forumlvid' => $forumlvid,
                                                'groupid' => $groupid,
                                            ));
        $warnings = array();

        // Request and permission validation.
        $forumlv = $DB->get_record('forumlv', array('id' => $params['forumlvid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forumlv, 'forumlv');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $status = forumlv_user_can_post_discussion($forumlv, $params['groupid'], -1, $cm, $context);

        $result = array();
        $result['status'] = $status;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function can_add_discussion_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'True if the user can add discussions, false otherwise.'),
                'warnings' => new external_warnings()
            )
        );
    }

}

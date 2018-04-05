<?php

/// This page allows to edit entries categories for a particular instance of glossarylv

require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);                       // Course Module ID, or
$usedynalink = optional_param('usedynalink', 0, PARAM_INT);  // category ID
$confirm     = optional_param('confirm', 0, PARAM_INT);      // confirm the action
$name        = optional_param('name', '', PARAM_CLEAN);  // confirm the name

$action = optional_param('action', '', PARAM_ALPHA ); // what to do
$hook   = optional_param('hook', '', PARAM_ALPHANUM); // category ID
$mode   = optional_param('mode', '', PARAM_ALPHA);   // cat

$action = strtolower($action);

$url = new moodle_url('/mod/glossarylv/editcategories.php', array('id'=>$id));
if ($usedynalink !== 0) {
    $url->param('usedynalink', $usedynalink);
}
if ($confirm !== 0) {
    $url->param('confirm', $confirm);
}
if ($name !== 'name') {
    $url->param('name', $name);
}
if ($action !== 'action') {
    $url->param('action', $action);
}
if ($hook !== 'hook') {
    $url->param('hook', $hook);
}
if ($mode !== 'mode') {
    $url->param('mode', $mode);
}

$PAGE->set_url($url);

if (! $cm = get_coursemodule_from_id('glossarylv', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
    print_error('coursemisconf');
}

if (! $glossarylv = $DB->get_record("glossarylv", array("id"=>$cm->instance))) {
    print_error('invalidcoursemodule');
}

if ($hook > 0) {
    if ($category = $DB->get_record("glossarylv_categories", array("id"=>$hook))) {
        //Check it belongs to the same glossarylv
        if ($category->glossarylvid != $glossarylv->id) {
            print_error('invalidid', 'glossarylv');
        }
    } else {
        print_error('invalidcategoryid');
    }
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/glossarylv:managecategories', $context);

$strglossaries   = get_string("modulenameplural", "glossarylv");
$strglossarylv     = get_string("modulename", "glossarylv");

$PAGE->navbar->add(get_string("categories","glossarylv"),
        new moodle_url('/mod/glossarylv/editcategories.php', array('id' => $cm->id,'mode' => 'cat')));
if (!empty($action)) {
    $navaction = get_string($action). " " . core_text::strtolower(get_string("category","glossarylv"));
    $PAGE->navbar->add($navaction);
}
$PAGE->set_title($glossarylv->name);
$PAGE->set_heading($course->fullname);

// Prepare format_string/text options
$fmtoptions = array(
    'context' => $context);

if (right_to_left()) { // RTL table alignment support
    $rightalignment = 'left';
    $leftalignment = 'right';
} else {
    $rightalignment = 'right';
    $leftalignment = 'left';

}

if ( $hook >0 ) {

    if ( $action == "edit" ) {
        if ( $confirm ) {
            require_sesskey();
            $action = "";
            $cat = new stdClass();
            $cat->id = $hook;
            $cat->name = $name;
            $cat->usedynalink = $usedynalink;

            $DB->update_record("glossarylv_categories", $cat);
            $event = \mod_glossarylv\event\category_updated::create(array(
                'context' => $context,
                'objectid' => $hook
            ));
            $cat->glossarylvid = $glossarylv->id;
            $event->add_record_snapshot('glossarylv_categories', $cat);
            $event->add_record_snapshot('glossarylv', $glossarylv);
            $event->trigger();

            // Reset caches.
            \mod_glossarylv\local\concept_cache::reset_glossarylv($glossarylv);

        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($glossarylv->name), 2);
            echo $OUTPUT->heading(format_string(get_string("edit"). " " . get_string("category","glossarylv")), 3);

            $name = $category->name;
            $usedynalink = $category->usedynalink;
            require "editcategories.html";
            echo $OUTPUT->footer();
            die;
        }

    } elseif ( $action == "delete" ) {
        if ( $confirm ) {
            require_sesskey();
            $DB->delete_records("glossarylv_entries_categ", array("categoryid"=>$hook));
            $DB->delete_records("glossarylv_categories", array("id"=>$hook));

            $event = \mod_glossarylv\event\category_deleted::create(array(
                'context' => $context,
                'objectid' => $hook
            ));
            $event->add_record_snapshot('glossarylv_categories', $category);
            $event->add_record_snapshot('glossarylv', $glossarylv);
            $event->trigger();

            // Reset caches.
            \mod_glossarylv\local\concept_cache::reset_glossarylv($glossarylv);

            redirect("editcategories.php?id=$cm->id", get_string("categorydeleted", "glossarylv"), 2);
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($glossarylv->name), 2);
            echo $OUTPUT->heading(format_string(get_string("delete"). " " . get_string("category","glossarylv")), 3);

            echo $OUTPUT->box_start('generalbox boxaligncenter errorboxcontent boxwidthnarrow');
            echo "<div class=\"boxaligncenter deletecatconfirm\">".format_string($category->name, true, $fmtoptions)."<br/>";

            $num_entries = $DB->count_records("glossarylv_entries_categ", array("categoryid"=>$category->id));
            if ( $num_entries ) {
                print_string("deletingnoneemptycategory","glossarylv");
            }
            echo "<p>";
            print_string("areyousuredelete","glossarylv");
            echo "</p>";
?>

                <table border="0" width="100" class="confirmbuttons">
                    <tr>
                        <td align="$rightalignment" style="width:50%">
                        <form id="form" method="post" action="editcategories.php">
                        <div>
                        <input type="hidden" name="sesskey"     value="<?php echo sesskey(); ?>" />
                        <input type="hidden" name="id"          value="<?php p($cm->id) ?>" />
                        <input type="hidden" name="action"      value="delete" />
                        <input type="hidden" name="confirm"     value="1" />
                        <input type="hidden" name="mode"         value="<?php echo $mode ?>" />
                        <input type="hidden" name="hook"         value="<?php echo $hook ?>" />
                        <input type="submit" value=" <?php print_string("yes")?> " />
                        </div>
                        </form>
                        </td>
                        <td align="$leftalignment" style="width:50%">

<?php
            unset($options);
            $options = array ("id" => $id);
            echo $OUTPUT->single_button(new moodle_url("editcategories.php", $options), get_string("no"));
            echo "</td></tr></table>";
            echo "</div>";
            echo $OUTPUT->box_end();
        }
    }

} elseif ( $action == "add" ) {
    if ( $confirm ) {
        require_sesskey();
        $dupcategory = $DB->get_records_sql("SELECT * FROM {glossarylv_categories} WHERE ".$DB->sql_like('name','?', false)." AND glossarylvid=?", array($name, $glossarylv->id));
        if ( $dupcategory ) {
            redirect("editcategories.php?id=$cm->id&amp;action=add&amp;name=$name", get_string("duplicatecategory", "glossarylv"), 2);

        } else {
            $action = "";
            $cat = new stdClass();
            $cat->name = $name;
            $cat->usedynalink = $usedynalink;
            $cat->glossarylvid = $glossarylv->id;

            $cat->id = $DB->insert_record("glossarylv_categories", $cat);
            $event = \mod_glossarylv\event\category_created::create(array(
                'context' => $context,
                'objectid' => $cat->id
            ));
            $event->add_record_snapshot('glossarylv_categories', $cat);
            $event->add_record_snapshot('glossarylv', $glossarylv);
            $event->trigger();

            // Reset caches.
            \mod_glossarylv\local\concept_cache::reset_glossarylv($glossarylv);
        }
    } else {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($glossarylv->name), 2);
        echo "<h3 class=\"main\">" . get_string("add"). " " . get_string("category","glossarylv"). "</h3>";
        $name="";
        require "editcategories.html";
    }
}

if ( $action ) {
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($glossarylv->name), 2);

?>

<form method="post" action="editcategories.php">
<table width="40%" class="boxaligncenter generalbox" cellpadding="5">
        <tr>
          <th style="width:90%" align="center">
          <?php p(get_string("categories","glossarylv")) ?></th>
          <th style="width:10%" align="center">
          <?php p(get_string("action")) ?></th>
        </tr>
        <tr><td style="width:100%" colspan="2">



<?php
    $categories = $DB->get_records("glossarylv_categories", array("glossarylvid"=>$glossarylv->id), "name ASC");

    if ( $categories ) {
        echo '<table width="100%">';
        foreach ($categories as $category) {
            $num_entries = $DB->count_records("glossarylv_entries_categ", array("categoryid"=>$category->id));
?>

             <tr>
               <td style="width:80%" align="$leftalignment">
               <?php
                    echo "<span class=\"bold\">".format_string($category->name, true, $fmtoptions)."</span> <span>($num_entries " . get_string("entries","glossarylv") . ")</span>";
               ?>
               </td>
               <td style="width:19%" align="center" class="action">
               <?php
                echo "<a href=\"editcategories.php?id=$cm->id&amp;action=delete&amp;mode=cat&amp;hook=$category->id\">" .
                     $OUTPUT->pix_icon('t/delete', get_string('delete')). "</a> ";
                echo "<a href=\"editcategories.php?id=$cm->id&amp;action=edit&amp;mode=cat&amp;hook=$category->id\">" .
                     $OUTPUT->pix_icon('t/edit', get_string('edit')). "</a> ";
               ?>
               </td>
             </tr>

             <?php

          }
        echo '</table>';
     }
?>

        </td></tr>
        <tr>
        <td style="width:100%" colspan="2"  align="center">
            <?php

             $options['id'] = $cm->id;
             $options['action'] = "add";

             echo "<table class=\"editbuttons\" border=\"0\"><tr><td align=\"$rightalignment\">";
             echo $OUTPUT->single_button(new moodle_url("editcategories.php", $options), get_string("add") . " " . get_string("category","glossarylv"));
             echo "</td><td align=\"$leftalignment\">";
             unset($options['action']);
             $options['mode'] = 'cat';
             $options['hook'] = $hook;
             echo $OUTPUT->single_button(new moodle_url("view.php", $options), get_string("back","glossarylv"));
             echo "</td></tr>";
             echo "</table>";

            ?>
        </td>
        </tr>
        </table>


</form>

<?php
echo $OUTPUT->footer();

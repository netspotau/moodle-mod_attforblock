<?PHP  // $Id: manage.php,v 1.2.2.4 2009/02/28 19:20:14 dlnsk Exp $

/// This page prints a particular instance of attforblock
/// (Replace attforblock with the name of your module)

    require_once('../../config.php');
    require_once('locallib.php');

    $id   = required_param('id', PARAM_INT);   // Course Module ID, or
    $from = optional_param('from', "", PARAM_ACTION);

    $url = new moodle_url('/mod/attforblock/manage.php');

    if (! $cm = $DB->get_record('course_modules', array('id' => $id))) {
        error('Course Module ID was incorrect');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        error("Course is misconfigured");
    }

    if (! $attforblock = $DB->get_record('attforblock', array('id' => $cm->instance))) {
        error("Course module is incorrect");
    }

    $url->param($id);
    $url->param($from);
    $PAGE->set_url($url);
    require_login($course->id);

    if (! $user = $DB->get_record('user', array('id' => $USER->id)) ) {
        error("No such user in this course");
    }
    
    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
    }
    
    if (!has_capability('mod/attforblock:manageattendances', $context) AND
            !has_capability('mod/attforblock:takeattendances', $context) AND
            !has_capability('mod/attforblock:changeattendances', $context)) {
        redirect("view.php?id=$cm->id");
    }
    
    //if teacher is coming from block, then check for a session exists for today
	if($from === 'block') {
		$today = time(); // because we compare with database, we don't need to use usertime()
        $sql = "SELECT id, lasttaken
                  FROM {attendance_sessions}
                 WHERE $today BETWEEN sessdate AND (sessdate + duration)
                   AND courseid = ?";
        if($att = $DB->get_record_sql($sql, array($course->id))) {
            if ((!$att->lasttaken and has_capability('mod/attforblock:takeattendances', $context)) or
                ($att->lasttaken and has_capability('mod/attforblock:changeattendances', $context))) {
                redirect('attendances.php?id='.$id.'&sessionid='.$att->id);
            }
        }
	}
	
/// Print headers
    $navlinks[] = array('name' => $attforblock->name, 'link' => null, 'type' => 'activity');
    print_header("$course->shortname: ".$attforblock->name, $course->fullname,
                 $navlinks, "", "", true, $OUTPUT->update_module_button($cm->id, 'attforblock'), 
                 navmenu($course));
    
	echo $OUTPUT->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
	
	if(!$DB->count_records_select('attendance_sessions', "courseid = ? AND sessdate >= ?", array($course->id, $course->startdate))) {	// no session exists for this course
		show_tabs($cm, $context);
		echo $OUTPUT->heading(get_string('nosessionexists','attforblock'));	
		$hiddensess = $DB->count_records_select('attendance_sessions', "courseid = ? AND sessdate < ?", array($course->id, $course->startdate));
        echo '<div align="left">';
        echo $OUTPUT->help_icon('hiddensessions', 'attforblock', '');
        echo get_string('hiddensessions', 'attforblock').': '.$hiddensess.'</div>';
	} else {	//sessions generated , display them
		add_to_log($course->id, 'attendance', 'manage attendances', 'mod/attforblock/manage.php?course='.$course->id, $user->lastname.' '.$user->firstname);
		show_tabs($cm, $context);
		print_sessions_list($course);
	}
//	require_once('lib.php');
//	$t = attforblock_get_user_grades($attforblock); ////////////////////////////////////////////
	echo $OUTPUT->footer($course);
	
	
function print_sessions_list($course) {
	global $CFG, $DB, $OUTPUT, $USER, $context, $cm;
	
			//$USER->timezone = $tz;
	        $strhours = get_string('hours');
			$strmins = get_string('min');
				
			$qry = $DB->get_records_select('attendance_sessions', "courseid = ? AND sessdate >= ?", array($course->id, $course->startdate), 'sessdate asc');
			$i = 0;
			$table = new html_table();
			//$table->width = '100%';
			//$table->tablealign = 'center';
			$table->head = array('#', get_string('date'), get_string('time'), get_string('duration', 'attforblock'), get_string('description','attforblock'), get_string('actions'), get_string('select'));
			$table->align = array('', '', '', 'right', 'left', 'center', 'center');
			$table->size = array('1px', '1px', '1px', '1px', '*', '1px', '1px');

            $allowtake = has_capability('mod/attforblock:takeattendances', $context);
            $allowchange = has_capability('mod/attforblock:changeattendances', $context);
            $allowmanage = has_capability('mod/attforblock:manageattendances', $context);
			foreach($qry as $key=>$sessdata)
			{
				$i++;
				$actions = '';
//				if ($allowtake) {
					if($sessdata->lasttaken > 0)	//attendance has taken
					{
						if ($allowchange) {
                            $desc = "<a href=\"attendances.php?id=$cm->id&amp;sessionid={$sessdata->id}\">".
                                    ($sessdata->description ? $sessdata->description : get_string('nodescription', 'attforblock')).
                                    '</a>';
                        } else {
                            $desc = '<i>'.(empty($sessdata->description) ? get_string('nodescription', 'attforblock') : $sessdata->description).'</i>';
                        }
					} else {
						$desc = empty($sessdata->description) ? get_string('nodescription', 'attforblock') : $sessdata->description;
						if ($allowtake) {
                            $title = get_string('takeattendance','attforblock');
                            $actions = "<a title=\"$title\" href=\"attendances.php?id=$cm->id&amp;sessionid={$sessdata->id}\">".
                                       "<img src=\"" . $OUTPUT->pix_url('t/go') . "\" alt=\"$title\" /></a>&nbsp;";
                        }
					}
//				}
				if($allowmanage) {
					$title = get_string('editsession','attforblock');
					$actions .= "<a title=\"$title\" href=\"sessions.php?id=$cm->id&amp;sessionid={$sessdata->id}&amp;action=update\">".
								"<img src=\"" . $OUTPUT->pix_url('t/edit') . "\" alt=\"$title\" /></a>&nbsp;";
					$title = get_string('deletesession','attforblock');
					$actions .= "<a title=\"$title\" href=\"sessions.php?id=$cm->id&amp;sessionid={$sessdata->id}&amp;action=delete\">".
								"<img src=\"" . $OUTPUT->pix_url('t/delete') . "\" alt=\"$title\" /></a>&nbsp;";
				}
				
				$table->data[$sessdata->id][] = $i;
				$table->data[$sessdata->id][] = userdate($sessdata->sessdate, get_string('str_ftimedmyw', 'attforblock'), $USER->timezone);
				$table->data[$sessdata->id][] = userdate($sessdata->sessdate, get_string('str_ftimehm', 'attforblock'), $USER->timezone);
                $hours = floor($sessdata->duration / HOURSECS);
                $mins = floor(($sessdata->duration - $hours * HOURSECS) / MINSECS);
                $mins = $mins < 10 ? "0$mins" : "$mins";
				$table->data[$sessdata->id][] = $hours ? "{$hours}&nbsp;{$strhours}&nbsp;{$mins}&nbsp;{$strmins}" : "{$mins}&nbsp;{$strmins}";
				$table->data[$sessdata->id][] = $desc;
				$table->data[$sessdata->id][] = $actions;
				$table->data[$sessdata->id][] = '<input type="checkbox" name="sessid['.$sessdata->id.']" />';
				unset($desc, $actions);
			}
        echo '<div align="center"><div class="generalbox boxwidthwide">';
        echo "<form method=\"post\" action=\"sessions.php?id={$cm->id}\">"; //&amp;sessionid={$sessdata->id}
        echo '<div align="right">';
        echo $OUTPUT->help_icon('sessions', 'attforblock', get_string('help'));
        echo '</div>';
        echo html_writer::table($table);
		$hiddensess = $DB->count_records_select('attendance_sessions', "courseid = ? AND sessdate < ?", array($course->id, $course->startdate));
        echo '<table width="100%"><tr><td valign="top">';
        echo '<div align="left">';
        echo $OUTPUT->help_icon('hiddensessions', 'attforblock', '');
        echo get_string('hiddensessions', 'attforblock').': '.$hiddensess.'</div></td>';
        echo '<td><div align="right"><a href="javascript:checkall();">'.get_string('selectall').'</a> /'.
             ' <a href="javascript:checknone();">'.get_string('deselectall').'</a><br /><br />';
        echo '<strong>'.get_string('withselected', 'quiz').':</strong>&nbsp;';
        if ($allowmanage) {
            $actionlist = array('deleteselected' => get_string('delete'),
                                'changeduration' => get_string('changeduration', 'attforblock'));
            //$select = html_select::make($actionlist, 'action'); // Required
            echo html_writer::select($actionlist, 'action');
            echo '<input type="submit" name="ok" value="'.get_string('ok')."\" />\n";
        } else {
            echo get_string('youcantdo', 'attforblock'); //You can't do anything
        }
        echo '</div></td></tr></table>';
        echo '</form></div></div>';
			
}
?>

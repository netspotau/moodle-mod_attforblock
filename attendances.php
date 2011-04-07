<?PHP // $Id: attendances.php,v 1.2.2.5 2009/02/23 19:22:40 dlnsk Exp $

//  Lists all the sessions for a course

    require_once('../../config.php');    
	require_once($CFG->libdir.'/blocklib.php');
	require_once('locallib.php');
	require_once('lib.php');	
	require_once('../../enrol/locallib.php'); // to get a list of enrolled students
	
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    $id 		= required_param('id', PARAM_INT);
	$sessionid	= required_param('sessionid', PARAM_INT);
    $group    	= optional_param('group', -1, PARAM_INT);              // Group to show
	$sort 		= optional_param('sort','lastname', PARAM_ALPHA);

    if (! $cm = $DB->get_record('course_modules', array('id' => $id))) {
        error('Course Module ID was incorrect');
    }
    
    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        error('Course is misconfigured');
    }
    
    require_login($course->id);

    if (! $attforblock = $DB->get_record('attforblock', array('id' => $cm->instance))) {
        error("Course module is incorrect");
    }
    if (! $user = $DB->get_record('user', array('id' => $USER->id)) ) {
        error("No such user in this course");
    }
    
    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
    }
    
    $statlist = implode(',', array_keys( (array)get_statuses($course->id) ));
    if ($form = data_submitted()) {
    	$students = array();			// stores students ids
		$formarr = (array)$form;
		$i = 0;
		$now = time();
		foreach($formarr as $key => $value) {
			if(substr($key,0,7) == 'student' && $value !== '') {
				$students[$i] = new stdClass();
				$sid = substr($key,7);		// gets studeent id from radiobutton name
				$students[$i]->studentid = $sid;
				$students[$i]->statusid = intval($value);
				$students[$i]->statusset = $statlist;
				$students[$i]->remarks = array_key_exists('remarks'.$sid, $formarr) ? $formarr['remarks'.$sid] : '';
				$students[$i]->sessionid = $sessionid;
				$students[$i]->timetaken = $now;
				$students[$i]->takenby = $USER->id;
				$i++;
			}
		}
		$attforblockrecord = $DB->get_record('attforblock', array('course' => $course->id));

		foreach($students as $student) {
			if ($log = $DB->get_record('attendance_log', array('sessionid' => $sessionid, 'studentid' => $student->studentid))) {
				$student->id = $log->id; // this is id of log
				$DB->update_record('attendance_log', $student);
			} else {
				$DB->insert_record('attendance_log', $student);
			}
		}
		$DB->set_field('attendance_sessions', 'lasttaken', $now, array('id' => $sessionid));
		$DB->set_field('attendance_sessions', 'lasttakenby', $USER->id, array('id' => $sessionid));
		
		attforblock_update_grades($attforblockrecord);
		add_to_log($course->id, 'attendance', 'updated', 'mod/attforblock/report.php?id='.$id, $user->lastname.' '.$user->firstname);
		redirect('manage.php?id='.$id, get_string('attendancesuccess','attforblock'), 3);
    	exit();
    }
    
/// Print headers
    $navlinks[] = array('name' => $attforblock->name, 'link' => "view.php?id=$id", 'type' => 'activity');
    $navlinks[] = array('name' => get_string('update', 'attforblock'), 'link' => null, 'type' => 'activityinstance');
    print_header("$course->shortname: ".$attforblock->name.' - ' .get_string('update','attforblock'), $course->fullname,
                 $navlinks, "", "", true, "&nbsp;", navmenu($course));

//check for hack
    if (!$sessdata = $DB->get_record('attendance_sessions', array('id' => $sessionid))) {
		error("Required Information is missing", "manage.php?id=".$id);
    }
	$help = $OUTPUT->help_icon('updateattendance', 'attforblock', '');
	$update = $DB->count_records('attendance_log', array('sessionid' => $sessionid));
	
	if ($update) {
        require_capability('mod/attforblock:changeattendances', $context);
		echo $OUTPUT->heading(get_string('update','attforblock').' ' .get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname.$help);
	} else {
        require_capability('mod/attforblock:takeattendances', $context);
		echo $OUTPUT->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname.$help);
	}

    /// find out current groups mode
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm, true);
    $manager = new course_enrolment_manager($course);

    if ($currentgroup) {
        /*
        $sql = "SELECT u.*
            FROM {role_assignments} ra, {user} u, {course} c, {context} cxt
            WHERE ra.userid = u.id
                AND ra.contextid = cxt.id
                AND cxt.contextlevel = 50
                AND cxt.instanceid = c.id
                AND c.id = ?
                AND roleid =5
                AND u.id IN (SELECT userid FROM {groups_members} gm WHERE gm.groupid = ?)
            ORDER BY u.$sort ASC";
        $params = array($cm->course, $currentgroup);
        $students = $DB->get_records_sql($sql, $params);
        */
        $students = $manager->get_users($sort); // FIXME add $currentgroup somehow
    } else {
        $students = $manager->get_users($sort);
    }

	$sort = $sort == 'firstname' ? 'firstname' : 'lastname';
    /// Now we need a menu for separategroups as well!
    if ($groupmode == VISIBLEGROUPS || 
            ($groupmode && has_capability('moodle/site:accessallgroups', $context))) {
        groups_print_activity_menu($cm, $CFG->wwwroot."/mod/attforblock/attendances.php?id=$id&sessionid=$sessionid&sort=$sort");
    }
	
    $table = new html_table();
	$table->data[][] = '<b>'.get_string('sessiondate','attforblock').': '.userdate($sessdata->sessdate, get_string('strftimedate').', '.get_string('str_ftimehm', 'attforblock')).
							', "'.($sessdata->description ? $sessdata->description : get_string('nodescription', 'attforblock')).'"</b>';
	echo html_writer::table($table);
	
	echo '<script type="text/javascript">'; // substitute for select_all_in() function which does not seem to work with Moodle 2.0
	echo 'function att_select_column(cl) {';
	echo '	var inputs = document.getElementsByTagName("input");';
	echo '	for(i=0,j=0; i<inputs.length; i++) {';
	echo '		var test = " " + inputs[i].className + " ";';
	echo '		if (test.indexOf(cl) != -1) {';
	echo '			inputs[i].checked="checked";';
	echo '		}';
	echo '	}';
	echo '}';
	echo '</script>';
	
	$statuses = get_statuses($course->id);
	$i = 3;
  	foreach($statuses as $st) {
		$tabhead[] = "<a href=\"javascript:att_select_column('attcb{$i}');\" style=\"text-decoration: underline;\">".$st->acronym."</a>";
		$i++;
	}
	$tabhead[] = get_string('remarks','attforblock');
	
	$firstname = "<a href=\"attendances.php?id=$id&amp;sessionid=$sessionid&amp;sort=firstname\">".get_string('firstname').'</a>';
	$lastname  = "<a href=\"attendances.php?id=$id&amp;sessionid=$sessionid&amp;sort=lastname\">".get_string('lastname').'</a>';
    if ($CFG->fullnamedisplay == 'lastname firstname') { // for better view (dlnsk)
        $fullnamehead = "$lastname / $firstname";
    } else {
        $fullnamehead = "$firstname / $lastname";
    }
	
	if ($students) {
        unset($table);
        $table = new html_table();
        $table->head[] = '#';
        $table->align[] = 'center';
        $table->size[] = '20px';
        
        $table->head[] = '';
        $table->align[] = '';
        $table->size[] = '1px';
        
        $table->head[] = $fullnamehead;
        $table->align[] = 'left';
        $table->size[] = '';
        $table->wrap[2] = 'nowrap';
        foreach ($tabhead as $hd) {
            $table->head[] = $hd;
            $table->align[] = 'center';
            $table->size[] = '20px';
        }
        $i = 0;
        foreach($students as $student) {
            $i++;
            $att = $DB->get_record('attendance_log', array('sessionid' => $sessionid, 'studentid' => $student->id));
            $table->data[$student->id][] = (!$att && $update) ? "<b style=\"color:red;\">$i</b>" : $i; 
            $table->data[$student->id][] = $OUTPUT->user_picture($student, array($course->id)); 
			$table->data[$student->id][] = "<a href=\"view.php?id=$id&amp;student={$student->id}\">".((!$att && $update) ? '<b style="color:red;">' : '').fullname($student).((!$att && $update) ? '</b>' : '').'</a>';

			$x = 3;
			foreach($statuses as $st) {
                $table->data[$student->id][] = '<input name="student'.$student->id.'" class="attcb'.$x.'" type="radio" value="'.$st->id.'" '.($st->id == $att->statusid ? 'checked="checked"' : '').'/>';
                $x++;
            }
            $table->data[$student->id][] = '<input type="text" name="remarks'.$student->id.'" size="" value="'.($att ? $att->remarks : '').'"/>';
        }

        echo '<form name="takeattendance" method="post" action="attendances.php">';
        echo html_writer::table($table);
        echo '<div style="text-align: center;">';
        echo '<input type="hidden" name="id" value="'.$id.'"/>';
        echo '<input type="hidden" name="sessionid" value="'.$sessionid.'" />';
        echo '<input type="hidden" name="formfrom" value="editsessvals" />';
        echo '<input type="submit" name="esv" value="'.get_string('ok').'" />';
        echo '</div>';
        echo '</form>';
    } else {
		echo $OUTPUT->heading(get_string('nothingtodisplay'));
	}
	 
	echo get_string('status','attforblock').':<br />'; 
	foreach($statuses as $st) {
		echo $st->acronym.' - '.$st->description.'<br />';
	}

    echo $OUTPUT->footer($course);
    
?>

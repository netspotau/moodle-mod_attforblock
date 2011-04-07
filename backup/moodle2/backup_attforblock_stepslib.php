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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 -
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Define all the backup steps that will be used by the backup_attforblock_activity_task
 */
class backup_attforblock_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        
        // Define each element separated
        $attendance = new backup_nested_element('attendance');
        
        $block = new backup_nested_element('block', array('id'), array(
            'course', 'name', 'grade'));
        
        $statuses = new backup_nested_element('statuses');
        $status = new backup_nested_element('status', array('id'), array(
            'courseid', 'acronym', 'description', 'grade', 'visible', 'deleted'));
        
        $sessions = new backup_nested_element('session', array('id'), array(
            'courseid', 'sessdate', 'duration', 'lasttaken', 'lasttakenby',
            'description', 'timemodified'));
        
        $logs = new backup_nested_element('logs');
        $log = new backup_nested_element('log', array('id'), array(
        	'sessionid', 'studentid', 'statusid', 'statusset', 'timetaken', 'takenby', 'remarks'));

        // Build the tree
        $attendance->add_child($block);
        $attendance->add_child($statuses);
        $statuses->add_child($status);
        $attendance->add_child($sessions);
        $sessions->add_child($logs);
        $logs->add_child($log);
        /*
        $sessions->add_child($logs);
        $logs->add_child($log);
        $sessions->add_child($status);
        $sessions->add_child($block);
        */

        // Define sources
        $block->set_source_table('attforblock', array('id' => backup::VAR_ACTIVITYID));
        $status->set_source_table('attendance_statuses', array('courseid' => backup::VAR_COURSEID));
        $sessions->set_source_table('attendance_sessions', array('courseid' => backup::VAR_COURSEID));
        $log->set_source_table('attendance_log', array('sessionid' => backup::VAR_PARENTID));

        // Define id annotations

        $log->annotate_ids('user', 'studentid');
        
        // Return the root element (chat), wrapped into standard activity structure
        return $this->prepare_activity_structure($attendance);
    }
}

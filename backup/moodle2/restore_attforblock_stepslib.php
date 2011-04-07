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
 * @copyright 2010 onwards -
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_assignment_activity_task
 */

/**
 * Structure step to restore one assignment activity
 */
class restore_attforblock_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        //$userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('attendance_blocks', '/activity/attendance/block');
        $paths[] = new restore_path_element('attendance_statuses', '/activity/attendance/statuses/status');
        $paths[] = new restore_path_element('attendance_sessions', '/activity/attendance/session');
        $paths[] = new restore_path_element('attendance_logs', '/activity/attendance/session/logs/log');

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_attendance_blocks($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('attforblock', $data);
        $this->apply_activity_instance($newitemid);
        
    }

    protected function process_attendance_sessions($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->courseid = $this->get_courseid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // insert the attendance record
        $newitemid = $DB->insert_record('attendance_sessions', $data);
        $this->set_mapping('attendance_sessions', $oldid, $newitemid);
        
    }

    protected function process_attendance_statuses($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->courseid = $this->get_courseid();

        $newitemid = $DB->insert_record('attendance_statuses', $data);
        $this->set_mapping('attendance_statuses', $oldid, $newitemid);
        
    }

    protected function process_attendance_logs($data) {
        global $DB;

        $data = (object)$data;
        
        $data->sessionid = $this->get_new_parentid('attendance_sessions');
        $data->statusid = $this->get_mappingid('attendance_statuses', $data->statusid);
        $data->statusset = ''; // TODO should contain all new status id values, comma-separated
        $data->studentid = $this->get_mappingid('user', $data->studentid);

        $newitemid = $DB->insert_record('attendance_log', $data);
        
    }

    protected function after_execute() {
        // nothing to do here
    }
}

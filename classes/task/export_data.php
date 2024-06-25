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

namespace local_export_api\task;
use core\event\course_completed;
use core\task\adhoc_task;
use core\task\manager;
use local_export_api\api;

class export_data extends adhoc_task {

    public static function instance(course_completed $event): self {
        $task = new self();
        $data = $event->get_data();
        $newdata = [];
        $newdata['relateduserid'] = $data['relateduserid'];
        $newdata['courseid'] = $data['courseid'];
        $newdata['objectid'] = $data['objectid'];
        $task->set_custom_data($newdata);
        manager::reschedule_or_queue_adhoc_task($task);
        return $task;
    }

    /**
     * Execute the task.
     */
    public function execute() {
        $data = json_decode($this->get_custom_data_as_string(), true);
        api::export_data($data);
    }
}
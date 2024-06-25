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

namespace local_export_api;
use context_course;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/user/profile/lib.php');

class api {

    /**
     * Transfer certificate to other platform via REST API
     *
     * @param array $eventdata
     * @return void
     */
    public static function export_data(array $eventdata): void {
        global $DB;
        $userid = $eventdata['relateduserid'];
        $useridnumber = $DB->get_field('user', 'idnumber', ['id' => $userid]);
        $courseid = $eventdata['courseid'];
        $exportdata = [
                'courseid' => $courseid,
                'userid' => $useridnumber,
                'status' => 'completed',
        ];
        mtrace(var_export($exportdata, true), PHP_EOL);
        $json = json_encode($exportdata);
        $error = self::send_request($json);
        if (empty($error)) {
            // Success.
            $data = new stdClass();
            $data->userid = $userid;
            $data->timecreated = time();
            $data->completionid = $eventdata['objectid'];
            $id = $DB->insert_record('local_export_api', $data);
            $eventjson = event\exportcompleted::create([
                    'context' => context_course::instance($courseid),
                    'objectid' => $id,
                    'relateduserid' => $userid,
            ]);
            $eventjson->trigger();
        } else {
            throw new moodle_exception('error', '', '', null, $error);
        }
    }

    /**
     * Send API request using the settings in config.
     *
     * @param string $json
     * @return string empty string on success error message on failure
     */
    public static function send_request(string $json): string {
        $return = '';
        $apiurl = get_config('local_export_api', 'apiurl');
        $apitoken = get_config('local_export_api', 'apitoken');
        $curl = curl_init();
        $headers = [
                'Authorization: Bearer ' . $apitoken,
                'Content-Type: application/json',
        ];
        curl_setopt_array($curl, [
                CURLOPT_URL => $apiurl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $json,
        ]);
        $response = curl_exec($curl);
        // When response is false. There is a problem with the server.
        if ($response !== false) {
            $return = self::validate_response($response, $curl);
        } else {
            $return = "There was an error communicating with the remote server. It may be down: ";
            $info = curl_getinfo($curl);
            $return .= curl_error($curl) . curl_errno($curl) . var_export($info, true);
        }
        curl_close($curl);
        // When we have an empty string, the certificate was successfully transferred.
        return $return;
    }

    /**
     * Check if entry exists in the JSON response.
     *
     * @param string $response The JSON response from api request.
     * @param mixed $curl
     * @return string empty on success or error message.
     */
    public static function validate_response(string $response, $curl): string {
        // When something did not work well, status 400 is returned and an error message.
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // Request was successful.
        if ($httpcode == "200" || $httpcode === 200) {
            return '';
        } else {
            return "Error $httpcode: There following error occured during transfer of data: " . $response;
        }
    }
}
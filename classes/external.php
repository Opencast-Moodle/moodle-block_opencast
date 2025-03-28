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
 * Opencast block external API
 *
 * @package    block_opencast
 * @category   external
 * @copyright  2021 Tamara Gunkel <tamara.gunkel@wi.uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\apibridge;
use block_opencast\local\series_form;
use block_opencast\local\liveupdate_helper;
use block_opencast\local\upload_helper;
use tool_opencast\seriesmapping;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');

/**
 * Opencast block external functions.
 *
 * @copyright  2021 Tamara Gunkel <tamara.gunkel@wi.uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_opencast_external extends external_api {


    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function submit_series_form_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
            'ocinstanceid' => new external_value(PARAM_INT, 'The Opencast instance id'),
            'seriesid' => new external_value(PARAM_ALPHANUMEXT, 'The series id'),
            'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as json array'),
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_series_titles_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
            'ocinstanceid' => new external_value(PARAM_INT, 'The Opencast instance id'),
            'series' => new external_value(PARAM_RAW, 'Requested series, encoded as json array'),
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function import_series_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
            'ocinstanceid' => new external_value(PARAM_INT, 'The Opencast instance id'),
            'seriesid' => new external_value(PARAM_ALPHANUMEXT, 'Series to be imported'),
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function unlink_series_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
            'ocinstanceid' => new external_value(PARAM_INT, 'The Opencast instance id'),
            'seriesid' => new external_value(PARAM_ALPHANUMEXT, 'Series to be removed'),
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function set_default_series_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
            'ocinstanceid' => new external_value(PARAM_INT, 'The Opencast instance id'),
            'seriesid' => new external_value(PARAM_ALPHANUMEXT, 'Series to be set as default'),
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_liveupdate_info_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
            'ocinstanceid' => new external_value(PARAM_INT, 'The Opencast instance id'),
            'type' => new external_value(PARAM_TEXT, 'The type of domain to check the status from'),
            'identifier' => new external_value(PARAM_ALPHANUMEXT, 'Event id to observe its processing state'),
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function unarchive_uploadjob_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
            'ocinstanceid' => new external_value(PARAM_INT, 'The Opencast instance id'),
            'uploadjobid' => new external_value(PARAM_INT, 'The upload job id'),
        ]);
    }

    /**
     * Submits the series form.
     *
     * @param int $contextid The context id for the course.
     * @param int $ocinstanceid Opencast instance id
     * @param string $seriesid Series identifier
     * @param string $jsonformdata The data from the form, encoded as json array.
     *
     * @return string new series id
     */
    public static function submit_series_form($contextid, int $ocinstanceid, string $seriesid, string $jsonformdata) {
        global $USER, $DB;

        $params = self::validate_parameters(self::submit_series_form_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'seriesid' => $seriesid,
            'jsonformdata' => $jsonformdata,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:createseriesforcourse', $context);

        list($ignored, $course) = get_context_info_array($context->id);

        // Check if the maximum number of series is already reached.
        $courseseries = $DB->get_records('tool_opencast_series', ['ocinstanceid' => $ocinstanceid, 'courseid' => $course->id]);
        if (!$params['seriesid'] && count($courseseries) >= get_config('block_opencast', 'maxseries_' . $ocinstanceid)) {
            throw new moodle_exception('maxseriesreached', 'block_opencast');
        }

        $data = [];
        parse_str($params['jsonformdata'], $data);
        $data['courseid'] = $course->id;

        $metadatacatalog = json_decode(get_config('block_opencast', 'metadataseries_' . $params['ocinstanceid']));
        // Make sure $metadatacatalog is array.
        $metadatacatalog = !empty($metadatacatalog) ? $metadatacatalog : [];
        $createseriesform = new series_form(null, ['courseid' => $course->id,
            'ocinstanceid' => $params['ocinstanceid'],
            'metadata_catalog' => $metadatacatalog, ], 'post', '', null, true, $data);
        $validateddata = $createseriesform->get_data();

        if ($validateddata) {
            $metadata = [];
            foreach ($validateddata as $field => $value) {
                if ($field === 'courseid') {
                    continue;
                }
                if ($field === 'subjects') {
                    $metadata[] = [
                        'id' => 'subject',
                        'value' => implode(',', $value),
                    ];
                } else {
                    $metadata[] = [
                        'id' => $field,
                        'value' => $value,
                    ];
                }
            }

            $apibridge = apibridge::get_instance($params['ocinstanceid']);
            if (!$params['seriesid']) {
                return json_encode($apibridge->create_course_series($course->id, $metadata, $USER->id));
            } else {
                $result = $apibridge->update_series_metadata($params['seriesid'], $metadata);
                if (!$result) {
                    throw new moodle_exception('metadataseriesupdatefailed', 'block_opencast');
                }
                return $result;
            }
        } else {
            throw new moodle_exception('missingrequiredfield');
        }
    }

    /**
     * Retrieves the series titles.
     *
     * @param int $contextid The context id for the course.
     * @param int $ocinstanceid Opencast instance id
     * @param string $series Requested series, encoded as json array.
     *
     * @return string Series titles
     */
    public static function get_series_titles(int $contextid, int $ocinstanceid, string $series) {
        $params = self::validate_parameters(self::get_series_titles_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'series' => $series,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:manageseriesforcourse', $context);

        $serialiseddata = json_decode($params['series']);
        $seriestitles = [];

        $apibridge = apibridge::get_instance($params['ocinstanceid']);
        $seriesrecords = $apibridge->get_multiple_series_by_identifier($serialiseddata);

        foreach ($seriesrecords as $s) {
            $seriestitles[$s->identifier] = $s->title;
        }

        return json_encode($seriestitles);
    }

    /**
     * Imports a series into a course.
     *
     * @param int $contextid The context id for the course.
     * @param int $ocinstanceid Opencast instance id
     * @param string $series Series to be imported
     *
     * @return bool True if successful
     */
    public static function import_series(int $contextid, int $ocinstanceid, string $series) {
        global $USER, $DB;
        $params = self::validate_parameters(self::import_series_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'seriesid' => $series,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:importseriesintocourse', $context);

        list($unused, $course, $cm) = get_context_info_array($context->id);

        // Check if the maximum number of series is already reached.
        $courseseries = $DB->get_records('tool_opencast_series', ['ocinstanceid' => $ocinstanceid, 'courseid' => $course->id]);
        if (count($courseseries) >= get_config('block_opencast', 'maxseries_' . $ocinstanceid)) {
            throw new moodle_exception('maxseriesreached', 'block_opencast');
        }

        // Check if the series id already exists in this course.
        $importingseriesid = $params['seriesid'];
        $existingseries = array_filter($courseseries, function ($courseserie) use ($importingseriesid) {
            return $courseserie->series === $importingseriesid;
        });

        if (count($existingseries) > 0) {
            throw new moodle_exception('importseries_alreadyexists', 'block_opencast');
        }

        $apibridge = apibridge::get_instance($params['ocinstanceid']);
        // Ensure the import series is allowed.
        if (!$apibridge->can_user_import_arbitrary_series($params['seriesid'], $USER->id)) {
            throw new moodle_exception('importseries_notallowed', 'block_opencast');
        }

        // Perform ACL change.
        $result = $apibridge->import_series_to_course_with_acl_change($course->id, $params['seriesid'], $USER->id);

        if ($result->error) {
            throw new moodle_exception('importfailed', 'block_opencast');
        }

        $seriesinfo = new stdClass();
        $seriesinfo->id = $params['seriesid'];
        $seriesinfo->title = $apibridge->get_series_by_identifier($params['seriesid'])->title;
        $seriesinfo->isdefault = $result->seriesmapped->isdefault;

        return json_encode($seriesinfo);
    }

    /**
     * Removes a series from a course but does not delete it in Opencast.
     *
     * @param int $contextid The context id for the course.
     * @param int $ocinstanceid Opencast instance id
     * @param string $series Series to be removed from the course
     *
     * @return bool True if successful
     */
    public static function unlink_series(int $contextid, int $ocinstanceid, string $series) {
        $params = self::validate_parameters(self::unlink_series_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'seriesid' => $series,
        ]);

        $unlinkall = $params['seriesid'] === 'all';

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:manageseriesforcourse', $context);

        list($unused, $course, $cm) = get_context_info_array($context->id);

        // In case the request comes from block deletion remove all mappings.
        if ($unlinkall) {
            $mappings = seriesmapping::get_records(['courseid' => $course->id]);
        } else {
            // Otherwise, the request comes from normal series deletion page.
            $mappings = seriesmapping::get_records(['ocinstanceid' => $params['ocinstanceid'], 'courseid' => $course->id,
                    'series' => $params['seriesid']]);
        }

        foreach ($mappings as $mapping) {
            $isdefault = $mapping->get('isdefault');
            // We need to check the uniqueness of the mapping record when it is a single mapping removal.
            if ($isdefault && !$unlinkall) {
                // Prevent deletion of default series.
                // By checking the number of default series,
                // it is still possible to correct the faulty scenario of having multi-default series in a course.
                if (seriesmapping::count_records(['ocinstanceid' => $params['ocinstanceid'],
                        'courseid' => $course->id, 'isdefault' => true, ]) === 1) {
                    throw new moodle_exception('cantdeletedefaultseries', 'block_opencast');
                }
            }

            if (!$mapping->delete()) {
                throw new moodle_exception('delete_series_failed', 'block_opencast');
            }

            // Unlinking series from course.
            $apibridge = apibridge::get_instance($mapping->get('ocinstanceid'));
            $seriesunlinked = $apibridge->unlink_series_from_course($course->id, $mapping->get('series'));

            if (!$seriesunlinked) {
                throw new moodle_exception('delete_series_failed', 'block_opencast');
            }
        }

        return true;
    }

    /**
     * Sets a new default series for a course.
     *
     * @param int $contextid The context id for the course.
     * @param int $ocinstanceid Opencast instance id
     * @param string $series Series to be set as default
     *
     * @return bool True if successful
     */
    public static function set_default_series(int $contextid, int $ocinstanceid, string $series) {
        $params = self::validate_parameters(self::set_default_series_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'seriesid' => $series,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:manageseriesforcourse', $context);

        list($unused, $course, $cm) = get_context_info_array($context->id);

        $olddefaultseries = seriesmapping::get_record(['ocinstanceid' => $params['ocinstanceid'],
            'courseid' => $course->id, 'isdefault' => true, ]);

        // Series is already set as default.
        // We provide an exception here to fix the problem of having a course with no default series, which should not happen,
        // by letting it pass through when the old default does not exist.
        if (!empty($olddefaultseries) && $olddefaultseries->get('series') == $params['seriesid']) {
            return true;
        }

        // Set new series as default.
        $mapping = seriesmapping::get_record(['ocinstanceid' => $params['ocinstanceid'],
            'courseid' => $course->id, 'series' => $params['seriesid'], ], true);

        // Remove default flag from old series first.
        $canbeupdated = empty($olddefaultseries);
        if (!empty($olddefaultseries)) {
            $olddefaultseries->set('isdefault', false);
            if ($olddefaultseries->update()) {
                $canbeupdated = true;
            }
        }

        // Now, we go for the actual update.
        if ($canbeupdated && $mapping) {
            $mapping->set('isdefault', true);
            if ($mapping->update()) {
                return true;
            }
        }

        throw new moodle_exception('setdefaultseriesfailed', 'block_opencast');
    }

    /**
     * Returns the live update info for:
     * - Workflow processing state.
     * - Upload status.
     *
     * @param int $contextid The context id for the course.
     * @param int $ocinstanceid Opencast instance id
     * @param string $type the type of live update to check against
     * @param string $identifier the identifier to get records for
     *
     * @return string Latest update state info
     */
    public static function get_liveupdate_info(int $contextid, int $ocinstanceid, string $type, string $identifier) {
        $params = self::validate_parameters(self::get_liveupdate_info_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'type' => $type,
            'identifier' => $identifier,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:viewunpublishedvideos', $context);

        // Initialise the live update info as an empty array.
        $liveupdateinfo = [];
        // Get processing state info.
        if ($params['type'] == 'processing') {
            $liveupdateinfo = liveupdate_helper::get_processing_state_info($params['ocinstanceid'], $params['identifier']);
        } else if ($type == 'uploading') {
            // Get uploading status.
            $liveupdateinfo = liveupdate_helper::get_uploading_info($params['identifier']);
        }

        // Force to have replace and remove params, otherwise empty must be returned.
        if (!isset($liveupdateinfo['replace']) || !isset($liveupdateinfo['remove'])) {
            // Returning empty string helps to remove the item in the javascript, that results in cleaning the interval.
            return '';
        }

        // Finally, we return info as json encoded string.
        return json_encode($liveupdateinfo);
    }

    /**
     * Perform unarchiving an uploadjob.
     *
     * @param int $contextid The context id for the course.
     * @param int $ocinstanceid Opencast instance id
     * @param int $uploadjobid Uploadjob id
     *
     * @return string Latest update state info
     */
    public static function unarchive_uploadjob(int $contextid, int $ocinstanceid, int $uploadjobid) {
        global $USER, $DB;
        $params = self::validate_parameters(self::unarchive_uploadjob_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'uploadjobid' => $uploadjobid,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:addvideo', $context);

        list($unused, $course, $cm) = get_context_info_array($context->id);

        $params = [
            'id' => $params['uploadjobid'],
            'ocinstanceid' => $params['ocinstanceid'],
            'courseid' => $course->id,
            'status' => upload_helper::STATUS_ARCHIVED_FAILED_UPLOAD,
        ];
        $uploadjob = $DB->get_record('block_opencast_uploadjob', $params);

        if (!empty($uploadjob)) {
            $time = time();
            $uploadjob->timemodified = $time;
            $uploadjob->countfailed = 0;
            $uploadjob->status = upload_helper::STATUS_READY_TO_UPLOAD;
            $DB->update_record('block_opencast_uploadjob', $uploadjob);
            return true;
        }

        throw new moodle_exception('uploadjobnotfound', 'block_opencast');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function submit_series_form_returns() {
        return new external_value(PARAM_RAW, 'Json series data');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_series_titles_returns() {
        return new external_value(PARAM_RAW, 'json array for the series');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function import_series_returns() {
        return new external_value(PARAM_RAW, 'Json series data');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function unlink_series_returns() {
        return new external_value(PARAM_BOOL, 'True if successful');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function set_default_series_returns() {
        return new external_value(PARAM_BOOL, 'True if successful');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_liveupdate_info_returns() {
        return new external_value(PARAM_RAW, 'Json live update info');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function unarchive_uploadjob_returns() {
        return new external_value(PARAM_BOOL, 'True if successful');
    }
}

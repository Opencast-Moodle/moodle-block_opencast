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
use tool_opencast\seriesmapping;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');

/**
 * Opencast block external functions.
 *
 * @copyright  2021 Tamara Gunkel <tamara.gunkel@wi.uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_opencast_external extends external_api
{

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function submit_series_form_parameters()
    {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
            'ocinstanceid' => new external_value(PARAM_INT, 'The Opencast instance id'),
            'seriesid' => new external_value(PARAM_ALPHANUMEXT, 'The series id'),
            'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as json array')
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_series_titles_parameters()
    {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
            'ocinstanceid' => new external_value(PARAM_INT, 'The Opencast instance id'),
            'series' => new external_value(PARAM_RAW, 'Requested series, encoded as json array')
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
            'seriesid' => new external_value(PARAM_ALPHANUMEXT, 'Series to be imported')
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
            'seriesid' => new external_value(PARAM_ALPHANUMEXT, 'Series to be removed')
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
            'seriesid' => new external_value(PARAM_ALPHANUMEXT, 'Series to be set as default')
        ]);
    }

    /**
     * Submits the series form.
     *
     * @param int $contextid The context id for the course.
     * @param string $jsonformdata The data from the form, encoded as json array.
     *
     * @return string new series id
     */
    public static function submit_series_form($contextid, int $ocinstanceid, string $seriesid, string $jsonformdata)
    {
        global $USER;

        $params = self::validate_parameters(self::submit_series_form_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'seriesid' => $seriesid,
            'jsonformdata' => $jsonformdata
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:createseriesforcourse', $context);

        list($ignored, $course) = get_context_info_array($context->id);

        $data = array();
        parse_str($params['jsonformdata'], $data);
        $data['courseid'] = $course->id;

        $metadatacatalog = json_decode(get_config('block_opencast', 'metadataseries_' . $params['ocinstanceid']));
        $createseriesform = new series_form(null, array('courseid' => $course->id,
            'ocinstanceid' => $params['ocinstanceid'],
            'metadata_catalog' => $metadatacatalog), 'post', '', null, true, $data);
        $validateddata = $createseriesform->get_data();

        if ($validateddata) {
            $metadata = [];
            foreach ($validateddata as $field => $value) {
                if ($field === 'courseid') {
                    continue;
                }

                $metadata[] = array(
                    'id' => $field,
                    'value' => $value
                );
            }

            $apibridge = apibridge::get_instance($params['ocinstanceid']);
            if (!$params['seriesid']) {
                return json_encode($apibridge->create_course_series($course->id, $metadata, $USER->id));
            } else {
                return $apibridge->update_series_metadata($params['seriesid'], $metadata);
            }
        } else {
            throw new moodle_exception('missingrequiredfield');
        }
    }

    /**
     * Retrieves the series titles.
     *
     * @param int $contextid The context id for the course.
     * @param string $series Requested series, encoded as json array.
     *
     * @return string Series titles
     */
    public static function get_series_titles(int $contextid, int $ocinstanceid, string $series)
    {
        $params = self::validate_parameters(self::get_series_titles_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'series' => $series
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:defineseriesforcourse', $context);

        $serialiseddata = json_decode($params['series']);
        $seriestitles = array();

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
     * @param string $series Series to be imported
     *
     * @return bool True if successful
     */
    public static function import_series(int $contextid, int $ocinstanceid, string $series)
    {
        global $USER;
        $params = self::validate_parameters(self::import_series_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'seriesid' => $series
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:importseriesintocourse', $context);

        list($unused, $course, $cm) = get_context_info_array($context->id);

        // Perform ACL change.
        $apibridge = apibridge::get_instance($params['ocinstanceid']);
        $result= $apibridge->import_series_to_course_with_acl_change($course->id, $params['seriesid'], $USER->id);

        if($result->error){
            // TODO improve message
            throw new moodle_exception('missingrequiredfield');
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
     * @param string $series Series to be removed from the course
     *
     * @return bool True if successful
     */
    public static function unlink_series(int $contextid, int $ocinstanceid, string $series)
    {
        global $USER;
        $params = self::validate_parameters(self::unlink_series_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'seriesid' => $series
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:manageseriesforcourse', $context);


        list($unused, $course, $cm) = get_context_info_array($context->id);

        $mapping = seriesmapping::get_record(array('ocinstanceid' => $params['ocinstanceid'], 'courseid' => $course->id,
            'series' => $params['seriesid']), true);

        if ($mapping) {
            if($mapping->get('isdefault')) {
                throw new moodle_exception('cantdeletedefaultseries', 'block_opencast');
            }

            if(!$mapping->delete()) {
                throw new moodle_exception('delete_series_failed', 'block_opencast');
            }
        }

        return true;
    }

    /**
     * Sets a new default series for a course.
     *
     * @param int $contextid The context id for the course.
     * @param string $series Series to be set as default
     *
     * @return bool True if successful
     */
    public static function set_default_series(int $contextid, int $ocinstanceid, string $series)
    {
        $params = self::validate_parameters(self::set_default_series_parameters(), [
            'contextid' => $contextid,
            'ocinstanceid' => $ocinstanceid,
            'seriesid' => $series
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:manageseriesforcourse', $context);

        list($unused, $course, $cm) = get_context_info_array($context->id);

        $olddefaultseries = seriesmapping::get_record(array('ocinstanceid' => $params['ocinstanceid'],'courseid' => $course->id, 'isdefault' => true));

        // Series is already set as default.
        if ($olddefaultseries->get('series') == $params['seriesid']) {
            return true;
        }

        // Set new series as default.
        $mapping = seriesmapping::get_record(array('ocinstanceid' => $params['ocinstanceid'],'courseid' => $course->id, 'series' => $params['seriesid']), true);

        if ($mapping) {
            $mapping->set('isdefault', true);
            if ($mapping->update()) {
                // Remove default flag from old series.
                if ($olddefaultseries) {
                    $olddefaultseries->set('isdefault', false);
                    if ($olddefaultseries->update()) {
                        return true;
                    }
                }
            }
        }

        throw new moodle_exception('setdefaultseriesfailed', 'block_opencast');
    }


    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function submit_series_form_returns()
    {
        return new external_value(PARAM_RAW, 'Json series data');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_series_titles_returns()
    {
        // TODO create structre insead of using generic value.
        return new external_value(PARAM_RAW, 'json array for the series');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function import_series_returns()
    {
        return new external_value(PARAM_RAW, 'Json series data');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function unlink_series_returns()
    {
        return new external_value(PARAM_BOOL, 'True if successful');
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function set_default_series_returns()
    {
        return new external_value(PARAM_BOOL, 'True if successful');
    }
}

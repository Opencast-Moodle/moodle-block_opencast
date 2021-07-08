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
            'series' => new external_value(PARAM_RAW, 'Requested series, encoded as json array')
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
    public static function submit_series_form($contextid, string $jsonformdata)
    {
        global $USER;

        $params = self::validate_parameters(self::submit_series_form_parameters(), [
            'contextid' => $contextid,
            'jsonformdata' => $jsonformdata
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:createseriesforcourse', $context);

        list($ignored, $course) = get_context_info_array($context->id);

        $data = array();
        parse_str($params['jsonformdata'], $data);
        $data['courseid'] = $course->id;

        $metadatacatalog = json_decode(get_config('block_opencast', 'metadataseries'));
        $createseriesform = new series_form(null, array('courseid' => $course->id,
            'metadata_catalog' => $metadatacatalog), 'post', '', null, true, $data);
        $validateddata = $createseriesform->get_data();

        if ($validateddata) {
            $metadata = [];
            foreach ($validateddata as $field => $value) {
                if ($field === 'courseid' || $field === 'seriesid') {
                    continue;
                }

                $metadata[] = array(
                    'id' => $field,
                    'value' => $value
                );
            }

            $apibridge = apibridge::get_instance();
            if (empty($validateddata->seriesid)) {
                // TODO also set other metadata if given
                return json_encode($apibridge->create_course_series($course->id, $validateddata->seriestitle, $USER->id));
            } else {

                return $apibridge->update_series_metadata($validateddata->seriesid, $metadata);
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
    public static function get_series_titles(int $contextid, string $series)
    {
        $params = self::validate_parameters(self::get_series_titles_parameters(), [
            'contextid' => $contextid,
            'series' => $series
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('block/opencast:defineseriesforcourse', $context);

        $serialiseddata = json_decode($params['series']);
        $seriestitles = array();

        $apibridge = apibridge::get_instance();
        $seriesrecords = $apibridge->get_multiple_series_by_identifier($serialiseddata);

        foreach ($seriesrecords as $s) {
            $seriestitles[$s->identifier] = $s->title;
        }

        return json_encode($seriestitles);
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
        return new external_value(PARAM_RAW, 'json array for the series');
    }
}

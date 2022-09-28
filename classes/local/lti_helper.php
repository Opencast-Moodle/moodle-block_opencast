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
 * LTI helper.
 * @package    block_opencast
 * @copyright  2022 Tamara Gunkel WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use oauth_helper;

/**
 * LTI helper.
 * @package    block_opencast
 * @copyright  2022 Tamara Gunkel WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lti_helper {

    /**
     * Create necessary lti parameters.
     * @param string $consumerkey LTI consumer key.
     * @param string $consumersecret LTI consumer secret.
     * @param string $endpoint of the opencast instance.
     * @param string $customtool
     *
     * @return array lti parameters
     */
    public static function create_lti_parameters($consumerkey, $consumersecret, $endpoint, $customtool) {
        global $CFG, $COURSE, $USER;

        $helper = new oauth_helper(array('oauth_consumer_key' => $consumerkey,
            'oauth_consumer_secret' => $consumersecret));

        // Set all necessary parameters.
        $params = array();
        $params['oauth_version'] = '1.0';
        $params['oauth_nonce'] = $helper->get_nonce();
        $params['oauth_timestamp'] = $helper->get_timestamp();
        $params['oauth_consumer_key'] = $consumerkey;

        $params['context_id'] = $COURSE->id;
        $params['context_label'] = trim($COURSE->shortname);
        $params['context_title'] = trim($COURSE->fullname);
        $params['resource_link_id'] = 'o' . random_int(1000, 9999) . '-' . random_int(1000, 9999);
        $params['resource_link_title'] = 'Opencast';
        $params['context_type'] = ($COURSE->format == 'site') ? 'Group' : 'CourseSection';
        $params['launch_presentation_locale'] = current_language();
        $params['ext_lms'] = 'moodle-2';
        $params['tool_consumer_info_product_family_code'] = 'moodle';
        $params['tool_consumer_info_version'] = strval($CFG->version);
        $params['oauth_callback'] = 'about:blank';
        $params['lti_version'] = 'LTI-1p0';
        $params['lti_message_type'] = 'basic-lti-launch-request';
        $urlparts = parse_url($CFG->wwwroot);
        $params['tool_consumer_instance_guid'] = $urlparts['host'];
        $params['custom_tool'] = urlencode($customtool);

        // User data.
        $params['user_id'] = $USER->id;
        $params['lis_person_name_given'] = $USER->firstname;
        $params['lis_person_name_family'] = $USER->lastname;
        $params['lis_person_name_full'] = $USER->firstname . ' ' . $USER->lastname;
        $params['ext_user_username'] = $USER->username;
        $params['lis_person_contact_email_primary'] = $USER->email;
        $params['roles'] = lti_get_ims_role($USER, null, $COURSE->id, false);

        if (!empty($CFG->mod_lti_institution_name)) {
            $params['tool_consumer_instance_name'] = trim(html_to_text($CFG->mod_lti_institution_name, 0));
        } else {
            $params['tool_consumer_instance_name'] = get_site()->shortname;
        }

        $params['launch_presentation_document_target'] = 'iframe';
        $params['oauth_signature_method'] = 'HMAC-SHA1';
        $signedparams = lti_sign_parameters($params, $endpoint, "POST", $consumerkey, $consumersecret);
        $params['oauth_signature'] = $signedparams['oauth_signature'];

        return $params;
    }
}

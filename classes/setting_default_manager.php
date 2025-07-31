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
 * Manager class for admin setting defaults.
 *
 * @package    block_opencast
 * @copyright  2023 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

/**
 * Manager class for admin setting defaults.
 *
 * @package    block_opencast
 * @copyright  2023 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_default_manager {


    /**
     * Initializes the set default settings provided in this class.
     * Methods starting with set_default will be called to set configs.
     *
     * @param int $ocinstanceid ocinstance id
     */
    public static function init_regirstered_defaults($ocinstanceid = 1) {
        $classmethods = get_class_methods('\block_opencast\setting_default_manager');
        foreach ($classmethods as $methodname) {
            if (strpos($methodname, 'set_default') !== false) {
                self::$methodname($ocinstanceid);
            }
        }
    }

    /**
     * Returns metadata default setting.
     *
     * @return string json default setting string
     */
    public static function get_default_metadata() {
        return '[' .
            '{"name":"title","datatype":"text","required":1,"readonly":0,"param_json":"{\"style\":\"min-width: 27ch;\"}"},' .
            '{"name":"subjects","datatype":"autocomplete","required":0,"readonly":0,"param_json":null,"defaultable":0,' .
            '"batchable":0},' .
            '{"name":"description","datatype":"textarea","required":0,"readonly":0,"param_json":' .
            '"{\"rows\":\"3\",\"cols\":\"19\"}","defaultable":0,"batchable":0},' .
            '{"name":"language","datatype":"select","required":0,"readonly":0,"param_json":"{\"\":\"No option selected\",' .
            '\"slv\":\"Slovenian\",\"por\":\"Portugese\",\"roh\":\"Romansh\",\"ara\":\"Arabic\",\"pol\":\"Polish\",\"ita\":' .
            '\"Italian\",\"zho\":\"Chinese\",\"fin\":\"Finnish\",\"dan\":\"Danish\",\"ukr\":\"Ukrainian\",\"fra\":\"French\",' .
            '\"spa\":\"Spanish\",\"gsw\":\"Swiss German\",\"nor\":\"Norwegian\",\"rus\":\"Russian\",\"jpx\":\"Japanese\",' .
            '\"nld\":\"Dutch\",\"tur\":\"Turkish\",\"hin\":\"Hindi\",\"swa\":\"Swedish\",' .
            '\"eng\":\"English\",\"deu\":\"German\"}","defaultable":0,"batchable":0},' .
            '{"name":"rightsHolder","datatype":"text","required":0,"readonly":0,"param_json":' .
            '"{\"style\":\"min-width: 27ch;\"}","defaultable":0,"batchable":0},' .
            '{"name":"license","datatype":"select","required":0,"readonly":0,"param_json":"{\"\":\"No option selected\",' .
            '\"ALLRIGHTS\":\"All Rights Reserved\",\"CC0\":\"CC0\",\"CC-BY-ND\":\"CC BY-ND\",\"CC-BY-NC-ND\":\"CC BY-NC-ND\",' .
            '\"CC-BY-NC-SA\":\"CC BY-NC-SA\",\"CC-BY-SA\":\"CC BY-SA\",\"CC-BY-NC\":\"CC BY-NC\",\"CC-BY\":\"CC BY\"}",' .
            '"defaultable":0,"batchable":0},' .
            '{"name":"creator","datatype":"autocomplete","required":0,"readonly":0,"param_json":null,"defaultable":0,' .
            '"batchable":0},' .
            '{"name":"contributor","datatype":"autocomplete","required":0,"readonly":0,"param_json":null,"defaultable":0,' .
            '"batchable":0},' .
            '{"name":"location","datatype":"text","required":0,"readonly":1,"param_json":"{\"value\":\"Moodle\"}"' .
            ',"defaultable":0,"batchable":0}]';
    }

    /**
     * Sets the default config for metadata.
     *
     * @param int $ocinstanceid ocinstance id
     */
    public static function set_default_metadata($ocinstanceid = 1) {
        $configname = 'metadata_' . $ocinstanceid;
        $currentmetadata = get_config('block_opencast', $configname);
        if (empty($currentmetadata)) {
            set_config($configname, self::get_default_metadata(), 'block_opencast');
        }
    }

    /**
     * Returns series metadata default setting.
     *
     * @return string json default setting string
     */
    public static function get_default_metadataseries() {
        return '[' .
            '{"name":"title","datatype":"text","required":1,"readonly":0,"param_json":"{\"style\":\"min-width: 27ch;\"}"},' .
            '{"name":"subjects","datatype":"autocomplete","required":0,"readonly":0,"param_json":null,"defaultable":0},' .
            '{"name":"description","datatype":"textarea","required":0,"readonly":0,"param_json":' .
            '"{\"rows\":\"3\",\"cols\":\"19\"}","defaultable":0},' .
            '{"name":"language","datatype":"select","required":0,"readonly":0,"param_json":"{\"\":\"No option selected\",' .
            '\"slv\":\"Slovenian\",\"por\":\"Portugese\",\"roh\":\"Romansh\",\"ara\":\"Arabic\",\"pol\":\"Polish\",\"ita\":' .
            '\"Italian\",\"zho\":\"Chinese\",\"fin\":\"Finnish\",\"dan\":\"Danish\",\"ukr\":\"Ukrainian\",\"fra\":\"French\",' .
            '\"spa\":\"Spanish\",\"gsw\":\"Swiss German\",\"nor\":\"Norwegian\",\"rus\":\"Russian\",\"jpx\":\"Japanese\",' .
            '\"nld\":\"Dutch\",\"tur\":\"Turkish\",\"hin\":\"Hindi\",\"swa\":\"Swedish\",' .
            '\"eng\":\"English\",\"deu\":\"German\"}","defaultable":0},' .
            '{"name":"rightsHolder","datatype":"text","required":1,"readonly":0,"param_json":' .
            '"{\"style\":\"min-width: 27ch;\"}", "defaultable":0},' .
            '{"name":"license","datatype":"select","required":1,"readonly":0,"param_json":"{\"\":\"No option selected\",' .
            '\"ALLRIGHTS\":\"All Rights Reserved\",\"CC0\":\"CC0\",\"CC-BY-ND\":\"CC BY-ND\",\"CC-BY-NC-ND\":\"CC BY-NC-ND\",' .
            '\"CC-BY-NC-SA\":\"CC BY-NC-SA\",\"CC-BY-SA\":\"CC BY-SA\",\"CC-BY-NC\":\"CC BY-NC\",\"CC-BY\":\"CC BY\"}",' .
            '"defaultable":0},' .
            '{"name":"creator","datatype":"autocomplete","required":0,"readonly":0,"param_json":null,"defaultable":0},' .
            '{"name":"contributor","datatype":"autocomplete","required":0,"readonly":0,"param_json":null,"defaultable":0}]';
    }

    /**
     * Sets the default config for series metadata.
     *
     * @param int $ocinstanceid ocinstance id
     */
    public static function set_default_metadataseries($ocinstanceid = 1) {
        $configname = 'metadataseries_' . $ocinstanceid;
        $currentmetadata = get_config('block_opencast', $configname);
        if (empty($currentmetadata)) {
            set_config($configname, self::get_default_metadataseries(), 'block_opencast');
        }
    }

    /**
     * Returns transcription languages default setting.
     *
     * @return string json default setting string
     */
    public static function get_default_transcriptionlanguages() {
        return '[{"key":"de","value":"German"},' .
            '{"key":"en","value":"English"}]';
    }

    /**
     * Sets the default config for transcription languages.
     *
     * @param int $ocinstanceid ocinstance id
     */
    public static function set_default_transcriptionlanguages($ocinstanceid = 1) {
        $configname = 'transcriptionlanguages_' . $ocinstanceid;
        $currentmetadata = get_config('block_opencast', $configname);
        if (empty($currentmetadata)) {
            set_config($configname, self::get_default_transcriptionlanguages(), 'block_opencast');
        }
    }

    /**
     * Returns roles default setting.
     *
     * @return string json default setting string
     */
    public static function get_default_roles() {
        return '[{"rolename":"ROLE_ADMIN","actions":"write,read","permanent":1},' .
            '{"rolename":"ROLE_GROUP_MH_DEFAULT_ORG_EXTERNAL_APPLICATIONS","actions":"write,read","permanent":1},' .
            '{"rolename":"[COURSEID]_Instructor","actions":"write,read","permanent":1},' .
            '{"rolename":"[COURSEGROUPID]_Learner","actions":"read","permanent":0}]';
    }

    /**
     * Sets the default config for roles.
     *
     * @param int $ocinstanceid ocinstance id
     */
    public static function set_default_roles($ocinstanceid = 1) {
        $configname = 'roles_' . $ocinstanceid;
        $currentmetadata = get_config('block_opencast', $configname);
        if (empty($currentmetadata)) {
            set_config($configname, self::get_default_roles(), 'block_opencast');
        }
    }

    /**
     * Returns maxseries default setting.
     *
     * @return int max series number
     */
    public static function get_default_maxseries() {
        return 3;
    }

    /**
     * Sets the default config for maxseries.
     *
     * @param int $ocinstanceid ocinstance id
     */
    public static function set_default_maxseries($ocinstanceid = 1) {
        $configname = 'maxseries_' . $ocinstanceid;
        $currentmetadata = get_config('block_opencast', $configname);
        if (empty($currentmetadata)) {
            set_config($configname, self::get_default_maxseries(), 'block_opencast');
        }
    }

    /**
     * Returns limitvideos default setting.
     *
     * @return int limit videos number
     */
    public static function get_default_limitvideos() {
        return 5;
    }

    /**
     * Sets the default config for limitvideos.
     *
     * @param int $ocinstanceid ocinstance id
     */
    public static function set_default_limitvideos($ocinstanceid = 1) {
        $configname = 'limitvideos_' . $ocinstanceid;
        $currentmetadata = get_config('block_opencast', $configname);
        if (empty($currentmetadata)) {
            set_config($configname, self::get_default_limitvideos(), 'block_opencast');
        }
    }
}

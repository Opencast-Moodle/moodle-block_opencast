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
 * User selector for change user dialog.
 *
 * @package    block_opencast
 * @copyright  2022 Tamara Gunkel, WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');

/**
 * User selector for change user dialog.
 *
 * @package block_opencast
 * @copyright 2022 Tamara Gunkel, WWU
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_opencast_enrolled_user_selector extends user_selector_base {
    /** @var bool|context|context_system|mixed|null Moodle context, usually course */
    protected $context;

    /**
     * Creates the selector.
     * @param string $name control name
     * @param array $options should have two elements with keys groupid and courseid.
     */
    public function __construct($name, $options) {
        if (isset($options['context'])) {
            $this->context = $options['context'];
        } else {
            $this->context = context::instance_by_id($options['contextid']);
        }
        parent::__construct($name, $options);
    }

    /**
     * Get options supported by the selector.
     * @return array
     */
    protected function get_options() {
        $options = parent::get_options();
        $options['contextid'] = $this->context->id;
        return $options;
    }

    /**
     * Returns enrolled users in a course.
     * @param string $search Search conditions
     * @return array|array[] Users
     * @throws coding_exception
     * @throws dml_exception
     */
    public function find_users($search) {
        global $DB;

        list($enrolsql, $eparams) = get_enrolled_sql($this->context);

        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params = array_merge($params, $eparams);

        $fields = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(u.id)';

        $sql = " FROM ($enrolsql) enrolled_users_view
                   JOIN {user} u ON u.id = enrolled_users_view.id
                  WHERE $wherecondition";
        $params['contextid'] = $this->context->id;

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }

        if ($search) {
            $groupname = get_string('potusersmatching', 'core_role', $search);
        } else {
            $groupname = get_string('potusers', 'core_role');
        }

        return array($groupname => $availableusers);
    }
}

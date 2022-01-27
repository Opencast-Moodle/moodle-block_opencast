<?php


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');

class block_opencast_enrolled_user_selector extends user_selector_base {
    protected $context;

    /**
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

    protected function get_options() {
        $options = parent::get_options();
        $options['contextid'] = $this->context->id;
        return $options;
    }

    public function find_users($search) {
        global $DB;

        list($enrolsql, $eparams) = get_enrolled_sql($this->context);

        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params = array_merge($params, $eparams);

        $fields = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(u.id)';

        $sql   = " FROM ($enrolsql) enrolled_users_view
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
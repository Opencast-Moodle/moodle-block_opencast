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
 * Mass action helper class.
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use coding_exception;
use stdClass;
use html_writer;

/**
 * Mass action helper class.
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class massaction_helper {

    /** @var string Toggle group name. */
    const TOGGLE_GROUP_NAME = 'opencast-videos-table';

    /** @var string Checkbox selectall class name. */
    const CHECKBOX_SELECTALL_CLASSNAME = 'opencast-videos-selectall';
    /** @var string Checkbox selectall id. */
    const CHECKBOX_SELECTALL_ID = 'select-all-opencast-videos';

    /** @var string Checkbox select item class name. */
    const CHECKBOX_SELECTITEM_CLASSNAME = 'opencast-video-select';
    /** @var string Checkbox select item disabled class name. */
    const CHECKBOX_SELECTITEM_DISABLED_CLASSNAME = 'opencast-videos-table-disabled';

    /** @var string Select dropdown class name. */
    const SELECT_DROPDOWN_CLASSNAME = 'opencast-videos-table-massactions';

    /** @var string Actions mapping hidden input id. */
    const HIDDEN_INPUT_ACTIONS_MAPPING_ID = 'opencast-videos-table-massactions-actionsmapping';

    /** @var string Massaction type change visibility. */
    const MASSACTION_CHANGEVISIBILITY = 'changevisibility';
    /** @var string Massaction type delete. */
    const MASSACTION_DELETE = 'delete';
    /** @var string Massaction type update metadata. */
    const MASSACTION_UPDATEMETADATA = 'updatemetadata';
    /** @var string Massaction type start workflow. */
    const MASSACTION_STARTWORKFLOW = 'startworkflow';

    /** @var array Mass-Action configuration mapping. */
    public $massactions = [
        self::MASSACTION_DELETE => [
            'path' => [
                'url' => '/blocks/opencast/deleteevent_massaction.php',
            ],
            'enable' => true,
        ],
        self::MASSACTION_UPDATEMETADATA => [
            'path' => [
                'url' => '/blocks/opencast/updatemetadata_massaction.php',
            ],
            'enable' => true,
        ],
        self::MASSACTION_CHANGEVISIBILITY => [
            'path' => [
                'url' => '/blocks/opencast/changevisibility_massaction.php',
            ],
            'enable' => true,
        ],
        self::MASSACTION_STARTWORKFLOW => [
            'path' => [
                'url' => '/blocks/opencast/startworkflow_massaction.php',
            ],
            'enable' => true,
        ],
    ];

    /** @var bool Whether the whole feature is activated or not. */
    private $isactivated = true;

    /** @var bool Whether hidden input for actions mapping is rendered. */
    private $mappingisrendered = false;

    /**
     * Constructs the mass action helper class.
     *
     * This function initializes the mass actions based on the provided default actions.
     * If default actions are provided, they are validated and set as the mass actions.
     * If the default actions are not valid, a coding_exception is thrown.
     *
     * @param array $defaultactions An optional array of default mass actions.
     * Each default action is represented as an associative array with the following structure:
     * [
     *     'name' => (string) The unique name of the mass action,
     *     'path' => [
     *         'url' => (string) The URL of the mass action handler,
     *         'params' => (array) The URL parameters of the mass action handler (optional),
     *     ],
     *     'enable' => (bool) Whether the mass action is enabled or not,
     * ]
     *
     * @throws coding_exception If the default actions are not valid.
     */
    public function __construct(array $defaultactions = null) {
        if (!empty($defaultactions)) {
            $this->massactions = $defaultactions;
            if (!$this->validate_massactions()) {
                throw new coding_exception('massaction_invaliddefaultactions', 'block_opencast');
            }
        }
    }

    /**
     * Renders the master checkbox for mass actions.
     *
     * This function generates a checkbox that acts as a master toggle for all the video items in the table.
     * The checkbox is only rendered if there are any enabled mass actions available.
     *
     * @return string The HTML markup for the master checkbox. If no mass actions are available, an empty string is returned.
     */
    public function render_master_checkbox() {
        global $OUTPUT;
        if (!$this->has_massactions()) {
            return '';
        }
        $mastercheckbox = new \core\output\checkbox_toggleall(self::TOGGLE_GROUP_NAME, true, [
            'classes' => self::CHECKBOX_SELECTALL_CLASSNAME,
            'id' => self::CHECKBOX_SELECTALL_ID,
            'name' => self::CHECKBOX_SELECTALL_ID,
            'label' => get_string('selectall'),
            // Consistent label to prevent unwanted text change when we automatically uncheck.
            'selectall' => get_string('selectall'),
            'deselectall' => get_string('selectall'),
            // We need the classes specially for behat test to pickup the checkbox.
            'labelclasses' => 'form-check-label d-block pe-2 sr-only',
        ]);
        return $OUTPUT->render($mastercheckbox);
    }

    /**
     * Renders a checkbox for a video item in the mass action table.
     *
     * This function generates a checkbox for a video item in the mass action table.
     * The checkbox is disabled when the video item is not selectable.
     *
     * @param stdClass $video The video object containing the video's identifier and title.
     * @param bool $isselectable Whether the video item is selectable or not. Default is true.
     *
     * @return string The HTML markup for the checkbox. If mass actions are not available, an empty string is returned.
     */
    public function render_item_checkbox(stdClass $video, bool $isselectable = true) {
        global $OUTPUT;

        if (!$this->has_massactions()) {
            return '';
        }
        // Preparing select checkboxes.
        $disabledcheckboxattrs = [
            'title' => get_string('videostablemassaction_disabled_item', 'block_opencast'),
            'disabled' => 'disabled',
        ];
        // A default disabled checkbox.
        $checkboxhtml = html_writer::checkbox(
            self::CHECKBOX_SELECTITEM_DISABLED_CLASSNAME,
            $video->identifier,
            false,
            '',
            $disabledcheckboxattrs
        );
        // When the row is selectable, then we provide the toggle checkbox.
        if ($isselectable) {
            $selectableattributes = [
                'classes' => self::CHECKBOX_SELECTITEM_CLASSNAME,
                'id' => 'ocvideo' . $video->identifier,
                'name' => 'ocvideo' . $video->title,
                'checked' => false,
                // For behat tests to pickup the checkbox easier, we provide the title as well.
                'label' => get_string('select') . ' ' . $video->title,
                // We need the classes specially for behat test to pickup the checkbox.
                'labelclasses' => 'form-check-label d-block pe-2 sr-only',
            ];
            $checkbox = new \core\output\checkbox_toggleall(self::TOGGLE_GROUP_NAME, false, $selectableattributes);
            $checkboxhtml = $OUTPUT->render($checkbox);
        }

        return $checkboxhtml;
    }

    /**
     * Renders the mass action select dropdown for the video table.
     *
     * This function generates a dropdown menu for selecting mass actions to be performed on the video table.
     * The dropdown menu is populated with enabled mass actions and is disabled when no mass actions are available.
     *
     * @param string $id The unique identifier for the dropdown menu.
     *
     * @return string The HTML markup for the mass action select dropdown. If no mass actions are available,
     * an empty string is returned.
     */
    public function render_table_mass_actions_select(string $id) {
        if (!$this->has_massactions()) {
            return '';
        }
        // Bulk actions.
        $html = html_writer::start_div('py-3 px-2 mt-2 mb-2');
        $html .= html_writer::label(
            get_string('videostablemassaction_label', 'block_opencast'),
            $id,
            false,
            ['class' => 'mr-3'],
        );

        $enabledmassactions = array_filter($this->massactions, function ($item) {
            return !empty($item['enable']);
        });

        $massactionselectitems = [];
        foreach (array_keys($enabledmassactions) as $makey) {
            $massactionselectitems[$makey] = get_string('videostable_massaction_' . $makey, 'block_opencast');
        }

        // Actions mapping hidden input.
        if (!$this->mappingisrendered) {
            $hiddenactionsmappingattrs = [
                'type' => 'hidden',
                'id' => self::HIDDEN_INPUT_ACTIONS_MAPPING_ID,
                'value' => json_encode($enabledmassactions),
            ];
            $html .= html_writer::empty_tag('input', $hiddenactionsmappingattrs);
            $this->mappingisrendered = true;
        }

        $withselectedparams = [
            'id' => $id,
            'data-action' => 'toggle',
            'data-togglegroup' => self::TOGGLE_GROUP_NAME,
            'data-toggle' => 'action',
            'disabled' => true,
            'class' => self::SELECT_DROPDOWN_CLASSNAME,
        ];
        $html .= html_writer::select($massactionselectitems, $id, '', ['' => 'choosedots'], $withselectedparams);

        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Adds a new mass action to the list of available mass actions.
     *
     * @param string $name The unique name of the mass action.
     * @param string $url The URL of the mass action handler.
     * @param bool $enable Whether the mass action is enabled or not. Default is true.
     *
     * @return bool Returns true if the mass action was successfully added, false otherwise.
     */
    public function add_massaction($name, $url, $enable = true) {
        if (!isset($this->massactions[$name])) {
            $item = [
                'path' => [
                    'url' => $url,
                ],
                'enable' => $enable,
            ];
            $this->massactions[$name] = $item;
            return true;
        }
        return false;
    }

    /**
     * Enables or disables a specific mass action.
     *
     * This function allows you to enable or disable a mass action based on its unique name.
     * If the mass action exists in the list of available mass actions, its 'enable' status will be updated.
     *
     * @param string $item The unique name of the mass action.
     * @param bool $enable Whether the mass action should be enabled (true) or disabled (false). Default is true.
     *
     */
    public function massaction_action_activation($item, $enable = true) {
        if (isset($this->massactions[$item])) {
            $this->massactions[$item]['enable'] = $enable;
        }
    }



    /**
     * Checks if there are any enabled mass actions available and if the feature is activated.
     *
     * This function filters the mass actions to get only the enabled ones and checks if there are any.
     * It also verifies if the feature is still activated.
     *
     * @return bool Returns true if there are enabled mass actions available and the feature is activated,
     * false otherwise.
     */
    public function has_massactions() {
        // Filter the mass actions to get only the enabled ones.
        $enabledmassactions = array_filter($this->massactions, function ($item) {
            return !empty($item['enable']);
        });

        // Check if there are enabled mass actions and if the feature is still activated.
        return count($enabledmassactions) > 0 && $this->isactivated;
    }

    /**
     * Validates the mass actions configuration.
     *
     * This function checks if the mass actions configuration is valid.
     * It ensures that each mass action has an 'enable' status, a 'path' array,
     * and the 'path' array contains a non-empty 'url'.
     *
     * @return bool Returns true if the mass actions configuration is valid, false otherwise.
     */
    private function validate_massactions() {
        $isvalid = true;
        foreach ($this->massactions as $action) {
            if (!isset($action['enable']) || !isset($action['path']) ||
                (isset($action['path']) && (!isset($action['path']['url']) || empty($action['path']['url'])))) {
                $isvalid = false;
                break;
            }
        }
        return $isvalid;
    }

    /**
     * Activates or deactivates the mass action feature.
     *
     * This function allows you to enable or disable the mass action feature.
     * When the feature is activated, the mass actions will be available for use.
     * When the feature is deactivated, the mass actions will not be available.
     *
     * @param bool $activate Whether to activate (true) or deactivate (false) the mass action feature.
     *                       The default value is true, meaning the feature will be activated if no value is provided.
     *
     * @return void This function does not return any value.
     */
    public function activate_massaction(bool $activate = true) {
        $this->isactivated = $activate;
    }

    /**
     * Sets a parameter for the URL of a specific mass action.
     *
     * This function allows you to add or update a parameter in the URL of a specific mass action.
     * The parameter will be appended to the 'path' array of the mass action.
     *
     * @param string $actionname The unique name of the mass action.
     * @param string $paramkey The key of the parameter to be set.
     * @param string $paramvalue The value of the parameter to be set.
     *
     * @throws coding_exception If the provided actionname, paramkey, or paramvalue is invalid.
     *
     * @return void This function does not return any value.
     */
    public function set_action_path_parameter(string $actionname, string $paramkey, string $paramvalue) {
        if (!isset($this->massactions[$actionname]) || empty($paramkey) || empty($paramvalue)) {
            throw new coding_exception('massaction_invalidactionparam', 'block_opencast');
        }
        $this->massactions[$actionname]['path']['params'][$paramkey] = $paramvalue;
    }

    /**
     * Removes a parameter from the URL of a specific mass action.
     *
     * This function allows you to remove a parameter from the URL of a specific mass action.
     * The parameter will be removed from the 'path' array of the mass action.
     *
     * @param string $actionname The unique name of the mass action.
     * @param string $paramkey The key of the parameter to be removed.
     *
     * @return bool Returns true if the parameter was successfully removed, false otherwise.
     *              If the provided actionname or paramkey is invalid, the function will return false.
     */
    public function remove_action_path_parameter(string $actionname, string $paramkey) {
        if (isset($this->massactions[$actionname]) && !empty($paramkey) &&
            isset($this->massactions[$actionname]['path']['params'][$paramkey])) {
            unset($this->massactions[$actionname]['path']['params'][$paramkey]);
            return true;
        }

        return false;
    }

    /**
     * Retrieves the JavaScript selectors used in the mass action helper class.
     *
     * @return array An associative array containing the JavaScript selectors.
     *               The keys represent the selector names, and the values represent the corresponding CSS selectors.
     *               The selectors include:
     *               - 'dropdown': The CSS selector for the mass action dropdown menu.
     *               - 'selectall': The CSS selector for the master checkbox for selecting all video items.
     *               - 'selectitem': The CSS selector for the checkboxes for selecting individual video items.
     *               - 'actionmapping': The CSS selector for the hidden input containing the mapping of mass actions.
     */
    public static function get_js_selectors() {
        return [
            'dropdown' => '.'. self::SELECT_DROPDOWN_CLASSNAME,
            'selectall' => 'input.'. self::CHECKBOX_SELECTALL_CLASSNAME,
            'selectitem' => 'input.'. self::CHECKBOX_SELECTITEM_CLASSNAME,
            'actionmapping' => self::HIDDEN_INPUT_ACTIONS_MAPPING_ID,
        ];
    }
}

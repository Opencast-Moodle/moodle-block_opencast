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


namespace block_opencast;

defined('MOODLE_INTERNAL') || die();


/**
 * Admin settings class for workflow select.
 *
 * Just so we can lazy-load the choices.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_workflowselect extends \admin_setting_configselect{
    protected $callopt=null;
     /**
     * Constructor.
     *
     * If you want to lazy-load the choices, pass a callback function that returns a choice
     * array for the $choices parameter.
     *
     * @param string $name unique ascii name, either 'mysetting' for settings that in config, or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string|int $defaultsetting
     * @param mixed $choices array of $value=>$label for each selection, or callback
     */
    public function __construct($name, $visiblename, $description, $defaultsetting, $choices,$callopt) {

            $this->callopt = $callopt;
        

        parent::__construct($name, $visiblename, $description, $defaultsetting,$choices);
    }

    public function load_choices() {

        if (is_array($this->choices)) {
            return true;
        }
        
        $this->choices = setting_helper::load_workflow_choices($this->callopt[0],$this->callopt[1]);

        return true;
    }
}

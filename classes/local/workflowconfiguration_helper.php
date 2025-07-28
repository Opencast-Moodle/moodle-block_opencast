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
 * Workflow configuration helper.
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use html_writer;

/**
 * Workflow configuration Helper.
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workflowconfiguration_helper {

    /** @var string The upload workflow mapping hidden input id. */
    const MAPPING_INPUT_HIDDEN_ID = 'configpanelmapping';

    /** @var string A suffix to add to the element ids to avoid conflicts. */
    const CONFIG_PANEL_ELEMENT_SUFFIX = '_moodle_form_config_panel';

    /** @var ?workflowconfiguration_helper the static instance of the class. */
    private static $instance = null;

    /** @var int The opencast instance id. */
    private $ocinstanceid;

    /** @var stdClass the upload workflow object. */
    private $uploadworkflow;

    /** @var string The upload workflow id. */
    private $uploadworkflowid;

    /**
     * The construct method for this class.
     *
     * @param int $ocinstanceid the opencast instance id.
     */
    public function __construct(int $ocinstanceid) {
        $this->ocinstanceid = $ocinstanceid;
        $this->set_uploadworkflowid();
        $this->set_uploadworkflow();
    }

    /**
     * Get the singleton instance of this class.
     *
     * @param int $ocinstanceid the opencast instance id.
     *
     * @return workflowconfiguration_helper an instance of the class.
     */
    public static function get_instance(int $ocinstanceid): workflowconfiguration_helper {
        if (is_null(self::$instance)) {
            self::$instance = new workflowconfiguration_helper($ocinstanceid);
        }
        return self::$instance;
    }

    /**
     * Sets the upload workflow id from the config, falls back to "ng-schedule-and-upload" if not configured yet.
     */
    private function set_uploadworkflowid() {
        $uploadworkflowid = get_config('block_opencast', 'uploadworkflow_' . $this->ocinstanceid);
        // Falling back to the general "ng-schedule-and-upload" workflow.
        if (empty($uploadworkflowid)) {
            $uploadworkflowid = 'ng-schedule-and-upload';
        }
        $this->uploadworkflowid = $uploadworkflowid;
    }

    /**
     * Sets the upload workflow object for the current instance.
     *
     * This method retrieves the workflow definition for the configured upload workflow ID
     * and stores it in the class property. If the workflow contains a configuration panel in JSON format,
     * it is converted to HTML and attached to the workflow object for compatibility.
     *
     * @see self::get_filtered_workflow_definition
     *
     * @return void
     */
    private function set_uploadworkflow() {
        $this->uploadworkflow = self::get_filtered_workflow_definition($this->ocinstanceid, $this->uploadworkflowid);
    }

    /**
     * Converts the workflow configuration panel JSON to an HTML representation.
     *
     * This method parses the configuration panel JSON from the workflow definition,
     * and generates the corresponding HTML form elements (such as input, select, checkbox)
     * wrapped in fieldsets and divs, preserving labels and options as defined in the JSON.
     * The resulting HTML can be used to render a dynamic configuration panel for the workflow.
     *
     * @param \stdClass $workflowobj The workflow object containing the configuration_panel_json property.
     * @return string|null The generated HTML string for the configuration panel, or null if JSON is empty or invalid.
     */
    private static function convert_config_panel_json_to_html(\stdClass $workflowobj): ?string {

        $configpaneljson = json_decode(trim($workflowobj->configuration_panel_json), true);
        if (empty($configpaneljson)) {
            return null;
        }

        $workflowid = $workflowobj->identifier;

        // Initialize the dom and xpath instances.
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->strictErrorChecking = true;
        $mainfieldset = $dom->createElement('fieldset');

        foreach ($configpaneljson as $index => $configpanelitem) {
            $uniqueidprefix = $workflowid . '_' . $index;

            // We have to have fieldset to be able to generate the elements.
            if (!isset($configpanelitem['fieldset'])) {
                continue;
            }

            $fielddiv = $dom->createElement('div');
            $fielddiv->setAttribute('id', "{$uniqueidprefix}_field_div");

            $shouldhavegroupdiv = count($configpanelitem['fieldset']) > 1;
            if (!empty($configpanelitem['description'])) {
                $fieldlabel = $dom->createElement('label');
                $fieldlabel->textContent = trim($configpanelitem['description']);
                $fielddiv->appendChild($fieldlabel);
                $shouldhavegroupdiv = true;
            }

            $groupdiv = $shouldhavegroupdiv ? $dom->createElement('div') : $fielddiv;
            if (!$groupdiv->hasAttribute('id')) {
                $groupdiv->setAttribute('id', "{$uniqueidprefix}_group_div");
            }
            foreach ($configpanelitem['fieldset'] as $key => $field) {
                $element = $field['type'] === 'select' ? 'select' : 'input';
                $inputdom = $dom->createElement($element);
                if (empty($field['id'])) {
                    $field['id'] = $field['name'];
                }

                $children = [];
                if (!empty($field['label'])) {
                    $fielduniquelabel = $dom->createElement('label');
                    $fielduniquelabel->textContent = trim($field['label']);
                    $fielduniquelabel->setAttribute('for', $field['id']);
                    $children[] = $fielduniquelabel;
                    unset($field['label']);
                }

                if ($element === 'select' && !empty($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $option) {
                        $optionelement = $dom->createElement('option', (string) ($option['text'] ?? ''));
                        $optionelement->setAttribute('value', (string) ($option['value'] ?? ''));
                        if (isset($option['selected']) && $option['selected']) {
                            $optionelement->setAttribute('selected', 'selected');
                        }
                        $inputdom->appendChild($optionelement);
                    }
                    unset($field['options']);
                }

                if ($field['type'] === 'checkbox') {
                    $value = $field['value'] ?? false;
                    if ($value) {
                        $inputdom->setAttribute('checked', 'checked');
                    }
                    $field['value'] = $value ? 'true' : 'false';
                }

                foreach ($field as $fieldattr => $attrvalue) {
                    $inputdom->setAttribute($fieldattr, trim((string) $attrvalue));
                }

                $children[] = $inputdom;

                // In case of checkbox, we reverse the order.
                if ($field['type'] === 'checkbox') {
                    $children = array_reverse($children);
                }

                foreach ($children as $child) {
                    $groupdiv->appendChild($child);
                }
            }

            if ($groupdiv->getAttribute('id') !== $fielddiv->getAttribute('id')) {
                $fielddiv->appendChild($groupdiv);
                $mainfieldset->appendChild($fielddiv);
            } else {
                $mainfieldset->appendChild($groupdiv);
            }
        }

        $maindiv = $dom->createElement('div');
        $maindiv->setAttribute('id', 'workflow-configuration');
        $maindiv->appendChild($mainfieldset);

        return $dom->saveHTML($maindiv) ?? null;

    }

    /**
     * Detemines whether all requirements to provide the upload configuration panel to the teachers in upload page are met.
     *
     * @return boolean whether or the upload configuration panel cpoiuld be provided to the users.
     */
    public function can_provide_configuration_panel(): bool {
        return !empty($this->uploadworkflow) &&
            (!empty($this->uploadworkflow->configuration_panel) || !empty($this->uploadworkflow->configuration_panel_json)) &&
            !empty(get_config('block_opencast', 'enableuploadwfconfigpanel_' . $this->ocinstanceid));
    }

    /**
     * Compiles and convert the user defined configuration panel data received after the upload forms are submitted.
     *
     * @param stdClass $formdata the form data object recieved after form submittion.
     *
     * @return array the user defined configuration panel data
     */
    public function get_userdefined_configuration_data(\stdClass $formdata): array {
        $configpaneldata = [];
        if ($this->can_provide_configuration_panel() && property_exists($formdata, self::MAPPING_INPUT_HIDDEN_ID)) {
            $configpanelmapping = json_decode($formdata->{self::MAPPING_INPUT_HIDDEN_ID}, true);
            foreach ($configpanelmapping as $cpid => $mappingtype) {
                $isboolean = $mappingtype === 'boolean';
                $cpidformatted = str_replace(self::CONFIG_PANEL_ELEMENT_SUFFIX, '', $cpid);
                if (property_exists($formdata, $cpid)) {
                    $value = $formdata->$cpid;
                    if ($isboolean) {
                        $value = boolval($value);
                        $value = !empty($value) ? 'true' : 'false';
                    }
                    if ($mappingtype === 'date') {
                        $value = intval($value);
                        $dobj = new \DateTime("now", new \DateTimeZone("UTC"));
                        $dobj->setTimestamp(intval($value));
                        $value = $dobj->format('Y-m-d\TH:i:s\Z');
                    }
                    $configpaneldata[$cpidformatted] = $value;
                } else if ($isboolean) {
                    $configpaneldata[$cpidformatted] = 'false';
                }
            }
        }
        return $configpaneldata;
    }

    /**
     * Read the configuration and applies the comma separation mechanism to return the string to array of alloed config ids.
     *
     * @return array the list of allowed config panel elements ids.
     */
    public function get_allowed_upload_configurations(): array {
        $alloweduploadwfconfigs = get_config('block_opencast', 'alloweduploadwfconfigs_' . $this->ocinstanceid);
        $alloweduploadwfconfigids = [];
        if (!empty(trim($alloweduploadwfconfigs))) {
            $alloweduploadwfconfigids = explode(',', $alloweduploadwfconfigs);
            $alloweduploadwfconfigids = array_map('trim', $alloweduploadwfconfigids);
        }
        return $alloweduploadwfconfigids;
    }

    /**
     * Get the upload workflow configuration panel
     * It gets the HTML (in configuration_panel) as 1. priority or the JSON (in configuration_panel_json) otherwise!
     *
     * @return string | null the configuration panel or null if not available.
     */
    public function get_upload_workflow_configuration_panel(): ?string {
        $configpanel = null;
        if (!empty($this->uploadworkflow)) {
            if (!empty($this->uploadworkflow->configuration_panel)) {
                $configpanel = (string) $this->uploadworkflow->configuration_panel;
            } else if (!empty($this->uploadworkflow->configuration_panel_json)) {
                $configpanel = (string) $this->uploadworkflow->configuration_panel_json_html;
            }
        }
        return $configpanel;
    }

    /**
     * Gets the workflow processing data to pass to the event creation calls (api or ingest).
     *
     * @param ?string $jobworkflowconfiguration the workflow configuration stored in the uploadjob or null if not defined.
     *
     * @return array the workflow processing data to pass to the event creation calls. It contains most usable output such as:
     * - workflow: the upload workflow
     * - processing: the array processing
     * - processing_json: the json encoded string of processing array
     * - configuration_json: the json encoded string of configuration array
     * - configuration: the array list of all workflow configuration (defaults and user defineds).
     */
    public function get_workflow_processing_data(?string $jobworkflowconfiguration = null): array {

        $processing = [];
        $processing['workflow'] = $this->uploadworkflowid;

        // Default workflow configurations.
        $processing['configuration'] = [
            "flagForCutting" => "false",
            "flagForReview" => "false",
            "publishToHarvesting" => "false",
            "straightToPublishing" => "true",
        ];

        // Take care of engane publishing.
        $publistoengage = get_config('block_opencast', 'publishtoengage_' . $this->ocinstanceid);
        $publistoengage = (empty($publistoengage)) ? "false" : "true";

        $processing['configuration']['publishToEngage'] = $publistoengage;

        if ($this->can_provide_configuration_panel() && !empty($jobworkflowconfiguration)) {
            $alloweduploadwfconfigids = $this->get_allowed_upload_configurations();

            $workflowconfigurationarr = json_decode($jobworkflowconfiguration, true);
            foreach ($workflowconfigurationarr as $configid => $value) {
                if (!empty($alloweduploadwfconfigids) && !in_array($configid, $alloweduploadwfconfigids)) {
                    continue;
                }
                $processing['configuration'][$configid] = (string) $value;
            }
        }

        $result = [
            'workflow' => $this->uploadworkflowid,
            'processing' => $processing,
            'processing_json' => json_encode($processing),
            'configuration_json' => json_encode($processing['configuration']),
            'configuration' => $processing['configuration'],
        ];

        return $result;
    }

    /**
     * Retrieves and prepares a workflow definition for a given Opencast instance and workflow ID.
     *
     * This static method is provided for internal as well as external usage, allowing other classes or code to fetch
     * a workflow definition without needing an instance of workflowconfiguration_helper.
     * It fetches the workflow definition from the Opencast API, and if a configuration panel in JSON format exists,
     * converts it to HTML and attaches it to the workflow object for compatibility with both HTML and JSON panels.
     *
     * @param int $ocinstanceid The Opencast instance ID.
     * @param string $workflowid The workflow ID to retrieve.
     * @return \stdClass|null The workflow definition object with JSON to HTML conversion attached, or null if not found.
     */
    public static function get_filtered_workflow_definition(int $ocinstanceid, string $workflowid): ?\stdClass {
        $apibridge = apibridge::get_instance($ocinstanceid);
        $workflowdefinition = $apibridge->get_workflow_definition($workflowid);
        if (!$workflowdefinition) {
            return null;
        }
        $jsonhtmlconverted = null;
        // If the workflow contains a configuration panel in JSON format,
        // convert it to HTML and attach it to the workflow definition object.
        // This approach maintains compatibility with both configuration_panel (HTML)
        // and configuration_panel_json (JSON) mechanisms.
        if (!empty($workflowdefinition->configuration_panel_json)) {
            $jsonhtmlconverted = self::convert_config_panel_json_to_html($workflowdefinition);
        }
        $workflowdefinition->configuration_panel_json_html = $jsonhtmlconverted;
        return $workflowdefinition;
    }
}

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
 * Manual import videos form (Step 3: LTI module handling).
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Manual import videos form (Step 3: LTI module handling).
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importvideos_step3_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $PAGE;

        // Define mform.
        $mform = $this->_form;
        $ocinstanceid = $this->_customdata['ocinstanceid'];

        // Get renderer.
        $renderer = $PAGE->get_renderer('block_opencast', 'importvideos');

        // Add hidden fields for transferring the wizard results and for wizard step processing.
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'ocinstanceid', $this->_customdata['ocinstanceid']);
        $mform->setType('ocinstanceid', PARAM_INT);
        $mform->addElement('hidden', 'step', 3);
        $mform->setType('step', PARAM_INT);
        $mform->addElement('hidden', 'sourcecourseid', $this->_customdata['sourcecourseid']);
        $mform->setType('sourcecourseid', PARAM_INT);
        foreach ($this->_customdata['coursevideos'] as $identifier => $checked) {
            $mform->addElement('hidden', 'coursevideos[' . $identifier . ']', $checked);
            $mform->setType('coursevideos', PARAM_BOOL);
        }

        // Get course context.
        $coursecontext = \context_course::instance($this->_customdata['courseid']);

        $handleseriesmodules = false;
        $referencedseriesmodules = array();

        // Check if the handle series feature is enabled and apibridge is working.
        if ((\block_opencast\local\importvideosmanager::handle_series_modules_is_enabled_and_working($ocinstanceid) == true)) {

            // Check if LTI handling is enabled and the user is allowed to use the feature.
            if (\block_opencast\local\ltimodulemanager::is_working_for_series($ocinstanceid) &&
                has_capability('block/opencast:addlti', $coursecontext)) {
                $handleseriesmodules = true;
                // Get Opencast LTI series modules in this course which point to the source course's series.
                $referencedseriesmodules = ltimodulemanager::get_modules_for_series_linking_to_other_course($ocinstanceid,
                    $this->_customdata['courseid'], $this->_customdata['sourcecourseid']);
            }

            // Check if mod_opencast is installed for handling activities.
            if (\core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null) {
                $handleseriesmodules = true;
                $referencedseriesmodules += activitymodulemanager::get_modules_for_series_linking_to_other_course($ocinstanceid,
                    $this->_customdata['courseid'], $this->_customdata['sourcecourseid']);
            }
        }

        $handleepisodemodules = false;
        $referencedepisodemodules = array();
        // Check if the handle episode feature is enabled _and_ the user is allowed to use the feature.
        if ((\block_opencast\local\importvideosmanager::handle_episode_modules_is_enabled_and_working($ocinstanceid) == true)) {

            // Check if LTI handling is enabled and the user is allowed to use the feature.
            if (\block_opencast\local\ltimodulemanager::is_working_for_episodes($ocinstanceid) &&
                has_capability('block/opencast:addltiepisode', $coursecontext)) {
                $handleepisodemodules = true;

                // Get Opencast LTI episode modules in this course which point to a video in the source course's series.
                $referencedepisodemodules = ltimodulemanager::get_modules_for_episodes_linking_to_other_course(
                    $ocinstanceid,
                    $this->_customdata['courseid'], $this->_customdata['sourcecourseid'],
                    array_keys($this->_customdata['coursevideos']));
            }

            // Check if mod_opencast is installed for handling activities.
            if (\core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null) {
                $handleepisodemodules = true;
                $referencedepisodemodules += activitymodulemanager::get_modules_for_episodes_linking_to_other_course(
                    $ocinstanceid, $this->_customdata['courseid'], $this->_customdata['sourcecourseid']);
            }
        }

        // If there is anything to be handled.
        if (($handleseriesmodules == true && count($referencedseriesmodules) > 0) ||
            ($handleepisodemodules == true && count($referencedepisodemodules) > 0)) {
            // Add intro.
            $notification = $renderer->wizard_intro_notification(
                get_string('importvideos_wizardstep3intro', 'block_opencast'));
            $mform->addElement('html', $notification);

            // If there is any series module which needs to be handled.
            if ($handleseriesmodules == true && count($referencedseriesmodules) > 0) {
                // Show heading for series module.
                $handleseriesheadingstring = \html_writer::tag('h3',
                    get_string('importvideos_wizardstep3seriesmodulesubheading', 'block_opencast'));
                $mform->addElement('html', $handleseriesheadingstring);

                // Show explanation for series module.
                $handleseriesmodulestring = \html_writer::tag('p',
                    get_string('importvideos_wizardstep3seriesmoduleexplanation', 'block_opencast'));
                $mform->addElement('html', $handleseriesmodulestring);

                // Add checkbox to fix series module.
                $handleseriesmodulelabel = get_string('importvideos_wizardstep3seriesmodulelabel', 'block_opencast');
                $mform->addElement('checkbox', 'fixseriesmodules', $handleseriesmodulelabel);
                $mform->setDefault('fixseriesmodules', 1);
            }

            // If there is any episode module which needs to be handled.
            if ($handleepisodemodules == true && count($referencedepisodemodules) > 0) {
                // Show heading for episode module.
                $handleepisodeheadingstring = \html_writer::tag('h3',
                    get_string('importvideos_wizardstep3episodemodulesubheading', 'block_opencast'));
                $mform->addElement('html', $handleepisodeheadingstring);

                // Show explanation for episode module.
                $handleepisodemodulestring = \html_writer::tag('p',
                    get_string('importvideos_wizardstep3episodemoduleexplanation', 'block_opencast'));
                $mform->addElement('html', $handleepisodemodulestring);

                // Add checkbox to fix series module.
                $handleepisodemodulelabel = get_string('importvideos_wizardstep3episodemodulelabel', 'block_opencast');
                $mform->addElement('checkbox', 'fixepisodemodules', $handleepisodemodulelabel);
                $mform->setDefault('fixepisodemodules', 1);
            }

            // Otherwise.
        } else {
            // Add intro.
            $notification = $renderer->wizard_intro_notification(
                get_string('importvideos_wizardstep3skipintro', 'block_opencast'));
            $mform->addElement('html', $notification);
        }

        // Add action buttons.
        $this->add_action_buttons(true, get_string('importvideos_wizardstepbuttontitlecontinue', 'block_opencast'));
    }
}

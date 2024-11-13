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
 * Javascript module to instantiate the Mass-Action functionality.
 *
 * @module     block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni (elan e.V.) (zamani@elan-ev.de)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import * as str from 'core/str';
import Notification from 'core/notification';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import url from 'core/url';

/**
 * Initializes the mass action functionality for the Opencast block.
 * This function sets up event listeners for dropdown changes, handles video selection,
 * and manages the modal dialogs for different actions.
 *
 * @param {number} courseid - The ID of the current course.
 * @param {number} ocinstanceid - The ID of the Opencast instance.
 * @param {Object} selectors - An object containing CSS/Id selectors for various elements.
 * @param {string} selectors.dropdown - Selector for the action dropdown elements.
 * @param {string} selectors.selectitem - Selector for the checkbox elements to select individual videos.
 * @param {string} selectors.actionmapping - Selector for the element containing action mapping data.
 * @param {string} selectors.selectall - Selector for the "select all" checkbox.
 * @returns {void} This function does not return a value.
 */
export const init = (courseid, ocinstanceid, selectors) => {

    const dropdowns = [...document.querySelectorAll(selectors.dropdown)];
    dropdowns.map(dropdown => {
        dropdown.addEventListener('change', e => {
            const element = e.currentTarget;
            const id = element.getAttribute('id');
            const action = element.value;

            // Make sure other bulk select get the same value.
            const populatedselector = `${selectors.dropdown}:not(#${id})`;
            const otherdropdowns = [...document.querySelectorAll(populatedselector)];
            if (otherdropdowns.length) {
                otherdropdowns.map(otherdropdown => otherdropdown.value = action)
            }

            if (action === '') {
                return;
            }

            const selectedvideos = [...document.querySelectorAll(`${selectors.selectitem}:checked`)];
            if (!selectedvideos.length) {
                return;
            }
            const selectedids = selectedvideos.map(element => element.id.substring(7));
            const selectedtitles = selectedvideos.map(element => element.name.substring(7));

            const actionsmappinginput = document.getElementById(selectors.actionmapping);
            const actionsmappingraw = actionsmappinginput ? actionsmappinginput.value : null;
            if (actionsmappingraw === null) {
                return;
            }
            const actionsmapping = JSON.parse(actionsmappingraw);
            // Make sure that the action url is there.
            if (!actionsmapping?.[action]?.path?.url) {
                console.warn('Unable to read the mass action url.');
                return;
            }

            // Because of using Modal for start workflow tasks, we don't provide a confirmation modal beforehand,
            // but instead we provide the confirmation texts in existing startworkflow modal.
            if (action === 'startworkflow') {

                const data = {
                    type: 'bulk',
                    selectedids: selectedids,
                    selectedtitles: selectedtitles,
                    url: actionsmapping[action].path.url
                };

                // Create and dispatch the custom event on start-workflow element with detail data.
                const event = new CustomEvent('click', { detail: data });
                document.querySelector('.start-workflow').dispatchEvent(event);
                return; // We stop the function here!
            }

            const stringskeys = [
                {
                    key: 'videostable_massaction_' + action + '_modal_title',
                    component: 'block_opencast'
                },
                {
                    key: 'videostable_massaction_' + action + '_modal_body',
                    component: 'block_opencast',
                    param: selectedtitles.join('</li><li>')
                },
                {
                    key: 'videostable_massaction_' + action,
                    component: 'block_opencast'
                },
            ];
            const strPromise = str.get_strings(stringskeys);

            const modalPromise = ModalSaveCancel.create({});

            var urlParams = {
                'ocinstanceid': ocinstanceid,
                'courseid': courseid
            };

            if (actionsmapping[action].path?.params) {
                urlParams = Object.assign(urlParams, actionsmapping[action].path.params);
            }

            const actionUrl = url.relativeUrl(actionsmapping[action].path.url, urlParams);

            $.when(strPromise, modalPromise).then(function(strings, modal) {
                // Pause the live update if it is running.
                window.liveUpdateState = 'paused';
                modal.setTitle(strings[0]);
                var body = '<form id="mass_action_confirmation_form" method="post" action="' + actionUrl + '">';
                body += '<p>' + strings[1] + '</p>';
                for (let selectedid of selectedids) {
                    body += '<input type="hidden" name="videoids[]" value="' + selectedid + '">';
                }
                body += '<input type="hidden" name="ismassaction" value="1">';
                body += '</form>';
                modal.setBody(body);
                modal.setSaveButtonText(strings[2]);
                modal.getRoot().on(ModalEvents.save, function() {
                    // Resume the live update if it was paused.
                    window.liveUpdateState = 'resumed';
                    document.getElementById('mass_action_confirmation_form').submit();
                });
                modal.getRoot().on(ModalEvents.hidden, function() {
                    // Resume the live update if it was paused.
                    window.liveUpdateState = 'resumed';
                    // Destroy when hidden/closed.
                    modal.destroy();
                    // Change the bulk action select back to choose...
                    resetVideosTableBulkActions(selectors);
                });
                modal.show();
                return modal;
            }).fail(Notification.exception);

        });
    });
}

/**
 * Resets the bulk action select dropdowns and unchecks the select items.
 * This function is called when the modal is hidden/closed.
 *
 * @param {Object} selectors - An object containing CSS/Id selectors for various elements.
 * @param {string} selectors.dropdown - Selector for the action dropdown elements.
 * @param {string} selectors.selectitem - Selector for the checkbox elements to select individual videos.
 * @param {string} selectors.actionmapping - Selector for the element containing action mapping data.
 * @param {string} selectors.selectall - Selector for the "select all" checkbox.
 * @returns {void} This function does not return a value.
 */
const resetVideosTableBulkActions = (selectors) => {
    const dropdowns = [...document.querySelectorAll(selectors.dropdown)];
    dropdowns.map(dropdown => {
        dropdown.value = '';
        dropdown.setAttribute('disabled', true);
    });

    const ckinputs = [...document.querySelectorAll(`${selectors.selectall}, ${selectors.selectitem}`)];
    ckinputs.map(input => {
        input.checked = false;
    });
};

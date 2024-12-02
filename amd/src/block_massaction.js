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
 * @param {string} selectors.container - Selector for the table wrapper container div,
 *                                      which should also contains the massaction dropdowns.
 * @returns {void} This function does not return a value.
 */
export const init = (courseid, ocinstanceid, selectors) => {

    // Fix toggle group data for mass action elements.
    fixToggleGroups(selectors);

    // Loop through dropdowns.
    const dropdowns = [...document.querySelectorAll(selectors.dropdown)];
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('change', e => {
            const element = e.currentTarget;
            const id = element.getAttribute('id');
            const action = element.value;

            // Make sure other bulk select get the same value.
            const parentcontainer = element.closest(selectors.container);
            let container = document;
            if (parentcontainer) {
                container = parentcontainer;
            }
            const populatedselector = `${selectors.dropdown}:not(#${id})`;
            const otherdropdowns = [...container.querySelectorAll(populatedselector)];
            if (otherdropdowns.length) {
                otherdropdowns.forEach(otherdropdown => {
                    otherdropdown.value = action;
                });
            }

            if (action === '') {
                return;
            }

            const selectedvideos = [...container.querySelectorAll(`${selectors.selectitem}:checked`)];
            if (!selectedvideos.length) {
                return;
            }
            const selectedids = selectedvideos.map(element => element.id.substring(7));
            const selectedtitles = selectedvideos.map(element => element.name.substring(7));

            const actionsmappinginput = container.querySelector(`input[name=${selectors.actionmapping}]`);
            const actionsmappingraw = actionsmappinginput ? actionsmappinginput.value : null;
            if (actionsmappingraw === null) {
                return;
            }
            const actionsmapping = JSON.parse(actionsmappingraw);
            // Make sure that the action url is there.
            if (!actionsmapping?.[action]?.path?.url) {
                return;
            }

            // Because of using Modal for start workflow tasks, we don't provide a confirmation modal beforehand,
            // but instead we provide the confirmation texts in existing startworkflow modal.
            if (action === 'startworkflow') {

                const data = {
                    type: 'bulk',
                    selectedids: selectedids,
                    selectedtitles: selectedtitles,
                    url: actionsmapping[action].path.url,
                    container: container
                };

                // Create and dispatch the custom event on start-workflow element with detail data.
                const event = new CustomEvent('click', {detail: data});
                container.querySelector('.start-workflow').dispatchEvent(event);
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
                    resetVideosTableBulkActions(selectors, container);
                });
                modal.show();
                return modal;
            }).fail(Notification.exception);
        });
    });
};

/**
 * Resets the bulk action select dropdowns.
 * This function is called when the modal is hidden/closed.
 *
 * @param {Object} selectors - An object containing CSS/Id selectors for various elements.
 * @param {string} selectors.dropdown - Selector for the action dropdown elements.
 * @param {string} selectors.selectitem - Selector for the checkbox elements to select individual videos.
 * @param {string} selectors.actionmapping - Selector for the element containing action mapping data.
 * @param {string} selectors.selectall - Selector for the "select all" checkbox.
 * @param {Object} container - The container element as the parent element.
 * @param {boolean} disabled a flag to set the dropdown attribute upon using the function (default to false).
 * @returns {void} This function does not return a value.
 */
const resetVideosTableBulkActions = (selectors, container, disabled = false) => {
    const dropdowns = [...container.querySelectorAll(selectors.dropdown)];
    dropdowns.forEach(dropdown => {
        dropdown.value = '';
        dropdown.disabled = disabled;
    });
};

/**
 * Fixes the toggle groups for the selections.
 * The main reason to do this here is to make sure that mass action feature works when multiple tables are in a page.
 *
 * This function looks for the table wrapper div container and takes its child table id and inject the id as tooglegroup data to
 * the its child elements such as dropdowns select-all and select-single checkboxes.
 *
 * @param {Object} selectors - An object containing CSS/Id selectors for various elements.
 * @param {string} selectors.dropdown - Selector for the action dropdown elements.
 * @param {string} selectors.selectitem - Selector for the checkbox elements to select individual videos.
 * @param {string} selectors.actionmapping - Selector for the element containing action mapping data.
 * @param {string} selectors.selectall - Selector for the "select all" checkbox.
 * @returns {void} This function does not return a value.
 */
const fixToggleGroups = (selectors) => {
    const containers = [...document.querySelectorAll(selectors.container)];
    containers.forEach(container => {
        // Take the table.
        const table = container.querySelector('table.opencast-videos-table');
        // Extract the table id.
        const tableid = table?.id;
        if (!tableid) {
            // Do nothing if no table id found to avoid misleading errors.
            return;
        }

        // Find the dropdown children and adjust their togglegroup data.
        const dropdowns = [...container.querySelectorAll(selectors.dropdown)];
        dropdowns.forEach(dropdown => {
            dropdown.dataset.togglegroup = tableid;
            dropdown.setAttribute('data-togglegroup', tableid);
        });

        // Find the select-all checkbox child(ren) and adjust their togglegroup data.
        const selectalls = [...container.querySelectorAll(selectors.selectall)];
        selectalls.forEach(selectall => {
            selectall.dataset.togglegroup = tableid;
            selectall.setAttribute('data-togglegroup', tableid);
        });

        // Find the select-items checkbox children and adjust their togglegroup data.
        const selectitems = [...container.querySelectorAll(selectors.selectitem)];
        selectitems.forEach(selectitem => {
            selectitem.dataset.togglegroup = tableid;
            selectitem.setAttribute('data-togglegroup', tableid);
        });
    });
};

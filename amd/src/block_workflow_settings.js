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
 * Javascript to initialise the configuration panels for workflow definitions.
 *
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/str', 'core/url', 'core/ajax', 'core/notification'],
    function ($, ModalFactory, ModalEvents, str, url, ajax, notification) {

        var initConfig = function (langStrings) {
            $('button[data-class="config_workflow"]').on('click', function (e) {
                e.preventDefault();
                var workflowid = $(e.currentTarget).data('id');
                ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    title: langStrings[0],
                    body: '<iframe id="config-frame" class="w-100 mh-100 border-0" sandbox="allow-forms allow-scripts" src="' +
                        url.relativeUrl('blocks/opencast/serveworkflowconfigpanel.php', {
                            'workflowid': workflowid
                        }) + '"</iframe>'
                })
                    .then(function (modal) {
                        modal.show();
                    });
            });
        };

        var initDelete = function (langStrings) {
            $('button[data-class="del_workflow"]').on('click', function (e) {
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                var workflowdefid = $(e.currentTarget).data('defid');
                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: langStrings[1],
                    body: langStrings[2].replace('%WORKFLOW%', workflowdefid)
                })
                    .then(function (modal) {
                        modal.setSaveButtonText(langStrings[3]);
                        modal.getRoot().on(ModalEvents.save, function () {
                            ajax.call([{
                                methodname: 'block_opencast_delete_workflow',
                                args: {id: id},
                                done: function (res) {
                                    if (res.success) {
                                        $('#workflow_' + workflowdefid).remove();
                                        $('#fitem_id_delete_' + id).remove();
                                    } else {
                                        notification.alert(langStrings[4], langStrings[5]);
                                    }
                                },
                                fail: function() {
                                    notification.alert(langStrings[4], langStrings[5]);
                                }
                            }]);
                        });
                        modal.show();
                    });
            });
        };

        /*
         * Initialise all of the modules for the opencast block.
         */
        var init = function () {
            // Load strings
            var strings = [
                {
                    key: 'configworkflow_modal_title',
                    component: 'block_opencast'
                },
                {
                    key: 'workflow_delete',
                    component: 'block_opencast'
                },
                {
                    key: 'workflow_delete_confirm',
                    component: 'block_opencast'
                },
                {
                    key: 'delete'
                },
                {
                    key: 'error'
                },
                {
                    key: 'workflow_delete_error',
                    component: 'block_opencast'
                }
            ];
            str.get_strings(strings).then(function (results) {
                initConfig(results);
                initDelete(results);
            });

            $('button[data-class="del_workflow"]').addClass('btn-danger');

            window.addEventListener('message', function (event) {
                if (event.origin !== "null") {
                    return;
                }
                $('#config-frame').height(event.data);
            });
        };

        return {
            init: init
        };
    });


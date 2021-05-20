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
 * Javascript to initialise the opencast block.
 *
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/str', 'core/url'],
    function ($, ModalFactory, ModalEvents, str, url) {

        var initWorkflowModal = function (courseid, langstrings) {
            var workflows = JSON.parse($('#workflowsjson').text());

            $('.start-workflow').on('click', function (e) {
                e.preventDefault();
                var clickedVideo = $(e.currentTarget);
                var select = '<select class="custom-select mb-3" id="workflowselect" name="workflow">';

                for (let workflow in workflows) {
                    select += '<option value="' + workflow + '">' + workflows[workflow].title + '</option>';
                }

                select += '</select>';

                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: langstrings[5],
                    body: '<form id="startWorkflowForm" method="post" action="' +
                        url.relativeUrl('blocks/opencast/startworkflow.php', {
                            'courseid': courseid,
                            'videoid': clickedVideo.data('id')
                        }) + '"><div class="form-group">' +
                        '<p>' + langstrings[6] + '</p>' + select + '<div id="workflowdesc"></div>' +
                        '<iframe id="config-frame" class="w-100 mh-100 border-0" sandbox="allow-forms allow-scripts" src="">' +
                        '</iframe><input type="hidden" name="configparams" id="configparams"></form>'
                })
                    .then(function (modal) {
                        modal.setSaveButtonText(langstrings[5]);
                        var root = modal.getRoot();
                        root.on(ModalEvents.save, function (e) {
                            document.getElementById('config-frame').contentWindow.postMessage('getdata', '*');
                            // Handle form submission after receiving data.
                            e.preventDefault();
                        });


                        // Show description for initial value.
                        modal.show().then(function () {
                            $('#workflowdesc').html(workflows[$('#workflowselect').val()].description);
                            $('#config-frame').attr('src', url.relativeUrl('blocks/opencast/serveworkflowconfigpanel.php', {
                                'courseid': courseid,
                                'workflowid': $('#workflowselect').val()
                            }));

                            // Show workflow description when selected.
                            $('#workflowselect').change(function () {
                                let workflowid = $(this).val();
                                $('#workflowdesc').html(workflows[workflowid].description);
                                $('#config-frame').attr('src', url.relativeUrl('blocks/opencast/serveworkflowconfigpanel.php', {
                                    'courseid': courseid,
                                    'workflowid': workflowid
                                }));
                            });
                        });
                    });
            });
        };

        var initReportModal = function (courseid, langstrings) {
            $('.report-problem').on('click', function (e) {
                e.preventDefault();
                var clickedVideo = $(e.currentTarget);
                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: langstrings[0],
                    body: '<form id="reportProblemForm" method="post" action="' +
                        url.relativeUrl('blocks/opencast/reportproblem.php', {
                            'courseid': courseid,
                            'videoid': clickedVideo.data('id')
                        }) + '"><div class="form-group">' +
                        '<label for="inputMessage">' + langstrings[1] + '</label>' +
                        '<textarea class="form-control" id="inputMessage" name="inputMessage" rows="4" placeholder="' +
                        langstrings[2] + '">' + '</textarea>' +
                        '  <div class="invalid-feedback d-none" id="messageValidation">' + langstrings[3] + '</div>' +
                        '</div></form>'
                })
                    .then(function (modal) {
                        modal.setSaveButtonText(langstrings[4]);
                        var root = modal.getRoot();
                        root.on(ModalEvents.save, function (e) {
                            if ($('#inputMessage').val()) {
                                $('#reportProblemForm').submit();
                            } else {
                                $('#inputMessage').addClass('is-invalid');
                                $('#messageValidation').removeClass('d-none');
                            }
                            e.preventDefault();
                        });
                        modal.show();

                    });
            });
        };

        /*
         * Initialise all of the modules for the opencast block.
         */
        var init = function (courseid) {
            // Load strings
            var strings = [
                {
                    key: 'reportproblem_modal_title',
                    component: 'block_opencast'
                },
                {
                    key: 'reportproblem_modal_body',
                    component: 'block_opencast'
                },
                {
                    key: 'reportproblem_modal_placeholder',
                    component: 'block_opencast'
                },
                {
                    key: 'reportproblem_modal_required',
                    component: 'block_opencast'
                },
                {
                    key: 'reportproblem_modal_submit',
                    component: 'block_opencast'
                },
                {
                    key: 'startworkflow',
                    component: 'block_opencast'
                },
                {
                    key: 'startworkflow_modal_body',
                    component: 'block_opencast'
                }
            ];
            str.get_strings(strings).then(function (results) {
                initWorkflowModal(courseid, results);
                initReportModal(courseid, results);
            });
            window.addEventListener('message', function (event) {
                if (event.origin !== "null") {
                    return;
                }

                if (event.data === parseInt(event.data)) {
                    $('#config-frame').height(event.data);
                } else {
                    $('#configparams').val(event.data);
                    $('#startWorkflowForm').submit();
                }
            });
        };

        return {
            init: init
        };
    });


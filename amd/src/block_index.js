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
 * @module     block_opencast
 * @copyright  2021 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/str', 'core/url', 'core/notification', 'core/toast', 'core/ajax'],
    function($, ModalFactory, ModalEvents, str, url, Notification, Toast, Ajax) {
        /**
         * Instantiate the window variable in order to work with Intervals
         *
         */
        window.liveUpdateInterval = null;
        window.liveUpdateItemsWithError = [];

        var initWorkflowModal = function(ocinstanceid, courseid, langstrings) {
            if (document.getElementById('workflowsjson')) {
                var workflows = JSON.parse($('#workflowsjson').text());

                $('.start-workflow').on('click', function(e) {
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
                                'ocinstanceid': ocinstanceid,
                                'courseid': courseid,
                                'videoid': clickedVideo.data('id')
                            }) + '"><div class="form-group">' +
                            '<p>' + langstrings[6] + '</p>' + select + '<div id="workflowdesc"></div>' +
                            '<iframe id="config-frame" class="w-100 mh-100 border-0" sandbox="allow-forms allow-scripts" src="">' +
                            '</iframe><input type="hidden" name="configparams" id="configparams"></form>'
                    }, undefined)
                        .then(function(modal) {
                            modal.setSaveButtonText(langstrings[5]);
                            var root = modal.getRoot();
                            root.on(ModalEvents.save, function(e) {
                                document.getElementById('config-frame').contentWindow.postMessage('getdata', '*');
                                // Handle form submission after receiving data.
                                e.preventDefault();
                            });

                            // Show description for initial value.
                            modal.show().then(function() {
                                const workflowselect = $('#workflowselect');
                                $('#workflowdesc').html(workflows[workflowselect.val()].description);
                                $('#config-frame').attr('src', url.relativeUrl('blocks/opencast/serveworkflowconfigpanel.php', {
                                    'ocinstanceid': ocinstanceid,
                                    'courseid': courseid,
                                    'workflowid': workflowselect.val()
                                }));

                                // Show workflow description when selected.
                                workflowselect.change(function() {
                                    let workflowid = $(this).val();
                                    $('#workflowdesc').html(workflows[workflowid].description);
                                    $('#config-frame').attr('src', url.relativeUrl('blocks/opencast/serveworkflowconfigpanel.php', {
                                        'ocinstanceid': ocinstanceid,
                                        'courseid': courseid,
                                        'workflowid': workflowid
                                    }));
                                });
                                return;
                            }).catch(Notification.exception);
                            return;
                        }).catch(Notification.exception);
                });
            }
        };

        var initReportModal = function(ocinstanceid, courseid, langstrings) {
            $('.report-problem').on('click', function(e) {
                e.preventDefault();
                var clickedVideo = $(e.currentTarget);
                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: langstrings[0],
                    body: '<form id="reportProblemForm" method="post" action="' +
                        url.relativeUrl('blocks/opencast/reportproblem.php', {
                            'ocinstanceid': ocinstanceid,
                            'courseid': courseid,
                            'videoid': clickedVideo.data('id')
                        }) + '"><div class="form-group">' +
                        '<label for="inputMessage">' + langstrings[1] + '</label>' +
                        '<textarea class="form-control" id="inputMessage" name="inputMessage" rows="4" placeholder="' +
                        langstrings[2] + '">' + '</textarea>' +
                        '  <div class="invalid-feedback d-none" id="messageValidation">' + langstrings[3] + '</div>' +
                        '</div></form>'
                })
                    .then(function(modal) {
                        modal.setSaveButtonText(langstrings[4]);
                        var root = modal.getRoot();
                        root.on(ModalEvents.save, function(e) {
                            if ($('#inputMessage').val()) {
                                $('#reportProblemForm').submit();
                            } else {
                                $('#inputMessage').addClass('is-invalid');
                                $('#messageValidation').removeClass('d-none');
                            }
                            e.preventDefault();
                        });
                        modal.show();
                        return;
                    }).catch(Notification.exception);
            });
        };

        /*
         * Initialise the status live update in the overview page.
         */
        var initLiveUpdate = function(ocinstanceid, contextid, reloadtimeout) {
            if (window.liveUpdateInterval != null) {
                clearInterval(window.liveUpdateInterval);
            }
            window.liveUpdateItemsWithError = [];
            var items = getLiveUpdateItems();
            if (items.length) {
                window.liveUpdateInterval = setInterval(function () {
                    var processingItems = getLiveUpdateProcessingItems();
                    var uploadingItems = getLiveUpdateUploadingItems();
                    if (processingItems.length == 0 && uploadingItems.length == 0) {
                        clearInterval(window.liveUpdateInterval);
                        if (window.liveUpdateItemsWithError.length > 0) {
                            var titles = window.liveUpdateItemsWithError.join('</li><li>');
                            str.get_string('liveupdate_fail_notification_message', 'block_opencast', titles)
                                .done(function(result) {
                                    Notification.addNotification({
                                        message: result,
                                        type: 'error'
                                    });
                                })
                                .fail(Notification.exception);
                        }
                        return;
                    }
                    for (var processingItem of processingItems) {
                        liveUpdatePerformAjax('processing', ocinstanceid, contextid, processingItem, reloadtimeout);
                    }
                    for (var uploadingItem of uploadingItems) {
                        liveUpdatePerformAjax('uploading', ocinstanceid, contextid, uploadingItem, reloadtimeout);
                    }
                }, 1000, ocinstanceid, contextid, url, reloadtimeout);
            }
        };

        /*
         * Gets all status live updates items (flags).
         */
        var getLiveUpdateItems = function() {
            var processingItems = getLiveUpdateProcessingItems();
            var uploadingItems = getLiveUpdateUploadingItems();
            return processingItems.concat(uploadingItems);
        };

        /*
         * Gets all status live updates items for Processing states.
         */
        var getLiveUpdateProcessingItems = function() {
            var itemsNodeList = document.getElementsByName('liveupdate_processing_item');
            return Array.from(itemsNodeList);
        };

        /*
         * Gets all status live updates items for uploading status.
         */
        var getLiveUpdateUploadingItems = function() {
            var itemsNodeList = document.getElementsByName('liveupdate_uploading_item');
            return Array.from(itemsNodeList);
        };

        /*
         * Perform status live update Ajax call to the backend to get the related info.
         */
        var liveUpdatePerformAjax = function(type, ocinstanceid, contextid, item, reloadtimeout) {
            var identifier = item.value;
            var title = item?.dataset?.title ? item.dataset.title : '';
            if (identifier == undefined || title == '') {
                window.liveUpdateItemsWithError.push(title);
                item.remove();
                return;
            }
            Ajax.call([{
                methodname: 'block_opencast_get_liveupdate_info',
                args: {contextid: contextid, ocinstanceid: ocinstanceid, type: type, identifier: identifier},
                done: function(status) {
                    if (status == '') {
                        window.liveUpdateItemsWithError.push(title);
                        item.remove();
                        return;
                    }
                    var statusObject = JSON.parse(status);
                    if (statusObject.replace != '') {
                        replaceLiveUpdateInfo(item, statusObject.replace);
                    }
                    if (statusObject.remove == true) {
                        item.remove();
                        var stringparams = {
                            timeout: reloadtimeout,
                            title: title
                        };
                        str.get_string('liveupdate_toast_notification', 'block_opencast', stringparams)
                            .done(function(result) {
                                Toast.add(result);
                            })
                            .fail(Notification.exception);
                        setTimeout(function() {
                            window.location.reload();
                        }, reloadtimeout * 1000);
                    }
                },
                fail: function(er) {
                    window.liveUpdateItemsWithError.push(title);
                    item.remove();
                }
            }]);
        };

        /*
         * Replace the new live update status with the current one for both text and DOM element.
         */
        var replaceLiveUpdateInfo = function(item, replace) {
            if (item == undefined || replace == '' || typeof replace != 'string') {
                return;
            }
            var newDiv = document.createElement('div');
            newDiv.innerHTML = replace.trim();
            var replaceElm = newDiv.firstChild;
            if (replaceElm.nodeName == '#text') {
                var prevText = item.parentNode.firstChild;
                prevText.remove();
                var newText = document.createTextNode(replace.trim());
                item.parentNode.insertBefore(newText, item);
            } else if (item.previousElementSibling) {
                var prevElm = item.previousElementSibling;
                var newDiv = document.createElement('div');
                newDiv.innerHTML = replace.trim();
                var replaceElm = newDiv.firstChild;
                if (!areElementsEqual(replaceElm, prevElm)) {
                    prevElm.remove();
                    item.parentNode.insertBefore(replaceElm, item);
                }
            }
        };

        /*
         * Checks if the liev update DOM elements (new vs old) are equal. 
         */
        var areElementsEqual = function(baseElm, checkElm) {
            var isEqual = true;
            var attributes = baseElm.getAttributeNames();
            for (var attributeName of attributes) {
                var baseAttributeValue = baseElm.getAttribute(attributeName).trim();
                var checkAttributeValue = '';
                if (checkElm.hasAttribute(attributeName)) {
                    checkAttributeValue = checkElm.getAttribute(attributeName).trim();
                }
                if (checkAttributeValue == '') {
                    continue;
                }
                if (checkAttributeValue != baseAttributeValue) {
                    isEqual = false;
                }
            }
            return isEqual;
        }

        /*
         * Initialise all of the modules for the opencast block.
         */
        var init = function(courseid, ocinstanceid, contextid, liveupdate) {
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
            str.get_strings(strings).then(function(results) {
                initWorkflowModal(ocinstanceid, courseid, results);
                initReportModal(ocinstanceid, courseid, results);
                return;
            }).catch(Notification.exception);
            window.addEventListener('message', function(event) {
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
            if (liveupdate.enabled) {
                initLiveUpdate(ocinstanceid, contextid, liveupdate.timeout);
            }
        };

        return {
            init: init
        };
    });


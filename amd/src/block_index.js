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

define(['jquery', 'core/modal_factory', 'core/modal_events',
    'core/str', 'core/url', 'core/notification', 'core/toast', 'core/ajax'],
    function($, ModalFactory, ModalEvents, str, url, Notification, Toast, Ajax) {
        /**
         * Instantiate the window variable in order to work with Intervals
         *
         */
        window.liveUpdateInterval = null;
        window.liveUpdateItemsWithError = [];
        window.liveUpdateState = null;

        var pauseLiveUpdate = function(liveupdate) {
            if (!liveupdate.enabled) {
                return;
            }
            if (window.liveUpdateInterval !== null) {
                clearInterval(window.liveUpdateInterval);
                window.liveUpdateState = 'paused';
            }
        };

        var resumeLiveUpdate = function(ocinstanceid, contextid, liveupdate) {
            if (!liveupdate.enabled) {
                return;
            }
            if (window.liveUpdateState == 'paused') {
                initLiveUpdate(ocinstanceid, contextid, liveupdate.timeout);
                window.liveUpdateState = 'resumed';
            }
        };

        var initWorkflowModal = function(ocinstanceid, courseid, langstrings, contextid, liveupdate) {
            if (document.getElementById('workflowsjson')) {
                var workflows = JSON.parse($('#workflowsjson').text());
                var privacyinfohtml = null;
                var privacytitle = null;
                var privacyworkflows = null;
                var hasprivacyinfo = false;
                if (document.getElementById('workflowprivacynotice')) {
                    hasprivacyinfo = true;
                    privacyinfohtml = $('#swprivacynoticeinfotext').html();
                    privacytitle = $('#swprivacynoticetitle').text();
                    privacyworkflows = JSON.parse($('#swprivacynoticewfds').text());
                }

                $('.start-workflow').on('click', function(e) {
                    e.preventDefault();
                    const detail = e?.detail || {};

                    var clickedVideo = $(e.currentTarget);
                    var actionurl = url.relativeUrl('blocks/opencast/startworkflow.php', {
                        'ocinstanceid': ocinstanceid,
                        'courseid': courseid,
                        'videoid': clickedVideo.data('id')
                    });
                    var ismassaction = false;
                    var bulkinfodiv = '';
                    var massactioncontainer = null;
                    if (detail?.type === 'bulk' && detail?.selectedids && detail?.container) {
                        ismassaction = true;
                        massactioncontainer = detail.container;
                        const table = massactioncontainer.querySelector('table.opencast-videos-table');
                        const tableid = table?.id;
                        let seriesid = '';
                        if (tableid) {
                            seriesid = tableid.replace('opencast-videos-table-', '');
                        }
                        bulkinfodiv = '<div id="bulkinfodiv" class="w-100 mb-1">';
                        bulkinfodiv += '<p>' + langstrings[12].replace('{$a}', detail.selectedtitles.join('</li><li>')) + '</p>';
                        bulkinfodiv += '</div>';
                        for (let videoid of detail.selectedids) {
                            bulkinfodiv += '<input type="hidden" name="videoids[]" value="' + videoid + '">';
                        }
                        bulkinfodiv += '<input type="hidden" name="ismassaction" value="1">';
                        actionurl = url.relativeUrl(detail.url, {
                            'ocinstanceid': ocinstanceid,
                            'courseid': courseid,
                            'seriesid': seriesid
                        });
                    }

                    var select = '<select class="custom-select mb-3" id="workflowselect" name="workflow">';

                    for (let workflow in workflows) {
                        select += '<option value="' + workflow + '">' + workflows[workflow].title + '</option>';
                    }

                    select += '</select>';

                    var privacynoticediv = '';
                    if (hasprivacyinfo) {
                        privacynoticediv = '<div id="privacynoticediv" class="w-100 mb-2 d-none">';
                        privacynoticediv += '<strong>' + privacytitle + '</strong>';
                        privacynoticediv += '<div class="pl-1 pr-1">' + privacyinfohtml + '</div>';
                        privacynoticediv += '</div>';
                    }

                    var workflowdescdiv = '<div id="workflowdescdiv" class="mb-2 d-none"><strong>' + langstrings[7] +
                        '</strong><p class="pl-1 pr-1" id="workflowdesc"></p></div>';

                    var workflowconfigpaneldiv = '<div id="workflowconfigpaneldiv" class="d-none">' +
                        '<strong>' + langstrings[8] + '</strong>' +
                        '<iframe id="config-frame" ' +
                        'class="w-100 mh-100 m-0 p-0 border-0" sandbox="allow-forms allow-scripts">' +
                        '</iframe><input type="hidden" name="configparams" id="configparams"></div>';

                    var body = '<form id="startWorkflowForm" method="post" action="' +
                        actionurl
                        + '"><div class="form-group">' +
                        bulkinfodiv +
                        '<p>' + langstrings[6] + '</p>' +
                        select +
                        workflowdescdiv +
                        privacynoticediv +
                        workflowconfigpaneldiv +
                        '</div>' +
                        '</form>';

                    ModalFactory.create({
                        type: ModalFactory.types.SAVE_CANCEL,
                        title: ismassaction ? langstrings[13] : langstrings[5],
                        body: body
                    }, undefined)
                        .then(function(modal) {
                            // Pause the live update if it is running.
                            pauseLiveUpdate(liveupdate);
                            modal.setSaveButtonText(langstrings[5]);
                            var root = modal.getRoot();
                            root.on(ModalEvents.save, function(e) {
                                // Handle form submission after receiving data, if the workflow has config panel.
                                if ($('#config-frame').is(':visible')) {
                                    document.getElementById('config-frame').contentWindow.postMessage('getdata', '*');
                                    e.preventDefault();
                                } else {
                                    // If the workflow has no config panel, we submit it directly.
                                    $('#startWorkflowForm').submit();
                                }
                            });
                            root.on(ModalEvents.hidden, function() {
                                // Resume the live update if it was paused.
                                resumeLiveUpdate(ocinstanceid, contextid, liveupdate);
                                // Destroy when hidden/closed.
                                modal.destroy();
                                // Change the bulk action select back to choose...
                                resetVideosTableBulkActions(massactioncontainer);
                            });

                            // Show description for initial value.
                            modal.show().then(function() {
                                const workflowselect = $('#workflowselect');
                                let workflowid = workflowselect.val();
                                displayWorkflowDescription(workflows[workflowid]);
                                displayWorkflowConfigPanel(ocinstanceid, courseid, workflowid);
                                // The first time to check if the privacy notice must be displayed.
                                displayWorkflowPrivacyNotice(privacyworkflows, workflowid);

                                // Show workflow description when selected.
                                workflowselect.change(function() {
                                    let workflowid = $(this).val();
                                    displayWorkflowDescription(workflows[workflowid]);
                                    displayWorkflowConfigPanel(ocinstanceid, courseid, workflowid);
                                    // After each change, check if the selected workflow has to be displayed.
                                    displayWorkflowPrivacyNotice(privacyworkflows, workflowid);
                                });
                                return;
                            }).catch(Notification.exception);
                            return;
                        }).catch(Notification.exception);
                });
            }
        };

        /**
         * Helper function to display the privacy notice in workflow modal dialog.
         * @param {Array} privacyworkflows an array list of workflows to display privacy notice for.
         * @param {string} workflowid workflow def id
         */
        var displayWorkflowPrivacyNotice = function(privacyworkflows, workflowid) {
            if (Array.isArray(privacyworkflows) && (privacyworkflows.length === 0 || privacyworkflows.includes(workflowid))) {
                $('#privacynoticediv').removeClass('d-none');
            } else {
                $('#privacynoticediv').addClass('d-none');
            }
        };

        /**
         * Helper function to display the description of the workflow.
         * @param {Object} workflowobj the workflow object
         */
        var displayWorkflowDescription = function(workflowobj) {
            if (workflowobj?.description) {
                $('#workflowdescdiv').removeClass('d-none');
                $('#workflowdesc').html(workflowobj.description);
            } else {
                $('#workflowdescdiv').addClass('d-none');
            }
        };

        /**
         * Helper function to display Workflow configurration panel.
         * @param {string} ocinstanceid oc instance id
         * @param {string} courseid course id
         * @param {string} workflowid workflow def id
         */
        var displayWorkflowConfigPanel = function(ocinstanceid, courseid, workflowid) {
            $('#workflowconfigpaneldiv').addClass('d-none');
            $('#workflowconfigpanelloading').removeClass('d-none');
            $('#config-frame').attr('src', '');
            var configpanelsrc = url.relativeUrl('blocks/opencast/serveworkflowconfigpanel.php', {
                'ocinstanceid': ocinstanceid,
                'courseid': courseid,
                'workflowid': workflowid
            });
            $.ajax({
                url: configpanelsrc,
                success: (data) => {
                    if (data.trim() !== '') {
                        $('#workflowconfigpaneldiv').removeClass('d-none');
                        $('#config-frame').attr('src', configpanelsrc);
                    }
                },
                async: false
            });
        };

        var initReportModal = function(ocinstanceid, courseid, langstrings, contextid, liveupdate) {
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
                        // Pause the live update if it is running.
                        pauseLiveUpdate(liveupdate);
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
                        root.on(ModalEvents.hidden, function() {
                            // Resume the live update if it was paused.
                            resumeLiveUpdate(ocinstanceid, contextid, liveupdate);
                            // Destroy when hidden/closed.
                            modal.destroy();
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
            if (window.liveUpdateInterval !== null) {
                clearInterval(window.liveUpdateInterval);
            }
            window.liveUpdateItemsWithError = [];
            var items = getLiveUpdateItems();
            if (items.length) {
                window.liveUpdateInterval = setInterval(function() {
                    // Adding the state checker here, in order to pause the live update from other js modules like block_massaction.
                    if (window.liveUpdateState === 'paused') {
                        return;
                    }
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
                }, 5000, ocinstanceid, contextid, url, reloadtimeout);
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
                fail: function() {
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
                newDiv.innerHTML = replace.trim();
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
        };

        /*
         * Copies the direct access link into the clipboard.
         */
        var initCopyAccessLinkToClipboard = function() {
            $('.access-link-copytoclipboard').on('click', function(e) {
                e.preventDefault();
                var element = e.currentTarget;
                var link = element.getAttribute('href');
                if (!link) {
                    str.get_string('directaccess_copy_no_link', 'block_opencast')
                        .done(function(result) {
                            Toast.add(result, {type: 'warning'});
                        })
                        .fail(Notification.exception);
                    return;
                }

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(link)
                    .then(() => {
                        str.get_string('directaccess_copy_success', 'block_opencast')
                            .done(function(result) {
                                Toast.add(result);
                            })
                            .fail(Notification.exception);
                        return;
                    }).catch();
                    return;
                } else {
                    str.get_string('directaccess_copytoclipboard_unavialable', 'block_opencast')
                        .done(function(result) {
                            Toast.add(result, {type: 'danger', autohide: false, closeButton: true});
                        })
                        .fail(Notification.exception);
                }
            });
        };
        /*
         * Initalizes the unarchive uploadjob package on button click with modal.
         */
        var initUnarchiveUploadJobModal = function(ocinstanceid, langstrings, contextid, liveupdate) {
            $('.unarchive-uploadjob').on('click', function(e) {
                e.preventDefault();
                var targetBtn = $(e.currentTarget);
                var uploadjobid = targetBtn.data('id');
                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: langstrings[9],
                    body: langstrings[10]
                })
                .then(function(modal) {
                    // Pause the live update if it is running.
                    pauseLiveUpdate(liveupdate);
                    modal.setSaveButtonText(langstrings[11]);
                    var root = modal.getRoot();
                    root.on(ModalEvents.save, function (e) {
                        Ajax.call([{
                            methodname: 'block_opencast_unarchive_uploadjob',
                            args: {contextid: contextid, ocinstanceid: ocinstanceid, uploadjobid: uploadjobid},
                            done: function() {
                                window.location.reload();
                            },
                            fail: Notification.exception
                        }]);
                        e.preventDefault();
                    });
                    root.on(ModalEvents.hidden, function() {
                        // Resume the live update if it was paused.
                        resumeLiveUpdate(ocinstanceid, contextid, liveupdate);
                        // Destroy when hidden/closed.
                        modal.destroy();
                    });
                    modal.show();
                    return;
                })
                .catch(Notification.exception);
            });
        };

        /**
         * Resets the bulk action select dropdowns.
         * @param {object} container The wrapper container which contains the dropdowns, table and selection items of massaction.
         * @param {boolean} disabled a flag to set the dropdown attribute upon using the function (default to false).
         */
        var resetVideosTableBulkActions = function (container, disabled = false) {
            if (!container) {
                return;
            }
            const dropdowns = [...container.querySelectorAll('.opencast-videos-table-massactions')];
            dropdowns.forEach(dropdown => {
                dropdown.value = '';
                dropdown.disabled = disabled;
            });
        };

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
                },
                {
                    key: 'startworkflow_modal_description_title',
                    component: 'block_opencast'
                },
                {
                    key: 'startworkflow_modal_configpanel_title',
                    component: 'block_opencast'
                },
                {
                    key: 'unarchiveuploadjob',
                    component: 'block_opencast'
                },
                {
                    key: 'unarchiveuploadjobconfirmtext',
                    component: 'block_opencast'
                },
                {
                    key: 'unarchiveuploadjobconfirmbtn_save',
                    component: 'block_opencast'
                },
                {
                    key: 'videostable_massaction_startworkflow_modal_body',
                    component: 'block_opencast'
                },
                {
                    key: 'videostable_massaction_startworkflow_modal_title',
                    component: 'block_opencast'
                }
            ];
            str.get_strings(strings).then(function(results) {
                initWorkflowModal(ocinstanceid, courseid, results, contextid, liveupdate);
                initReportModal(ocinstanceid, courseid, results, contextid, liveupdate);
                initUnarchiveUploadJobModal(ocinstanceid, results, contextid, liveupdate);
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
            initCopyAccessLinkToClipboard();
        };

        return {
            init: init
        };
    });


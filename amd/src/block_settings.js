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
 * Javascript to initialise the opencast block settings.
 *
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Tabulator from 'block_opencast/tabulator';
import $ from 'jquery';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import * as str from 'core/str';

export const init = (rolesinputid, metadatainputid, metadataseriesinputid, ocinstanceid) => {

    // Load strings
    var strings = [
        {key: 'heading_role', component: 'block_opencast'},
        {key: 'heading_actions', component: 'block_opencast'},
        {key: 'heading_permanent', component: 'block_opencast'},
        {key: 'delete_role', component: 'block_opencast'},
        {key: 'delete_confirm_role', component: 'block_opencast'},
        {key: 'delete_metadata', component: 'block_opencast'},
        {key: 'delete_confirm_metadata', component: 'block_opencast'},
        {key: 'heading_name', component: 'block_opencast'},
        {key: 'heading_datatype', component: 'block_opencast'},
        {key: 'heading_required', component: 'block_opencast'},
        {key: 'heading_readonly', component: 'block_opencast'},
        {key: 'heading_params', component: 'block_opencast'},
        {key: 'delete', component: 'moodle'}
    ];
    str.get_strings(strings).then(function (jsstrings) {
        // Style hidden input.
        var rolesinput = $('#' + rolesinputid);
        rolesinput.parent().hide();
        rolesinput.parent().next().hide(); // Default value.

        // Don't create tables if they are not visible.
        if(!rolesinput.length) {
            return;
        }

        var metadatainput = $('#' + metadatainputid);
        metadatainput.parent().hide();
        metadatainput.parent().next().hide(); // Default value.

        var metadataseriesinput = $('#' + metadataseriesinputid);
        metadataseriesinput.parent().hide();
        metadataseriesinput.parent().next().hide(); // Default value.

        var rolestable = new Tabulator("#rolestable_" + ocinstanceid, {
            data: JSON.parse(rolesinput.val()),
            layout: "fitColumns",
            dataChanged: function (data) {
                data = data.filter(value => value.rolename);
                rolesinput.val(JSON.stringify(data));
            },
            columns: [
                {
                    title: jsstrings[0], field: "rolename", editor: "input", widthGrow: 4, cellEdited: function (cell) {
                        if (cell.getData().rolename.includes('[USERNAME]') || cell.getData().rolename.includes('[USERNAME_LOW]') ||
                            cell.getData().rolename.includes('[USERNAME_UP]')) {
                            // Tick permanent checkbox.
                            cell.getRow().update({'permanent': 1});
                            cell.getRow().getCell("permanent").getElement().getElementsByTagName("input")[0].disabled = true;
                        } else {
                            cell.getRow().getCell("permanent").getElement().getElementsByTagName("input")[0].disabled = false;
                        }
                    }
                },
                {title: jsstrings[1], field: "actions", editor: "input", widthGrow: 1},
                {
                    title: jsstrings[2],
                    field: "permanent",
                    hozAlign: "center",
                    widthGrow: 0,
                    formatter: function (cell) {
                        var input = document.createElement('input');
                        input.type = 'checkbox';
                        input.checked = cell.getValue();
                        input.addEventListener('click', function () {
                            cell.getRow().update({'permanent': $(this).prop('checked') ? 1 : 0});
                        });

                        if (cell.getData().rolename.includes('[USERNAME]') || cell.getData().rolename.includes('[USERNAME_LOW]') ||
                            cell.getData().rolename.includes('[USERNAME_UP]')) {
                            input.disabled = true;
                        }

                        return input;
                    }
                },
                {
                    title: "",
                    width: 40,
                    headerSort: false,
                    hozAlign: "center",
                    formatter: function () {
                        return '<i class="icon fa fa-trash fa-fw"></i>';
                    },
                    cellClick: function (e, cell) {
                        ModalFactory.create({
                            type: ModalFactory.types.SAVE_CANCEL,
                            title: jsstrings[3],
                            body: jsstrings[4]
                        })
                            .then(function (modal) {
                                modal.setSaveButtonText(jsstrings[12]);
                                modal.getRoot().on(ModalEvents.save, function () {
                                    cell.getRow().delete();
                                });
                                modal.show();
                            });
                    }
                }
            ],
        });

        $('#addrow-rolestable_' + ocinstanceid).click(function () {
            rolestable.addRow({'permanent': 0});
        });

        var metadatatable = new Tabulator("#metadatatable_" + ocinstanceid, {
            data: JSON.parse(metadatainput.val()),
            layout: "fitColumns",
            movableRows: true,
            rowMoved: function () {
                // Order by row position
                var data = metadatatable.getRows().map(row => row.getData());
                data = data.filter(value => value.name);
                metadatainput.val(JSON.stringify(data));
            },
            dataChanged: function () {
                // Order by row position
                var data = metadatatable.getRows().map(row => row.getData());
                data = data.filter(value => value.name);
                $('#' + metadatainputid).val(JSON.stringify(data));
            },
            columns: [
                {
                    title: jsstrings[7] + '   ' + $('#helpbtnname_' + ocinstanceid).html(),
                    field: "name",
                    editor: "input",
                    widthGrow: 1,
                    headerSort: false
                },
                {
                    title: jsstrings[8], field: "datatype", widthGrow: 1, headerSort: false, editor: "select", editorParams:
                        {
                            values: {
                                'text': 'String (text)',
                                'select': 'Drop Down (select)',
                                'autocomplete': 'Arrays (autocomplete)',
                                'textarea': 'Long Text (textarea)',
                                'date_time_selector': 'Date Time Selector (datetime)'
                            }
                        }
                },
                {
                    title: jsstrings[9],
                    field: "required", hozAlign: "center", widthGrow: 0, headerSort: false, formatter:

                        function (cell) {
                            var input = document.createElement('input');
                            input.type = 'checkbox';
                            input.checked = cell.getValue();
                            input.addEventListener('click', function () {
                                cell.getRow().update({'required': $(this).prop('checked') ? 1 : 0});
                            });

                            return input;
                        }
                },
                {
                    title: jsstrings[10],
                    field: "readonly", hozAlign: "center", widthGrow: 0, headerSort: false, formatter:
                        function (cell) {
                            var input = document.createElement('input');
                            input.type = 'checkbox';
                            input.checked = cell.getValue();
                            input.addEventListener('click', function () {
                                cell.getRow().update({'readonly': $(this).prop('checked') ? 1 : 0});
                            });

                            return input;
                        }
                },
                {
                    title: jsstrings[11] + '   ' + $('#helpbtnparams_' + ocinstanceid).html(),
                    field: "param_json",
                    editor: "textarea",
                    widthGrow: 2,
                    headerSort: false
                },
                {
                    title: "", width: 40, headerSort: false, hozAlign: "center", formatter:
                        function () {
                            return '<i class="icon fa fa-trash fa-fw"></i>';
                        },
                    cellClick: function (e, cell) {
                        ModalFactory.create({
                            type: ModalFactory.types.SAVE_CANCEL,
                            title: jsstrings[5],
                            body: jsstrings[6]
                        })
                            .then(function (modal) {
                                modal.setSaveButtonText(jsstrings[12]);
                                modal.getRoot().on(ModalEvents.save, function () {
                                    cell.getRow().delete();
                                });
                                modal.show();
                            });
                    }
                }
            ],
        });

        $('#addrow-metadatatable_' + ocinstanceid).click(function () {
            metadatatable.addRow({'datatype': 'text', 'required': 0, 'readonly': 0, 'param_json': null});
        });

        var metadataseriestable = new Tabulator("#metadataseriestable_" + ocinstanceid, {
            data: JSON.parse(metadataseriesinput.val()),
            layout: "fitColumns",
            movableRows: true,
            rowMoved: function () {
                // Order by row position
                var data = metadataseriestable.getRows().map(row => row.getData());
                data = data.filter(value => value.name);
                metadataseriesinput.val(JSON.stringify(data));
            },
            dataChanged: function () {
                // Order by row position
                var data = metadataseriestable.getRows().map(row => row.getData());
                data = data.filter(value => value.name);
                $('#' + metadataseriesinputid).val(JSON.stringify(data));
            },
            columns: [
                {
                    title: jsstrings[7] + '   ' + $('#helpbtnname_' + ocinstanceid).html(),
                    field: "name",
                    editor: "input",
                    widthGrow: 1,
                    headerSort: false
                },
                {
                    title: jsstrings[8], field: "datatype", widthGrow: 1, headerSort: false, editor: "select", editorParams:
                        {
                            values: {
                                'text': 'String (text)',
                                'select': 'Drop Down (select)',
                                'autocomplete': 'Arrays (autocomplete)',
                                'textarea': 'Long Text (textarea)',
                                'date_time_selector': 'Date Time Selector (datetime)'
                            }
                        }
                },
                {
                    title: jsstrings[9],
                    field: "required", hozAlign: "center", widthGrow: 0, headerSort: false, formatter:

                        function (cell) {
                            var input = document.createElement('input');
                            input.type = 'checkbox';
                            input.checked = cell.getValue();
                            input.addEventListener('click', function () {
                                cell.getRow().update({'required': $(this).prop('checked') ? 1 : 0});
                            });

                            return input;
                        }
                },
                {
                    title: jsstrings[10],
                    field: "readonly", hozAlign: "center", widthGrow: 0, headerSort: false, formatter:
                        function (cell) {
                            var input = document.createElement('input');
                            input.type = 'checkbox';
                            input.checked = cell.getValue();
                            input.addEventListener('click', function () {
                                cell.getRow().update({'readonly': $(this).prop('checked') ? 1 : 0});
                            });

                            return input;
                        }
                },
                {
                    title: jsstrings[11] + '   ' + $('#helpbtnparams_' + ocinstanceid).html(),
                    field: "param_json",
                    editor: "textarea",
                    widthGrow: 2,
                    headerSort: false
                },
                {
                    title: "", width: 40, headerSort: false, hozAlign: "center", formatter:
                        function () {
                            return '<i class="icon fa fa-trash fa-fw"></i>';
                        },
                    cellClick: function (e, cell) {
                        ModalFactory.create({
                            type: ModalFactory.types.SAVE_CANCEL,
                            title: jsstrings[5],
                            body: jsstrings[6]
                        })
                            .then(function (modal) {
                                modal.setSaveButtonText(jsstrings[12]);
                                modal.getRoot().on(ModalEvents.save, function () {
                                    cell.getRow().delete();
                                });
                                modal.show();
                            });
                    }
                }
            ],
        });

        $('#addrow-metadataseriestable_' + ocinstanceid).click(function () {
            metadataseriestable.addRow({'datatype': 'text', 'required': 0, 'readonly': 0, 'param_json': null});
        });
    });
};


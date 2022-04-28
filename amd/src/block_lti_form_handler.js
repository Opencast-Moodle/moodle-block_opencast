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
 * Ajax functions for opencast
 *
 * @module     block/opencast
 * @package    block_opencast
 * @copyright  2020 Farbod Zamani (zamani@elan-ev.de)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    /*
    * Submits lti form
    */
    var init = function(withredirecturl = '') {
        if (withredirecturl) {
            $('#ltiLaunchForm').submit(function(e) {
                e.preventDefault();
                var ocurl = decodeURIComponent($(this).attr("action"));
                var data = $(`#ltiLaunchForm`).serialize();
                $.ajax({
                    url: ocurl,
                    crossDomain: true,
                    type: 'POST',
                    xhrFields: {withCredentials: true},
                    data: data,
                    success: () => {
                        window.location = withredirecturl;
                    },
                    error: function (request, status, error) {
                        var errorMessage = request.responseText ? request.responseText :
                            'Most likely "Cross-Origin Request Blocked" happend,' +
                            ' due to the fact that the opencast server is unable to accept the LTI call from moodle server!';
                        console.log('Opencast LTI Launch (FAILED): ' + errorMessage);
                    },
                    async: false
                });
            });
            $('#ltiLaunchForm').submit();
        } else {
            $('#ltiLaunchForm').submit();
        }
    };
    return {
        init: init
    };
});

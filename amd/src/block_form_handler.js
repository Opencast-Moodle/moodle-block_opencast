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
 * @copyright  2019 Farbod Zamani (zamani@elan-ev.de)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function ($) {
    /**
     * Instantiate the window variable in order to work with Intervals
     *
     */
    window.presenterIntervalHandle = null;
    window.presentationIntervalHandle = null;
    window.presenterRun = false;
    window.presentationRun = false;

    /**
     * Clears intervals and enables Save Change Button after upload process is completed
     *
     */
    function afterUpload() {
        if (!window.presentationRun && !window.presenterRun) {
            clearInterval(window.presentationIntervalHandle);
            clearInterval(window.presenterIntervalHandle);
            $('[name="submitbutton"]').removeAttr('disabled');
        }
    }

    /**
     * Makes sure that autocomplete fields receive the form-control class in order to have consistency
     *
     */
    function autocompletePrettifier() {
        $('div[data-fieldtype="autocomplete"]').each(function (i, elm) {
            var input = $(elm).find('input');
            if (!input.hasClass('form-control')) {
                input.addClass('form-control');
            }
        });
    }

    /**
     * Sets default title if it is empty. If the filename contains one or more dots, everything after the last dot
     * is treated as the fileextension and removed.
     *
     * @param {string} filename the filename.
     */
    function setDefaultTitle(filename) {
        var title = $('input[name="title"]').val();
        if (!title && filename) {
            // Removing the extension from the filename.
            let filenameArray = filename.split('.');
            if (filenameArray.length > 1) {
                filenameArray.pop();
            }
            filename = filenameArray.join('.');
            $('input[name="title"]').val(filename);
        }
    }

    /**
     * Extracts the filename from filelist.
     *
     * @param {jQuery} filelist The filename
     * @returns {string} The filename
     */
    function getFilenameFromFilelist(filelist) {
        var filename = '';
        if (filelist.length) {
            var filelinks = filelist.find('.filepicker-filename > a');
            filename = filelinks.length ? $(filelinks[0]).text() : '';
        }
        return filename;
    }

    /**
     * Initialise all of the modules for the opencast block.
     *
     */
    var init = function () {
        // Toggle visibility of terms of use
        $('#termsofuse').hide();
        $('#termsofuse_toggle').on('click', function (e) {
            $('#termsofuse').toggle();
            e.preventDefault();
        });

        // Chunkupload.
        $('.local_chunkupload input[type="file"]').on('change', function (e) {
            setTimeout(function () {
                var span = $(e.currentTarget).siblings('label.chunkupload-label').find('.chunkupload-filename');
                if (span.length) {
                    setDefaultTitle($(span[0]).text());
                }
            }, 500);
        });

        $('.filepickerhidden').on('change', function (e) {
            setTimeout(function () {
                var filelist = $(e.currentTarget).parent().find('.filepicker-filelist');
                var filename = getFilenameFromFilelist(filelist);
                setDefaultTitle(filename);
            }, 500);
        });

        $('.filepicker-filelist').on('drop', function (e) {
            var filelist = e.currentTarget;
            var videoIdentifier = $(filelist).parent().siblings('.filepickerhidden').attr('name');

            $('[name="submitbutton"]').attr('disabled', 'disabled');

            if (videoIdentifier == 'video_presenter') {
                $(filelist).addClass('presenter-uploading');
                window.presenterIntervalHandle = setInterval(function () {
                    window.presenterRun = true;
                    if (!$('.presenter-uploading').hasClass('dndupload-inprogress')) {
                        window.presenterRun = false;
                        afterUpload();
                    }
                }, 500);
            } else {
                $(filelist).addClass('presentation-uploading');
                window.presenterIntervalHandle = setInterval(function () {
                    window.presentationRun = true;
                    if (!$('.presentation-uploading').hasClass('dndupload-inprogress')) {
                        window.presentationRun = false;
                        afterUpload();
                    }
                }, 500);
            }

            setTimeout(function () {
                var filename = getFilenameFromFilelist(filelist);
                setDefaultTitle(filename);
            }, 500);
        });

        // Ensures that autocomplete fields are loaded properly after 1 sec!
        setTimeout(function () {
            autocompletePrettifier();
            $('.moreless-actions').on('click', function () {
                autocompletePrettifier();
            });
        }, 1000);
    };

    return {
        init: init
    };
});

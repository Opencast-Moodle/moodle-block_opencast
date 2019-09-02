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
* @copyright  2019 Farbod Zamani (zamani@elan-ev.de)
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

define(['jquery'], function($) {
    /**
    * Instantiate the window variable in order to work with Intervals
    *
    */
    window.presenter_intervalHandle = null;
    window.presentation_intervalHandle = null;
    window.presenter_run = false;
    window.presentation_run = false;

    /**
    * Clears intervals and enables Save Change Button after upload process is completed
    *
    */
    function afterUpload() {
        if (!presentation_run  && !presenter_run ) {
            clearInterval(presentation_intervalHandle); 
            clearInterval(presenter_intervalHandle);              
            $('[name="submitbutton"]').removeAttr('disabled'); 
        }
    }

    /**
    * Makes sure that autocomplete fields receive the form-control class in order to have consistency
    *
    */
    function autocompletePrettifier () {
        $('div[data-fieldtype="autocomplete"]').each(function (i, elm) {
            var input = $(elm).find('input');
            if (!input.hasClass('form-control')) {
                input.addClass('form-control');
            }
        });
    }

    /**
    * Initialise all of the modules for the opencast block.
    *
    */
    var init = function() {
        
        $('.filepicker-filelist').on('drop', function (e){
            var filelist = e.currentTarget;
            var video_identifier = $(filelist).parent().siblings('.filepickerhidden').attr('name');
            
            $('[name="submitbutton"]').attr('disabled', 'disabled'); 
            
            if (video_identifier == 'video_presenter') {
                $(filelist).addClass('presenter-uploading');
                presenter_intervalHandle = setInterval(function(){
                    presenter_run = true;
                    if (!$('.presenter-uploading').hasClass('dndupload-inprogress')) {
                        presenter_run = false;
                        afterUpload();
                    }
                }, 500);
            } else {
                $(filelist).addClass('presentation-uploading');
                presenter_intervalHandle = setInterval(function(){
                    presentation_run = true;
                    if (!$('.presentation-uploading').hasClass('dndupload-inprogress')) {
                        presentation_run = false;
                        afterUpload();
                    }
                }, 500);
            }
            
        });
        
        // Ensures that autocomplete fields are loaded properly after 1 sec!
        setTimeout(function(){
            autocompletePrettifier();
            $('.moreless-actions').on('click', function (e){ //
                autocompletePrettifier();
            });
        }, 1000);
    };
    
    return {
        init: init
    };
});


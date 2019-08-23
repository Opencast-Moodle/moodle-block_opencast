require(['jquery'], function($) {
    $(document).ready(function(){ 
        window.presenter_intervalHandle = null;
        window.presentation_intervalHandle = null;
        window.presenter_run = false;
        window.presentation_run = false;
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

        function afterUpload() {
            if (!presentation_run  && !presenter_run ) {
                clearInterval(presentation_intervalHandle); 
                clearInterval(presenter_intervalHandle);              
                $('[name="submitbutton"]').removeAttr('disabled'); 
            }
        }

        setTimeout(function(){
            inputPrettifier();
            $('.moreless-actions').on('click', function (e){
                inputPrettifier();
            });
        }, 1000);

        function inputPrettifier () {
            $('div[data-fieldtype="autocomplete"]').each(function (i, elm) {
                var input = $(elm).find('input');
                if (!input.hasClass('form-control')) {
                    input.addClass('form-control');
                }
            });
        }
    });
});


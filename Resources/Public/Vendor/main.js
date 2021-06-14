$(document).ready(function(){
    $('.btn-start-backup').on('click', function(){
        var $progressBar = $(this).parent().find('.progress-bar');
        for(i=0;i<=100;i++){
            $($progressBar).css('width', i + '%');
            $($progressBar).text(i + '%')
        }
        $(this).parent().find('.btn-backupnow').removeClass('disabled');
    });
    $('.server-cloud-option').on('change', function(){
        var serverOption = $(this).val();
        $(this).parents('.configure-new-server-form').find('.server-cloud-apikey-box-wrap').show();
        $('.configure-new-server-form .server-cloud-apikey-box').removeClass('active').slideUp();
        $('.server-cloud-apikey-box-wrapper').css('min-height', $('.configure-new-server-form .server-cloud-apikey-box[data-nsbackup-server="'+serverOption+'"]').outerHeight());
        $('.configure-new-server-form .server-cloud-apikey-box[data-nsbackup-server="'+serverOption+'"]').addClass('active').slideDown();
    });
}); 
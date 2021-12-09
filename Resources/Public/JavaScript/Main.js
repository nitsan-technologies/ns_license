define([
    'jquery',
    'TYPO3/CMS/Backend/Modal',
    'TYPO3/CMS/NsLicense/Main',
    'TYPO3/CMS/Backend/jquery.clearable'
], function ($, Model) {
    $('.ns-license-activation-table .ns-license-actions-btns .btn-download').on('click', function(e){
        $('#nsLicenseLoaderUpdate').show();
    });
    $('#activation-modal .activation-modal-update').on('click', function(e){
        var url = $('.license-activation .license-activation-latest.active').attr('href');
        $('.license-activation .license-activation-latest.active').removeClass('active');
        $('#nsLicenseLoader').show();
        window.location = url;
    });
    $('.license-activation .license-deactivation').on('click', function(e) {
        $('#nsLicenseLoader').show();
    });
    if ($('#extension-info-modal').length) {
        $('#extension-info-modal').modal('show');
    };
    $('.activation-modal').on('click', function(e) {
        $('.modal').modal('hide');
    });
    $('.custom-reset').on('click', function(){
        var that = $(this);
        that.find('i').addClass('fa-spin');
        var id = that.attr('data-id');
        var defaultValue = $("#" + id).attr('data-value');
        $("#" + id).val(defaultValue);
        $("#" + id).addClass('form__field');
        setTimeout(function(){
            $("#" + id).removeClass('form__field');
            that.find('i').removeClass('fa-spin');
        }, 2000);
    });

    $('.ns-license-form').submit(function(){
        $('#nsLicenseLoader').show();
    });
});

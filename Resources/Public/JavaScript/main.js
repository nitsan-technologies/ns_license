import $ from 'jquery';
import Modal from "@typo3/backend/modal.js";

// Confirmation modalbox `Version Update` button
$('.license-activation .license-activation-latest').on('click', function(e){
    e.preventDefault();
    $(this).addClass('active');
});

$('#activation-modal .activation-modal-update').on('click', function(e){
    var url = $('.license-activation .license-activation-latest.active').attr('href');
    $('.license-activation .license-activation-latest.active').removeClass('active');
    $('#nsLicenseLoader').show();
    window.location = url;
});
//$('.license-activation .license-deactivation').on('click', function(e) {
//$('#nsLicenseLoader').show();
//});

// Confirmation modalbox `License DeActivation` button
$('.license-activation .license-deactivation-latest').on('click', function(e){
    e.preventDefault();
    $(this).addClass('active');
});
$('#deactivation-modal .deactivation-modal-update').on('click', function(e){
    var url = $('.license-activation .license-deactivation-latest.active').attr('href');
    $('.license-activation .license-deactivation-latest.active').removeClass('active');
    $('#nsLicenseLoader').show();
    window.location = url;
});

// If Cancel button from Modalbox
$('.modal .cancel-button, .modal .t3js-modal-close').on('click', function(e){
    $('.license-activation a.active').removeClass('active');
});

// Reset Form
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

// Submit to register license key
$('.ns-license-form').submit(function(){
    $('#nsLicenseLoader').show();
});

// Check for updates
$('.license-reload').on('click', function(){
    $('#nsLicenseLoader').show();
});

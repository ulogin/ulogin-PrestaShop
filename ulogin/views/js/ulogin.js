$(document).ready(function () {
    var cssClass = 'alert alert-danger ulogin';
    if (ulogin_message) {
        $('#columns').prepend('<div class="clearfix"></div><p class="' + cssClass + '"> ' + alert_ulogin + '</p>');
        //$('html, body').animate({scrollTop: $('#columns').offset().top}, 'slow');

        $('p.alert.alert-danger.ulogin').click(function () {
            $(this).hide('slow');
        });
    }
});
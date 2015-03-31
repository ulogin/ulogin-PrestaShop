/*
 * 2015 uLogin
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade uLogin to newer
 * versions in the future. If you wish to customize uLogin for your
 * needs please refer to http://ulogin.ru for more information.
 *
 *  @author uLogin RU <http://ulogin.ru>
 *  @copyright uLogin RU
 *  @license GNU General Public License, version 2
 *
 */
$(document).ready(function () {
    var cssClass = 'alert alert-danger ulogin';
    if (ulogin_message) {
        $('#columns').prepend('<div class="clearfix"></div><p class="' + cssClass + '"> ' + alert_ulogin + '</p>');

        $('p.alert.alert-danger.ulogin').click(function () {
            $(this).hide('slow');
        });
    }
});
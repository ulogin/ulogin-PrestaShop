{*
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
*}
{capture name=path}{l s='Аккаунты Социальных Сетей' mod='ulogin'}{/capture}
{capture name=path}
    <a href="{$link->getPageLink('my-account', true)|escape:'UTF-8'}">{l s='Моя учётная запись' mod='ulogin'}</a>
    <span class="navigation-pipe">{$navigationPipe|escape:'UTF-8'}</span>{l s='Аккаунты Социальных Сетей' mod='ulogin'}
{/capture}
<div><h2>Профиль uLogin</h2>

    <div>
        <h3>Синхронизация аккаунтов</h3>
        {$panel|escape:'UTF-8'}
        Привяжите ваши аккаунты соц. сетей к личному кабинету для быстрой авторизации через любой из них
    </div>

    <div>
        <h3>Привязанные аккаунты</h3>

        <div id="ulogin_synchronisation">{$syncpanel|escape:'UTF-8'}</div>
        Вы можете удалить привязку к аккаунту, кликнув по значку
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function () {
        var uloginNetwork = jQuery('#ulogin_synchronisation').find('.ulogin_network');
        uloginNetwork.click(function () {
            var network = jQuery(this).attr('data-ulogin-network');
            uloginDeleteAccount(network);
        });
    });

    function uloginDeleteAccount(network) {
        var query = $.ajax({
            type: 'POST',
            url: baseDir + 'modules/ulogin/ajax.php',
            cache: false,
            data: {
                network: network
            },
            dataType: 'json',
            error: function (data) {
                alert('Не удалось выполнить запрос');
            },
            success: function (data) {
                if (data.answerType == 'error') {
                    alert(data.msg);
                }
                if (data.answerType == 'ok') {
                    var accounts = jQuery('#ulogin_accounts'),
                            nw = accounts.find('[data-ulogin-network=' + network + ']');
                    if (nw.length > 0) nw.hide();
                }
            }
        });
        return false;
    }
</script>


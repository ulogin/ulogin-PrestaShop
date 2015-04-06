<!-- Block mymodule -->
    {$panel}
<!-- /Block mymodule -->
{strip}
    {addJsDef ulogin_message=$ulogin_message|@addcslashes:'\''}
    {if isset($ulogin_message) && $ulogin_message}
        {addJsDefL name=alert_ulogin}{l s='uLogin : %1$s' sprintf=$ulogin_message js=1 mod="ulogin"}{/addJsDefL}
    {/if}
{/strip}
{if isset($message)}
{$message}
{/if}

<?php
/**
 * 2015 uLogin
 *
 * @author    uLogin RU <https://ulogin.ru/>
 * @copyright 2015 uLogin RU
 * @license   GNU General Public License, version 2
 */

require_once(dirname(__FILE__).'../../../config/config.inc.php');
require_once(dirname(__FILE__).'../../../init.php');
$context = Context::getContext();
$user_id = (int)$context->customer->id;
$network = Tools::getValue('network');
if (isset($user_id) && isset($network))
{
	$result = Db::getInstance()->delete(_DB_PREFIX_.'customer_ulogin_table', "userid = '".$user_id.
		"' AND network ='".$network."'");
	if ($result)
		die(Tools::jsonEncode(array(
			'msg' => "Удаление аккаунта $network успешно выполнено",
			'user' => $user_id,
			'answerType' => 'ok'
		)));
	else
		die(Tools::jsonEncode(array(
			'msg' => "Ошибка при выполнении запроса на удаление $network",
			'answerType' => 'error'
		)));
}
else
	die(Tools::jsonEncode(array(
		'msg' => "Ошибка при удаление аккаунта $network",
		'answerType' => 'error'
	)));
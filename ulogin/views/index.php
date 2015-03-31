<?php
/**
 * 2015 uLogin
 *
 * DISCLAIMER
 *
 * uLogin - это инструмент, который позволяет пользователям получить единый доступ к различным
 * Интернет-сервисам без необходимости повторной регистрации, а владельцам сайтов - получить дополнительный
 * приток клиентов из социальных сетей и популярных порталов (Google, Яндекс, Mail.ru, ВКонтакте, Facebook и др.)
 *
 * @author uLogin RU <http://ulogin.ru>
 * @copyright  2015 uLogin RU
 * @license GNU General Public License, version 2
 */

header("Expires: Mon, 30 March 2015 12:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

header("Location: ../");
exit;
<?php
/**
 * 2015 uLogin
 * @author    uLogin RU <https://ulogin.ru/>
 * @copyright 2015 uLogin RU
 * @license   GNU General Public License, version 2
 * @version 1.0.2.
 */
if(!defined('_PS_VERSION_'))
	exit;

class Ulogin extends Module {

	public $errors = array();
	private $hooks = array('displayNav', 'displayHeader', 'displayFooter', 'DisplayCustomerAccount', 'DisplayMyAccountBlock');
	private $ulogin_default_options = array('display' => 'small', 'providers' => 'vkontakte,odnoklassniki,mailru,facebook', 'hidden' => 'other', 'fields' => 'first_name,last_name,email,photo,photo_big', 'optional' => 'phone', 'redirect_uri' => '',);

	private $ulogin_options = array('ulogin_label' => 'Войти с помощью:', 'ulogin_id1' => '', 'ulogin_id2' => '', 'ulogin_check_mail' => false, 'id_default_group' => '',);

	/**
	 * Получение данных о настройках плагина
	 */
	public function __construct() {
		$this->name = 'ulogin';
		$this->tab = 'front_office_features';
		$this->version = '1.0.2';
		$this->author = 'uLogin';
		$this->need_instance = 1;
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
		$this->bootstrap = true;
		$this->module_key = '35fe6c2a11167eef16be57a2462237f8';
		parent::__construct();
		$this->displayName = $this->l('uLogin');
		$this->description = $this->l('Плагин авторизации через соцсети в 1 клик');
		$this->context->smarty->assign('module_name', $this->name);
		$this->confirmUninstall = $this->l('Вы действительно хотите удалить модуль?');
		if(!Configuration::get('ulogin_default_options') && !Configuration::get('ulogin_options'))
			$this->warning = $this->l('Упс, что-то пошло не так!');
	}

	public function registerHooks() {
		foreach($this->hooks as $hook) {
			if(!$this->registerHook($hook)) {
				$this->errors = $this->l('Ошибка при установки хука') . '"$hook"<br />\n';

				return false;
			}
		}

		return true;
	}

	public function unregisterHooks() {
		foreach($this->hooks as $hook) {
			if(!$this->unregisterHook($hook)) {
				$this->errors = $this->l('Ошибка при удалении хука') . '"$hook"<br />\n';

				return false;
			}
		}

		return true;
	}

	public function addulogingroup() {
		$ulogin_gr = Db::getInstance()->executeS('SELECT id_group FROM ' . _DB_PREFIX_ . 'group_lang WHERE name = "' . $this->name . '"');
		if(!$ulogin_gr) {
			$res = Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'group` (`id_group`, `date_add`, `date_upd`) VALUES (NULL, NOW(), NOW())');
			$last_id = Db::getInstance()->Insert_ID();
			$languages = Db::getInstance()->executeS('SELECT id_lang, iso_code FROM `' . _DB_PREFIX_ . 'lang`');
			$sql = '';
			foreach($languages as $lang)
				$sql .= '(' . (int)$last_id . ', ' . (int)$lang['id_lang'] . ', "' . $this->name . '"),';
			$sql = Tools::substr($sql, 0, Tools::strlen($sql) - 1);
			$res &= Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'group_lang` (`id_group`, `id_lang`, `name`) VALUES ' . $sql);
			// Add shop association
			$res &= Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'group_shop` (`id_group`, `id_shop`) (SELECT ' . (int)$last_id . ', `value` FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` = \'PS_SHOP_DEFAULT\')');
			// Copy categories associations from the group of id 1 (default group for both visitors and customers in version 1.4) to the new group
			$res &= Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'category_group` (`id_category`, `id_group`) (SELECT `id_category`, ' . (int)$last_id . ' FROM `' . _DB_PREFIX_ . 'category_group` WHERE `id_group` = 3)');
			$controllers = Module::getModulesInstalled();
			$id_modules = array();
			foreach($controllers as $key => $id_module)
				$id_modules[$key] = $id_module['id_module'];
			$shops = Shop::getShops(true, null, true);
			if($res = Group::addModulesRestrictions($last_id, $id_modules, $shops))
				return $res;
		}

		return true;
	}

	public function install() {
		if(!parent::install() || !$this->registerHooks() || !Configuration::updateValue('ulogin_default_options', serialize($this->ulogin_default_options)) || !Configuration::updateValue('ulogin_options', serialize($this->ulogin_options)))
			return false;
		//создаём таблицу для uLogin
		$sql = 'CREATE TABLE IF NOT EXISTS`' . _DB_PREFIX_ . 'customer_ulogin_table` (
            `id` int(20) UNSIGNED NOT NULL auto_increment,
            `userid` int (20) unsigned NOT NULL,
            `identity` varchar(250) NOT NULL,
            `network` varchar(20),
            PRIMARY KEY  (id),
            UNIQUE KEY `identity` (identity)
            ) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8
        ';
		// исполняем запрос
		if(!Db::getInstance()->Execute(trim($sql)))
			return false;
		//создаём таблицу для группы uLogin
		if(!$this->addulogingroup())
			return false;

		return true;
	}

	public function uninstall() {
		if(!parent::uninstall() || !Configuration::deleteByName('ulogin_default_options') || !Configuration::deleteByName('ulogin_options') || !$this->unregisterHooks())
			return false;

		return true;
	}

	public function getContent() {
		$output = null;
		$ulogin_options = array();
		if(Tools::isSubmit('submit' . $this->name)) {
			$ulogin_options['ulogin_id1'] = Tools::getValue('ulogin_id1');
			$ulogin_options['ulogin_id2'] = Tools::getValue('ulogin_id2');
			$ulogin_options['ulogin_label'] = Tools::getValue('ulogin_label');
			$ulogin_options['ulogin_check_mail'] = Tools::getValue('ulogin_check_mail_');
			$ulogin_options['id_default_group'] = Tools::getValue('id_default_group');
			if(count($this->errors))
				foreach($this->errors as $err)
					$output .= $this->displayError($err); else {
				Configuration::updateValue('ulogin_options', serialize($ulogin_options));
				$output .= $this->displayConfirmation($this->l('Настройки сохранены'));
			}
		}

		return $output . $this->displayForm();
	}

	public function displayForm() {
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		$check_mail = (int)Configuration::get('PS_CUSTOMER_CREATION_EMAIL') == 1 ? $this->l('включен') : $this->l('выключен');
		$groups = Group::getGroups($this->context->language->id, true);
		//ширина полей в админке модуля
		$ulogin_col = '6';
		// Описываем поля формы для страници настроек
		$fields_form = array();
		$fields_form[0]['form'] = array('legend' => array('title' => $this->l('Настройки плагина uLogin'), 'icon' => 'icon-cogs'), 'input' => array(array('type' => 'free', 'label' => '<span class="h3">ID uLogin`а из <a href="http://ulogin.ru/lk.php"
						target="_blank">Личного кабинета</a></span>', 'col' => $ulogin_col, 'desc' => '<span style="color: red">' . $this->l('Внимание!') . '</span> ' . $this->l('Перед установкой uLogin ID, в настройках виджета') . ' ' . $this->l('в') . ' <a href="http://ulogin.ru/lk.php" target="_blank">' . $this->l('Личном кабинете') . '</a> ' . $this->l('необходимо указать') . ' <b>' . $this->l('email') . '</b> ' . $this->l('в возвращаемых полях') . $this->l('т.к. по умолчанию этот параметр отключён.'), 'name' => 'text',), array('type' => 'text', 'label' => $this->l('uLogin ID форма входа для панели навигации:'), 'name' => 'ulogin_id1', 'required' => false, 'col' => $ulogin_col, 'desc' => $this->l('Идентификатор виджета для панели навигации
						 (хук Nav). Пустое поле - виджет по умолчанию'),), array('type' => 'text', 'label' => $this->l('uLogin ID общая форма:'), 'name' => 'ulogin_id2', 'col' => $ulogin_col, 'desc' => $this->l('Идентификатор виджета для любого хука. Пустое
						поле - виджет по умолчанию')), array('type' => 'free', 'label' => '<span class="h3">' . $this->l('Другие параметры') . '</span>', 'name' => 'text',), array('type' => 'text', 'label' => $this->l('Текст:'), 'name' => 'ulogin_label', 'col' => $ulogin_col, 'desc' => $this->l('Текст типа "Войти с помощью:"')), array('type' => 'checkbox', 'label' => $this->l('Отправлять письмо при регистрации новому пользователю:'), 'name' => 'ulogin_check_mail', 'col' => $ulogin_col, 'desc' => $this->l('В настройках магазина этот параметр - ') . $check_mail, 'values' => array('query' => array(array('name' => 'mail', 'val' => '1', 'checked' => 'checked'),),)), array('type' => 'select', 'label' => $this->l('Группа клиентов по умолчанию'), 'name' => 'id_default_group', 'options' => array('query' => $groups, 'id' => 'id_group', 'name' => 'name'), 'col' => $ulogin_col, 'desc' => $this->l('Для пользователей авторизованных с помощью uLogin эта группа
						 будет группой по умолчанию.')),), 'submit' => array('title' => $this->l('Сохранить настройки'), 'class' => 'button'));
		$helper = new HelperForm();
		// Module, token и currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
		// Язык
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;
		// Заголовок и toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;        // false - убирает toolbar
		$helper->toolbar_scroll = true;      // toolbar виден всегда наверху экрана.
		$helper->submit_action = 'submit' . $this->name;
		$helper->toolbar_btn = array('save' => array('desc' => $this->l('Сохранить'), 'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),), 'back' => array('href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'), 'desc' => $this->l('Венуться к списку')));
		$ulogin_gr = Db::getInstance()->getValue('SELECT id_group FROM ' . _DB_PREFIX_ . 'group_lang WHERE name = "' . $this->name . '"');
		// Загружаем нужные нам значения из базы
		$ulogin_options = unserialize(Configuration::get('ulogin_options'));
		$helper->fields_value['ulogin_id1'] = $ulogin_options['ulogin_id1'];
		$helper->fields_value['ulogin_id2'] = $ulogin_options['ulogin_id2'];
		$helper->fields_value['ulogin_label'] = $ulogin_options['ulogin_label'];
		$helper->fields_value['ulogin_check_mail_'] = ($ulogin_options['ulogin_check_mail'] == '1' ? true : false);
		$helper->fields_value['id_default_group'] = $ulogin_options['id_default_group'] == '' ? $ulogin_gr : $ulogin_options['id_default_group'];

		return $helper->generateForm($fields_form);
	}

	/**
	 * Возвращает back url
	 */
	public function uloginCurrentPageUrl() {
		$page_url = trim(Tools::getValue('back'));
		if(!empty($page_url))
			return $page_url; else if(Tools::getValue('backurl'))
			return Tools::getValue('backurl');
		$page_url = 'http';
		if(isset($_SERVER['HTTPS']))
			if($_SERVER['HTTPS'] == 'on')
				$page_url .= 's';
		$page_url .= '://';
		if($_SERVER['SERVER_PORT'] != '80')
			$page_url .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI']; else
			$page_url .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

		return $page_url;
	}

	public function drawPanel($place = 0, $with_label = true) {
		$ulogin_options = unserialize(Configuration::get('ulogin_options'));
		$ulogin_default_options = unserialize(Configuration::get('ulogin_default_options'));
		$default_panel = false;
		switch($place) {
			case 0:
				$ulogin_id = $ulogin_options['ulogin_id1'];
				break;
			case 1:
				$ulogin_id = $ulogin_options['ulogin_id2'];
				break;
			default:
				$ulogin_id = $ulogin_options['ulogin_id1'];
		}
		if(empty($ulogin_id)) {
			$ul_options = $ulogin_default_options;
			$default_panel = true;
		}
		$panel = $with_label ? '<div class="ulogin_label">' . $ulogin_options['ulogin_label'] . '&nbsp;</div>' : '';
		$plusone = urlencode($this->uloginCurrentPageUrl());
		$redirect_uri = urlencode(_PS_BASE_URL_ . '/?ulogin=token&backurl=' . $plusone);
		$panel .= '<div class="ulogin_panel"';
		if($default_panel) {
			$ul_options['redirect_uri'] = $redirect_uri;
			unset($ul_options['label']);
			$x_ulogin_params = '';
			foreach($ul_options as $key => $value)
				$x_ulogin_params .= $key . '=' . $value . ';';
			if($ul_options['display'] != 'window')
				$panel .= ' data-ulogin="' . $x_ulogin_params . '"></div>'; else
				$panel .= ' data-ulogin="' . $x_ulogin_params . '" href="#"><img src="https://ulogin.ru/img/button.png" width=187 height=30 alt="МультиВход"/></div>';
		} else
			$panel .= ' data-uloginid="' . $ulogin_id . '" data-ulogin="redirect_uri=' . $redirect_uri . '"></div>';
		$panel = '<div class="ulogin_block place' . $place . '">' . $panel . '</div>'; //<div style="clear:both"></div>
		$this->context->smarty->assign('panel', $panel);

		return $this->display(__FILE__, 'panel.tpl');
	}

	public function hookDisplayHeader() {
		$this->context->controller->addCSS($this->_path . 'views/css/ulogin.css', 'all');
		$this->context->controller->addJS('https://ulogin.ru/js/ulogin.js');

		if(!Context::getContext()->customer->isLogged()) {
			$this->context->controller->addJS($this->_path . 'views/js/ulogin.js');
			$this->drawPanel();
		}

		$currcontroller = Tools::strtolower(get_class($this->context->controller));
		if($currcontroller == 'uloginprofilemodulefrontcontroller') {
			$this->context->controller->addCSS('https://ulogin.ru/css/providers.css');
			$this->context->controller->addJS($this->_path . 'views/js/ulogin.js');
		}
	}

	public function hookDisplayNav() {
		if(!Context::getContext()->customer->isLogged())
			return $this->drawPanel(0);
		return false;
	}

	public function hookDisplayFooter() {
		$currcontroller = Tools::strtolower(get_class($this->context->controller));
		if($currcontroller == 'authcontroller')
			$this->drawPanel(1);
		if($currcontroller == 'uloginprofilemodulefrontcontroller') {
			$this->drawPanel(1, false);
			$this->getUloginUserAccountsPanel();
		}
		$this->uloginParseRequest();
	}

	public function hookDisplayCustomerAccount() {
		$this->smarty->assign('in_footer', false);

		return $this->display(__FILE__, 'ulogin-my-account.tpl');
	}

	public function hookDisplayMyAccountBlock() {
		$this->smarty->assign('in_footer', true);

		return $this->display(__FILE__, 'ulogin-my-account.tpl');
	}

	/**
	 * Обменивает токен на пользовательские данные
	 * @param bool $token
	 * @return bool|mixed|string
	 */
	public function uloginGetUserFromToken($token = false) {
		$response = false;
		if($token) {
			$data = array('cms' => 'prestashop', 'version' => _PS_VERSION_,);
			$request = 'http://ulogin.ru/token.php?token=' . $token . '&host=' . $_SERVER['HTTP_HOST'] . '&data=' . base64_encode(Tools::jsonEncode($data));
			if(function_exists('curl_init')) {
				if(in_array('curl', get_loaded_extensions())) {
					$c = curl_init($request);
					curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
					$response = curl_exec($c);
					curl_close($c);
				} elseif(function_exists('file_get_contents') && ini_get('allow_url_fopen'))
					$response = Tools::file_get_contents($request);
			} else {
				$this->errors = $this->l('Ошибка: "culr" на сервере не обнаружен');

				return false;
			}
		}

		return $response;
	}

	/**
	 * Проверка пользовательских данных, полученных по токену
	 * @param $u_user - пользовательские данные
	 * @return bool
	 */
	public function uloginCheckTokenError($u_user) {
		if(!is_array($u_user)) {
			$this->errors = $this->l('Ошибка работы uLogin: Данные о пользователе содержат неверный
			 формат.');

			return false;
		}
		if(isset($u_user['error'])) {
			$strpos = strpos($u_user['error'], 'host is not');
			if($strpos) {
				$this->errors = $this->l('Ошибка работы uLogin: адрес хоста не совпадает с оригиналом');

				return false;
			}
			switch($u_user['error']) {
				case 'token expired':
					$this->errors = $this->l('Ошибка работы uLogin: время жизни токена истекло');
				case 'invalid token':
					$this->errors = $this->l('Ошибка работы uLogin: неверный токен');
				default:
					$this->errors = $this->l('Ошибка работы uLogin:') . $u_user['error'];
			}

			return false;
		}
		if(!isset($u_user['identity'])) {
			$this->errors = $this->l('Ошибка работы uLogin: В возвращаемых данных отсутствует переменная
			 "identity"');

			return false;
		}

		return true;
	}

	/**
	 * Регистрация на сайте и в таблице uLogin
	 * @param Array $u_user - данные о пользователе, полученные от uLogin
	 * @param int $in_db - при значении 1 необходимо переписать данные в таблице uLogin
	 * @return bool|int|Error
	 */
	public function uloginRegistrationUser($u_user, $in_db = 0) {
		if(!isset($u_user['email'])) {
			$this->errors = $this->l('Через данную форму выполнить вход/регистрацию невозможно.') . '<br/>' . $this->l('Сообщите администратору сайта о следующей ошибке:') . '<br/>' . $this->l('Необходимо указать "email" в возвращаемых полях uLogin');
			$this->context->smarty->assign('ulogin_message', $this->errors);

			return false;
		}
		$network = isset($u_user['network']) ? $u_user['network'] : '';
		// данные о пользователе есть в ulogin_table, но отсутствуют в WP
		if($in_db == 1)
			Db::getInstance()->delete(_DB_PREFIX_ . 'customer_ulogin_table', 'identity = "' . urlencode($u_user['identity']) . '"');
		$user_id = Db::getInstance()->getValue('SELECT id_customer FROM ' . _DB_PREFIX_ . 'customer WHERE email = "' . $u_user['email'] . '"');
		// $check_m_user == true -> есть пользователь с таким email
		$check_m_user = $user_id > 0 ? true : false;
		$current_user = (int)$this->context->cookie->id_customer;
		// $is_logged_in == true -> ползователь онлайн
		$is_logged_in = Context::getContext()->customer->isLogged();
		if(($check_m_user == false) && !$is_logged_in) {
			$user_pass = Tools::passwdGen();
			$customer = new CustomerCore();
			$customer->firstname = $u_user['first_name'];
			$customer->lastname = $u_user['last_name'];
			if(isset($u_user['sex'])) {
				switch($u_user['sex']) {
					case 0:
						$customer->id_gender = 0; //unknown
						break;
					case 1:
						$customer->id_gender = 2; //female
						break;
					case 2:
						$customer->id_gender = 1; //male
						break;
				}
			}
			if(isset($u_user['bdate'])) //выбор др, если есть
				$customer->birthday = date('Y-m-d', strtotime($u_user['bdate']));
			$customer->optin = Configuration::get('PS_CUSTOMER_OPTIN');
			$customer->active = true;
			$customer->deleted = false;
			$customer->is_guest = (Tools::isSubmit('is_new_customer') ? !Tools::getValue('is_new_customer', 1) : 0);
			$customer->passwd = Tools::encrypt($user_pass);
			$customer->email = $u_user['email'];
			Configuration::get('PS_CUSTOMER_NWSL') == 1 ? $customer->newsletter = true : $customer->newsletter = false;
			// отсутствует пользователь с таким email в базе PrestaShop -> регистрация -> вход
			if($customer->add()) {
				$ulogin_options = unserialize(Configuration::get('ulogin_options'));
				if(empty($ulogin_options['id_default_group'])) {
					$ulogin_gr_id = Db::getInstance()->getValue('SELECT id_group FROM ' . _DB_PREFIX_ . 'group_lang WHERE name = "' . $this->name . '"');
					$ulogin_options['id_default_group'] = $ulogin_gr_id;
				}
				$addugr = Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'customer SET id_default_group = "' . $ulogin_options['id_default_group'] . '" WHERE id_customer = "' . $customer->id . '"');
				$adducustomergr = Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'customer_group SET id_group = "' . $ulogin_options['id_default_group'] . '" WHERE id_customer = "' . $customer->id . '"');
				if(!$addugr)
					$this->errors = $this->l('Ошибка при добавлении пользователя в группу ulogin');
				if(!$adducustomergr)
					$this->errors = $this->l('Ошибка при добавлении пользователя в группу ulogin');
				if(!$this->sendConfirmationMail($customer))
					$this->errors = $this->l('Ошибка при отправке письма');
				$this->uloginInsertRow($customer->id, $u_user['identity'], $network);

				return $customer->id;
			}
		} else {
			if(!isset($u_user['verified_email']) || $u_user['verified_email'] != 1) {
				$this->errors = $this->l('Электронный адрес данного аккаунта совпадает с электронным адресом
					 существующего пользователя') . '<br>' . $this->l('Требуется подтверждение на владение указанным email.');
				$this->context->smarty->assign(array('message' => ('<script src="//ulogin.ru/js/ulogin.js"
				type="text/javascript"></script><script type="text/javascript">uLogin.mergeAccounts("' . Tools::getValue('token') . '")</script>'), 'ulogin_message' => $this->errors));
			}
			if($u_user['verified_email'] == 1) {
				$user_id = $is_logged_in ? $current_user : $user_id;
				$other_u = Db::getInstance()->getValue('SELECT identity FROM ' . _DB_PREFIX_ . 'customer_ulogin_table WHERE userid = "' . $user_id . '"');
				if($other_u) {
					if(!$is_logged_in && !isset($u_user['merge_account'])) {
						$this->errors = $this->l('С данным аккаунтом уже связаны данные из другой социальной сети.') . '<br>' . $this->l('Требуется привязка новой учётной записи социальной сети к этому аккаунту.');
						$this->context->smarty->assign(array('message' => ('<script src="//ulogin.ru/js/ulogin.js"
						type="text/javascript"></script><script type="text/javascript">uLogin.mergeAccounts("' . Tools::getValue('token') . '","' . $other_u . '")</script>'), 'ulogin_message' => $this->errors));

						return false;
					}
				}
				$this->uloginInsertRow($user_id, $u_user['identity'], $network);

				return $user_id;
			}
		}

		return false;
	}

	/**
	 * Добавление новой привязки uLogin
	 * в случае успешного выполнения возвращает $user_id иначе - false с выводом ошибки
	 * @param $user_id
	 * @param $identity
	 * @param string $network
	 * @return bool
	 */
	public function uloginInsertRow($user_id, $identity, $network = '') {
		if($user_id > 0) {
			$result = Db::getInstance()->insert('customer_ulogin_table', array('userid' => $user_id, 'identity' => urlencode($identity), 'network' => $network));
			if($result != false)
				return $user_id;
		} else
			return false;

		return '';
	}

	/**
	 * Обновление данных о пользователе и вход
	 * @param $u_user - данные о пользователе, полученные от uLogin
	 * @param $id_customer - идентификатор пользователя
	 * @return string
	 */
	public function loginCustomer($u_user, $id_customer) {
		// Убедимся, что пользователь действительно добавился в базу.
		$result = Db::getInstance()->GetRow('SELECT * FROM ' . _DB_PREFIX_ . 'customer WHERE id_customer = "' . $id_customer . '"');
		if(!empty ($result['id_customer']) && ($id_customer == $result['id_customer'])) {
			$customer = new Customer();
			$customer->id = $result['id_customer'];
			foreach($result as $key => $value) {
				if(array_key_exists($key, $customer)) {
					switch($key) {
						case 'birthday':
							if($value == $u_user['bdate'])
								$customer->birthday = $value; else {
								if(!empty($u_user['bdate']) && isset($u_user['bdate'])) {
									$customer->birthday = date('Y-m-d', strtotime($u_user['bdate']));
									Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'customer SET birthday = "' . $customer->birthday . '" WHERE id_customer = "' . $id_customer . '"');
								}
							}
							break;
						case 'id_gender':
							switch($u_user['sex']) {
								case 0:
									$id_gender = 0; //unknown
									break;
								case 1:
									$id_gender = 2; //female
									break;
								case 2:
									$id_gender = 1; //male
									break;
							}
							if($value == $id_gender)
								$customer->id_gender = $value; else {
								if(!empty($id_gender) && isset($u_user['sex'])) {
									$customer->id_gender = $id_gender;
									Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'customer SET id_gender = "' . $customer->id_gender . '" WHERE id_customer = "' . $id_customer . '"');
								}
							}
							break;
						default:
							$customer->{$key} = $value;
					}
				}
			}
			Hook::exec('actionBeforeAuthentication');
			$context = Context::getContext();
			$context->cookie->id_compare = isset($context->cookie->id_compare) ? $context->cookie->id_compare : CompareProduct::getIdCompareByIdCustomer($customer->id);
			$context->cookie->id_customer = $customer->id;
			$context->cookie->customer_lastname = $customer->lastname;
			$context->cookie->customer_firstname = $customer->firstname;
			$context->cookie->logged = 1;
			$customer->logged = 1;
			$context->cookie->is_guest = $customer->isGuest();
			$context->cookie->passwd = $customer->passwd;
			$context->cookie->email = $customer->email;
			// добавляем пользователя в context
			$context->customer = $customer;
			if(Configuration::get('PS_CART_FOLLOWING') && (empty($context->cookie->id_cart) || Cart::getNbProducts($context->cookie->id_cart) == 0) && $id_cart = (int)Cart::lastNoneOrderedCart($context->customer->id))
				$context->cart = new Cart($id_cart); else {
				$context->cart->id_carrier = 0;
				$context->cart->setDeliveryOption(null);
				$context->cart->id_address_delivery = Address::getFirstCustomerAddressId($customer->id);
				$context->cart->id_address_invoice = Address::getFirstCustomerAddressId($customer->id);
			}
			$context->cart->id_customer = (int)$customer->id;
			$context->cart->secure_key = $customer->secure_key;
			$context->cart->save();
			$context->cookie->id_cart = (int)$context->cart->id;
			$context->cookie->update();
			$context->cart->autosetProductAddress();
			Hook::exec('actionAuthentication');
			// Проверяем корзину, если сменился пользователь
			CartRule::autoRemoveFromCart($context);
			CartRule::autoAddToCart($context);
			// Залонились, обновляем страничку c которой залогинились с передачей ошибок.
			if(Tools::getValue('backurl')) {
				$back = Tools::getValue('backurl');
				$ex = explode('&err', $back);
				$back = $ex[0];
				if(!empty($this->errors))
					$back .= (Tools::strpos($back, '?') !== false ? '&' : '?') . 'err=' . urlencode($this->errors);
				Tools::redirect($back);
			}
			Tools::redirect('index.php');
		}

		// Ошибка во время авторизации
		return $this->errors = 'Ошибка во время авторизации';
	}

	/**
	 * @param $user_id
	 * @return bool
	 */
	public function uloginCheckUserId($user_id) {
		$current_user = (int)$this->context->cookie->id_customer;
		if(($current_user > 0) && ($user_id > 0) && ($current_user != $user_id)) {
			$this->errors = $this->l('Данный аккаунт привязан к другому пользователю.') . '<br/>' . $this->l('Вы не можете использовать этот аккаунт');
			$this->context->smarty->assign('ulogin_message', $this->errors);

			return false;
		}

		return true;
	}

	/**
	 * Обработка ответа сервера авторизации
	 */
	public function uloginParseRequest() {
		if(!Tools::getIsset('token'))
			return;  // не был получен токен uLogin
		$s = $this->uloginGetUserFromToken(Tools::getValue('token'));
		if(!$s) {
			$this->errors = '<b>' . $this->l('Ошибка работы uLogin:') . '</b></br></br>' . $this->l('Не удалось получить данные о пользователе с помощью токена.');
			$this->context->smarty->assign('ulogin_message', $this->errors);

			return false;
		}
		$u_user = Tools::jsonDecode($s, true);
		$check = $this->uloginCheckTokenError($u_user);
		if(!$check) {
			$this->context->smarty->assign('ulogin_message', $this->errors);

			return false;
		}
		$row = Db::getInstance()->getRow('SELECT userid FROM ' . _DB_PREFIX_ . 'customer_ulogin_table WHERE identity = "' . urlencode($u_user['identity']) . '"');
		$user_id = $row['userid'];
		if(isset($user_id)) {
			$wp_user = Db::getInstance()->getRow('SELECT id_customer FROM ' . _DB_PREFIX_ . 'customer WHERE id_customer = "' . $user_id . '"');
			if($user_id > 0 && $wp_user['id_customer'] > 0)
				$this->uloginCheckUserId($user_id); else
				$user_id = $this->uloginRegistrationUser($u_user, 1);
		} else
			$user_id = $this->uloginRegistrationUser($u_user);
		if($user_id > 0)
			$this->loginCustomer($u_user, $user_id);
		if(Tools::getValue('err'))
			$this->context->smarty->assign('ulogin_message', Tools::getValue('err'));
	}

	/**
	 * Вывод списка аккаунтов пользователя
	 * @param int $user_id - ID пользователя (если не задан - текущий пользователь)
	 * @return string
	 */
	public function getUloginUserAccountsPanel($user_id = 0) {
		$current_user = (int)$this->context->cookie->id_customer;
		$user_id = empty($user_id) ? $current_user : $user_id;
		if(empty($user_id))
			return '';
		$use_cache = false;
		$networks = Db::getInstance()->ExecuteS('SELECT network FROM ' . _DB_PREFIX_ . 'customer_ulogin_table WHERE userid = ' . (int)$user_id, $networks = true, $use_cache);
		$output = '';
		if($networks) {
			$output .= '<div id="ulogin_accounts">';
			foreach($networks as $network)
				$output .= "<div data-ulogin-network='{$network['network']}'
			class='ulogin_network big_provider {$network['network']}_big'></div>";
			$output .= '</div>';
		}
		$this->context->smarty->assign(array('syncpanel' => $output,));

		return '';
	}

	/*
	 * Высылает подтверждение по почте при регистрации пользователя, если стоит
	 *  галочка в настройках uLogina
	 * @param $customer
	 * @return bool
	 */
	protected function sendConfirmationMail($customer) {
		$uopt = unserialize(Configuration::get('ulogin_options'));
		if($uopt['ulogin_check_mail'] == 0)
			return true;

		return Mail::Send($this->context->language->id, 'account', Mail::l('Добро пожаловать!'), array('{firstname}' => $customer->firstname, '{lastname}' => $customer->lastname, '{email}' => $customer->email, '{passwd}' => Tools::getValue('passwd')), $customer->email, $customer->firstname . ' ' . $customer->lastname);
	}
}

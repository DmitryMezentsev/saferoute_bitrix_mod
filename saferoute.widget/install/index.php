<?php

use \Bitrix\Main\Application;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\ModuleManager;
use \Bitrix\Main\EventManager;
use \Bitrix\Main\Loader;
use \Bitrix\Main\Entity\Base;
use \Bitrix\Main\Config\Option;
use \Bitrix\Main\IO\Directory;
use \Bitrix\Sale\Delivery\Services\Manager as DeliveryManager;

Loc::loadMessages(__FILE__);

class ddeliveryru_widget extends CModule
{
	var $MODULE_ID = 'ddeliveryru.widget';
	
	const SALE_PAY_SYSTEM_ACTION_FILE = '/bitrix/php_interface/include/sale_payment/DDelivery';
	const DELIVERY_SRV_CLASS_NAME = '\Sale\Handlers\Delivery\DdeliveryHandler';
	
	
	// ID загруженного в Битрикс файла с логотипом DDelivery
	private $ddelivery_logo_id;
	
	
	/**
	 * Проверка, что данная версия Битрикс содержит в себе ядро D7
	 * 
	 * @return bool
	 */
	private function isD7()
	{
		return CheckVersion(ModuleManager::getVersion('main'), '14.00.00');
	}
	
	/**
	 * Возвращает путь к корню модуля
	 * 
	 * @return string
	 */
	private function GetPath()
	{
		return dirname(__DIR__);
	}
	
	
	/***************************
	 * Методы установки модуля *
	 ***************************/
	
	/**
	 * Загружает логотип DDelivery в систему
	 */
	private function LoadLogo()
	{
		$this->ddelivery_logo_id = CFile::SaveFile([
			'name'        => 'logo.png',
			'tmp_name'    => __DIR__ . DIRECTORY_SEPARATOR . 'logo.png',
			'type'        => 'image/png',
			'del'         => false,
			'MODULE_ID'   => $this->MODULE_ID,
			'description' => 'DDelivery logo',
		], 'ddelivery');
	}
	
	/**
	 * Создание таблиц и записей в БД
	 */
	public function InstallDB()
	{
		Loader::includeModule($this->MODULE_ID);
		
		// Создание таблицы с DDelivery ID заказов
		if(!Application::getConnection()->isTableExists(Base::getInstance('\DDeliveryru\Widget\DDeliveryOrderTable')->getDBTableName()))
		{
			Base::getInstance('\DDeliveryru\Widget\DDeliveryOrderTable')->createDbTable();
		}
		
		if(Loader::includeModule('sale'))
		{
			// Добавление способа доставки DDelivery
			DeliveryManager::add([
				'NAME'        => 'DDelivery',
				'ACTIVE'      => 'Y',
				'DESCRIPTION' => Loc::getMessage('DDELIVERY_WIDGET_DELIVERY_SERV_DESC'),
				'CURRENCY'    => 'RUB',
				'LOGOTIP'     => $this->ddelivery_logo_id,
				'CLASS_NAME'  => '\Bitrix\Sale\Delivery\Services\Configurable',
			]);
			
			// Добавление способа оплаты
			/* Отключено, пока виджет не поддерживает эквайринг
			CSalePaySystemAction::Add([
				'NAME'        => 'DDelivery',
				'NEW_WINDOW'  => 'N',
				'DESCRIPTION' => Loc::getMessage('DDELIVERY_WIDGET_PAY_SYSTEM_DESC'),
				'ACTION_FILE' => self::SALE_PAY_SYSTEM_ACTION_FILE,
				'IS_CASH'     => 'A',
			]);
			*/
		}
		
		// Установка начальных значений настроек модуля
		Option::set($this->MODULE_ID, 'ord_prop_code_fio', 'FIO');
		Option::set($this->MODULE_ID, 'ord_prop_code_location', 'LOCATION');
		Option::set($this->MODULE_ID, 'ord_prop_code_phone', 'PHONE');
		Option::set($this->MODULE_ID, 'ord_prop_code_city', 'CITY');
		Option::set($this->MODULE_ID, 'ord_prop_code_address', 'ADDRESS');
		Option::set($this->MODULE_ID, 'ord_prop_code_zip', 'ZIP');
	}
	
	/**
	 * Установка обработчиков событий
	 */
	public function InstallEvents()
	{
		EventManager::getInstance()->registerEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\DDeliveryru\Widget\Common', 'onSaleOrderSaved');
		EventManager::getInstance()->registerEventHandler('main', 'OnPageStart', $this->MODULE_ID, '\DDeliveryru\Widget\Common', 'onPageStart');
	}
	
	/**
	 * Копирование файлов
	 */
	public function InstallFiles()
	{
		// CSS/JS
		CopyDirFiles($this->GetPath() . '/install/css', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/' . $this->MODULE_ID, true, true);
		CopyDirFiles($this->GetPath() . '/install/js', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . $this->MODULE_ID, true, true);
		
		// PHP
		CopyDirFiles($this->GetPath() . '/install/api', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/' . $this->MODULE_ID . '/api', true, true);
		CopyDirFiles($this->GetPath() . '/install/sale_delivery', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/sale_delivery/ddelivery', true, true);
		
		// Способ оплаты
		/* Отключено, пока виджет не поддерживает эквайринг
		CopyDirFiles($this->GetPath() . '/install/sale_payment', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/sale_payment/DDelivery', true, true);
		*/
	}
	
	
	/***************************
	 * Методы удаления модуля *
	 ***************************/
	
	/**
	 * Удаляет логотип DDelivery из системы
	 */
	private function RemoveLogo()
	{
		$files = CFile::GetList([], ['MODULE_ID' => $this->MODULE_ID]);
		
		if($files->arResult)
		{
			foreach($files->arResult as $file)
			{
				CFile::Delete($file['ID']);
			}
		}
	}
	
	/**
	 * Удаление таблиц и записей из БД
	 * 
	 * @param $save_tables bool Флаг сохранения таблиц с данными
	 */
	public function UnInstallDB($save_tables = false)
	{
		Loader::includeModule($this->MODULE_ID);
		
		if(!$save_tables)
		{
			// Удаление таблицы с DDelivery ID заказов
			Application::getConnection()->queryExecute(
				'DROP TABLE IF EXISTS ' . Base::getInstance('\DDeliveryru\Widget\DDeliveryOrderTable')->getDBTableName()
			);
		}
		
		// Удаление настроек модуля
		Option::delete($this->MODULE_ID);
		
		if(Loader::includeModule('sale'))
		{
			// Удаление способа доставки DDelivery
			$delivery_services = DeliveryManager::getActiveList();
			foreach($delivery_services as $delivery_service)
			{
				if($delivery_service['CLASS_NAME'] === self::DELIVERY_SRV_CLASS_NAME)
					DeliveryManager::delete($delivery_service['ID']);
			}
			
			// Удаление способа оплаты
			/* Отключено, пока виджет не поддерживает эквайринг
			$pay_systems = CSalePaySystemAction::GetList([], ['=ACTION_FILE' => self::SALE_PAY_SYSTEM_ACTION_FILE]);
			foreach($pay_systems->arResult as $pay_system)
			{
				CSalePaySystemAction::Delete($pay_system['ID']);
			}
			*/
		}
	}
	
	/**
	 * Удаление обработчиков событий
	 */
	public function UnInstallEvents()
	{
		EventManager::getInstance()->unRegisterEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\DDeliveryru\Widget\Common', 'onSaleOrderSaved');
		EventManager::getInstance()->unRegisterEventHandler('main', 'OnPageStart', $this->MODULE_ID, '\DDeliveryru\Widget\Common', 'onPageStart');
	}
	
	/**
	 * Удаление файлов
	 */
	public function UnInstallFiles()
	{
		// CSS/JS
		Directory::deleteDirectory($_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/' . $this->MODULE_ID);
		Directory::deleteDirectory($_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . $this->MODULE_ID);
		
		// PHP
		Directory::deleteDirectory($_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/' . $this->MODULE_ID);
		Directory::deleteDirectory($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/sale_delivery/ddelivery');
		
		// Способ оплаты
		/* Отключено, пока виджет не поддерживает эквайринг
		Directory::deleteDirectory($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/sale_payment/DDelivery');
		*/
	}
	
	
	function __construct()
	{
		include(__DIR__ . '/version.php');
		
		$this->MODULE_VERSION      = $arModuleVersion['VERSION'];
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
		$this->PARTNER_NAME        = Loc::getMessage('DDELIVERY_PARTNER_NAME');
		
		$this->MODULE_NAME         = Loc::getMessage('DDELIVERY_WIDGET_MOD_NAME');
		$this->MODULE_DESCRIPTION  = Loc::getMessage('DDELIVERY_WIDGET_MOD_DESCRIPTION');
		$this->PARTNER_URI         = Loc::getMessage('DDELIVERY_WIDGET_PARTNER_URI');
		
		if(Loader::includeModule('sale'))
		{
			// Установка для способа доставки CODE и CLASS_NAME
			// (да, костыль, но в InstallDB() оно нихера не работает)
			foreach(DeliveryManager::getActiveList() as $d)
			{
				if(($d['ID'] === $d['CODE'] || !$d['CODE']) && $d['NAME'] === 'DDelivery')
				{
					DeliveryManager::update($d['ID'], [
						'CODE' => 'DDelivery',
						'CLASS_NAME' => self::DELIVERY_SRV_CLASS_NAME,
					]);
				}
			}
		}
	}
	
	/**
	 * Устанавливает модуль
	 */
	public function DoInstall()
	{
		global $APPLICATION;
		
		if($this->isD7())
		{
			ModuleManager::registerModule($this->MODULE_ID);
			
			$this->LoadLogo();
			$this->InstallFiles();
			$this->InstallDB();
			$this->InstallEvents();
		}
		else
		{
			$APPLICATION->ThrowException(Loc::getMessage('DDELIVERY_WIDGET_ERROR_VERSION'));
		}
		
		$APPLICATION->IncludeAdminFile(Loc::getMessage('DDELIVERY_WIDGET_INSTALL_TITLE'), $this->GetPath() . '/install/step.php');
	}
	
	/**
	 * Удаляет модуль
	 */
	public function DoUninstall()
	{
		global $APPLICATION;
		
		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		
		if($request['step'] < 2)
		{
			$APPLICATION->IncludeAdminFile(Loc::getMessage('DDELIVERY_WIDGET_UNINSTALL_TITLE'), $this->GetPath() . '/install/unstep1.php');
		}
		elseif($request['step'] == 2)
		{
			$this->RemoveLogo();
			$this->UnInstallEvents();
			$this->UnInstallDB($request['savedata'] === 'Y');
			$this->UnInstallFiles();
			
			ModuleManager::unRegisterModule($this->MODULE_ID);
			
			$APPLICATION->IncludeAdminFile(Loc::getMessage('DDELIVERY_WIDGET_UNINSTALL_TITLE'), $this->GetPath() . '/install/unstep2.php');
		}
	}
}

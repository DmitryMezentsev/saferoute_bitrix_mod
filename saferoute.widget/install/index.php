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
use \Bitrix\Sale\Delivery\Services\Table as DeliveryTable;

Loc::loadMessages(__FILE__);

class saferoute_widget extends CModule
{
    var $MODULE_ID = 'saferoute.widget';

    const SALE_PAY_SYSTEM_ACTION_FILE = '/bitrix/php_interface/include/sale_payment/SafeRoute';
    const DELIVERY_SRV_CLASS_NAME = '\Sale\Handlers\Delivery\SaferouteHandler';

    const SAFEROUTE_DELIVERY_CODE         = 'SafeRoute';
    const SAFEROUTE_COURIER_DELIVERY_CODE = 'SafeRouteCourier';
    const SAFEROUTE_PICKUP_DELIVERY_CODE  = 'SafeRoutePickup';


    // ID загруженного в Битрикс файла с логотипом SafeRoute
    private $saferoute_logo_id;


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

    /**
     * Возвращает ID способа доставки по коду
     *
     * @param $code string
     * @return int|null
     */
    private function getDeliveryIDByCode($code)
    {
        $id = null;

        $result = DeliveryTable::getList();
        while($delivery = $result->fetch())
        {
            if($delivery['CODE'] === $code) $id = $delivery['ID'];
        }

        return $id;
    }


    /***************************
     * Методы установки модуля *
     ***************************/

    /**
     * Загружает логотип SafeRoute в систему
     */
    private function LoadLogo()
    {
        $this->saferoute_logo_id = CFile::SaveFile([
            'name'        => 'logo.png',
            'tmp_name'    => __DIR__ . DIRECTORY_SEPARATOR . 'logo.png',
            'type'        => 'image/png',
            'del'         => false,
            'MODULE_ID'   => $this->MODULE_ID,
            'description' => 'SafeRoute logo',
        ], 'saferoute');
    }

    /**
     * Создание таблиц и записей в БД
     */
    public function InstallDB()
    {
        global $DB;

        Loader::includeModule($this->MODULE_ID);

        // Создание таблицы с SafeRoute ID заказов
        if(!Application::getConnection()->isTableExists(Base::getInstance('\Saferoute\Widget\SafeRouteOrderTable')->getDBTableName()))
        {
            Base::getInstance('\Saferoute\Widget\SafeRouteOrderTable')->createDbTable();
        }

        if(Loader::includeModule('sale'))
        {
            // Добавление способов доставки SafeRoute
            $sr_deliveries = [];
            $deliveries_for_add = [
                [self::SAFEROUTE_DELIVERY_CODE, 'SAFEROUTE_WIDGET_DELIVERY_SERV_TITLE', 'SAFEROUTE_WIDGET_DELIVERY_SERV_DESC', 'Y'],
                [self::SAFEROUTE_COURIER_DELIVERY_CODE, 'SAFEROUTE_WIDGET_COURIER_DELIVERY_SERV_TITLE', 'SAFEROUTE_WIDGET_COURIER_DELIVERY_SERV_DESC', 'N'],
                [self::SAFEROUTE_PICKUP_DELIVERY_CODE, 'SAFEROUTE_WIDGET_PICKUP_DELIVERY_SERV_TITLE', 'SAFEROUTE_WIDGET_PICKUP_DELIVERY_SERV_DESC', 'N'],
            ];

            foreach($deliveries_for_add as $delivery_for_add)
            {
                $delivery_id = $this->getDeliveryIDByCode($delivery_for_add[0]);

                if($delivery_id)
                {
                    $sr_deliveries[] = $delivery_id;

                    DeliveryManager::update($delivery_id, [
                        'ACTIVE'  => $delivery_for_add[3],
                        'LOGOTIP' => $this->saferoute_logo_id,
                    ]);
                }
                else
                {
                    $sr_deliveries[] = DeliveryManager::add([
                        'NAME'        => Loc::getMessage($delivery_for_add[1]),
                        'DESCRIPTION' => Loc::getMessage($delivery_for_add[2]),
                        'ACTIVE'      => $delivery_for_add[3],
                        'CURRENCY'    => 'RUB',
                        'LOGOTIP'     => $this->saferoute_logo_id,
                        'CLASS_NAME'  => '\Bitrix\Sale\Delivery\Services\Configurable',
                    ])->getId();
                }
            }

            // Добавление способа оплаты
            $sr_payment_id = CSalePaySystemAction::Add([
                'NAME'        => 'SafeRoute',
                'DESCRIPTION' => Loc::getMessage('SAFEROUTE_WIDGET_PAY_SYSTEM_DESC'),
                'ACTION_FILE' => self::SALE_PAY_SYSTEM_ACTION_FILE,
                'NEW_WINDOW'  => 'N',
                'IS_CASH'     => 'N',
                'ACTIVE'      => 'N',
                'ENTITY_REGISTRY_TYPE' => 'ORDER',
            ]);

            if($sr_payment_id)
            {
                // Установка логотипа для способа оплаты
                $DB->Update('b_sale_pay_system_action', ['LOGOTIP' => $this->saferoute_logo_id], "WHERE ID=$sr_payment_id");

                // Установка ограничения по способу доставки для способа оплаты
                foreach ($sr_deliveries as $sr_delivery_id)
                {
                    $DB->Insert('b_sale_delivery2paysystem', [
                        'DELIVERY_ID'    => $sr_delivery_id,
                        'LINK_DIRECTION' => "'P'",
                        'PAYSYSTEM_ID'   => $sr_payment_id,
                    ]);
                }
                $DB->Insert('b_sale_service_rstr', [
                    'SERVICE_ID'   => $sr_payment_id,
                    'SERVICE_TYPE' => 1,
                    'CLASS_NAME'   => "'" . $DB->ForSql('\Bitrix\Sale\Services\PaySystem\Restrictions\Delivery') . "'",
                ]);
            }
        }

        // Установка начальных значений настроек модуля
        // Физ. лицо
        Option::set($this->MODULE_ID, 'ord_prop_code_fio', 'FIO');
        Option::set($this->MODULE_ID, 'ord_prop_code_location', 'LOCATION');
        Option::set($this->MODULE_ID, 'ord_prop_code_phone', 'PHONE');
        Option::set($this->MODULE_ID, 'ord_prop_code_email', 'EMAIL');
        Option::set($this->MODULE_ID, 'ord_prop_code_city', 'CITY');
        Option::set($this->MODULE_ID, 'ord_prop_code_address', 'ADDRESS');
        Option::set($this->MODULE_ID, 'ord_prop_code_zip', 'ZIP');
        // Юр. лицо
        Option::set($this->MODULE_ID, 'ord_prop_code_lp_company_name', 'COMPANY');
        Option::set($this->MODULE_ID, 'ord_prop_code_lp_tin', 'INN');
        Option::set($this->MODULE_ID, 'ord_prop_code_lp_contact_person', 'CONTACT_PERSON');
        Option::set($this->MODULE_ID, 'ord_prop_code_lp_email', 'EMAIL');
        Option::set($this->MODULE_ID, 'ord_prop_code_lp_phone', 'PHONE');
        Option::set($this->MODULE_ID, 'ord_prop_code_lp_zip', 'ZIP');
        Option::set($this->MODULE_ID, 'ord_prop_code_lp_city', 'CITY');
        Option::set($this->MODULE_ID, 'ord_prop_code_lp_delivery_address', 'ADDRESS');

        // Артикул товара
        Option::set($this->MODULE_ID, 'prod_prop_code_vendor_code', 'ARTNUMBER');
    }

    /**
     * Установка обработчиков событий
     */
    public function InstallEvents()
    {
        EventManager::getInstance()->registerEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\Saferoute\Widget\Common', 'onSaleOrderSaved');
        EventManager::getInstance()->registerEventHandler('main', 'OnPageStart', $this->MODULE_ID, '\Saferoute\Widget\Common', 'onPageStart');
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
        CopyDirFiles($this->GetPath() . '/install/sale_delivery', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/sale_delivery/saferoute', true, true);

        // Способ оплаты
        CopyDirFiles($this->GetPath() . '/install/sale_payment', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/sale_payment/SafeRoute', true, true);
    }


    /***************************
     * Методы удаления модуля *
     ***************************/

    /**
     * Удаляет логотип SafeRoute из системы
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
        global $DB;

        Loader::includeModule($this->MODULE_ID);

        if(!$save_tables)
        {
            // Удаление таблицы с SafeRoute ID заказов
            Application::getConnection()->queryExecute(
                'DROP TABLE IF EXISTS ' . Base::getInstance('\Saferoute\Widget\SafeRouteOrderTable')->getDBTableName()
            );
        }

        // Удаление настроек модуля
        Option::delete($this->MODULE_ID);

        if(Loader::includeModule('sale'))
        {
            // Удаление способов доставки SafeRoute
            $delivery_services = DeliveryTable::getList();
            foreach($delivery_services as $delivery_service)
            {
                if($delivery_service['CLASS_NAME'] === self::DELIVERY_SRV_CLASS_NAME)
                    DeliveryManager::delete($delivery_service['ID']);
            }
            // Деактивация того, что не было удалено
            foreach(DeliveryManager::getActiveList() as $delivery_service)
            {
                if($delivery_service['CLASS_NAME'] === self::DELIVERY_SRV_CLASS_NAME)
                    DeliveryManager::update($delivery_service['ID'], ['ACTIVE' => 'N']);
            }

            // Удаление способа оплаты
            $pay_systems = CSalePaySystemAction::GetList([], ['=ACTION_FILE' => self::SALE_PAY_SYSTEM_ACTION_FILE]);
            foreach($pay_systems->arResult as $pay_system)
            {
                if(CSalePaySystemAction::Delete($pay_system['ID']))
                {
                    $DB->Query("DELETE FROM `b_sale_service_rstr` WHERE `SERVICE_ID`=$pay_system[ID] AND SERVICE_TYPE=1");
                }
            }
        }
    }

    /**
     * Удаление обработчиков событий
     */
    public function UnInstallEvents()
    {
        EventManager::getInstance()->unRegisterEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\Saferoute\Widget\Common', 'onSaleOrderSaved');
        EventManager::getInstance()->unRegisterEventHandler('main', 'OnPageStart', $this->MODULE_ID, '\Saferoute\Widget\Common', 'onPageStart');
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
        Directory::deleteDirectory($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/sale_delivery/saferoute');

        // Способ оплаты
        Directory::deleteDirectory($_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/include/sale_payment/SafeRoute');
    }


    function __construct()
    {
        include(__DIR__ . '/version.php');

        $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->PARTNER_NAME        = Loc::getMessage('SAFEROUTE_PARTNER_NAME');

        $this->MODULE_NAME         = Loc::getMessage('SAFEROUTE_WIDGET_MOD_NAME');
        $this->MODULE_DESCRIPTION  = Loc::getMessage('SAFEROUTE_WIDGET_MOD_DESCRIPTION');
        $this->PARTNER_URI         = Loc::getMessage('SAFEROUTE_WIDGET_PARTNER_URI');

        if(Loader::includeModule('sale'))
        {
            // Установка для способов доставки CODE и CLASS_NAME
            // (да, костыль, но в InstallDB() оно нихера не работает)
            foreach(DeliveryTable::getList() as $d)
            {
                // Чтобы оно применялось только для новых, только что созданных доставок
                if($d['ID'] === $d['CODE'] || !$d['CODE'])
                {
                    if($d['NAME'] === Loc::getMessage('SAFEROUTE_WIDGET_COURIER_DELIVERY_SERV_TITLE'))
                    {
                        DeliveryManager::update($d['ID'], [
                            'CODE'       => self::SAFEROUTE_COURIER_DELIVERY_CODE,
                            'CLASS_NAME' => self::DELIVERY_SRV_CLASS_NAME,
                        ]);
                    }
                    elseif($d['NAME'] === Loc::getMessage('SAFEROUTE_WIDGET_PICKUP_DELIVERY_SERV_TITLE'))
                    {
                        DeliveryManager::update($d['ID'], [
                            'CODE'       => self::SAFEROUTE_PICKUP_DELIVERY_CODE,
                            'CLASS_NAME' => self::DELIVERY_SRV_CLASS_NAME,
                        ]);
                    }
                    elseif($d['NAME'] === Loc::getMessage('SAFEROUTE_WIDGET_DELIVERY_SERV_TITLE'))
                    {
                        DeliveryManager::update($d['ID'], [
                            'CODE'       => self::SAFEROUTE_DELIVERY_CODE,
                            'CLASS_NAME' => self::DELIVERY_SRV_CLASS_NAME,
                        ]);
                    }
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
            $APPLICATION->ThrowException(Loc::getMessage('SAFEROUTE_WIDGET_ERROR_VERSION'));
        }

        $APPLICATION->IncludeAdminFile(Loc::getMessage('SAFEROUTE_WIDGET_INSTALL_TITLE'), $this->GetPath() . '/install/step.php');
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
            $APPLICATION->IncludeAdminFile(Loc::getMessage('SAFEROUTE_WIDGET_UNINSTALL_TITLE'), $this->GetPath() . '/install/unstep1.php');
        }
        elseif($request['step'] == 2)
        {
            $this->RemoveLogo();
            $this->UnInstallEvents();
            $this->UnInstallDB($request['savedata'] === 'Y');
            $this->UnInstallFiles();

            ModuleManager::unRegisterModule($this->MODULE_ID);

            $APPLICATION->IncludeAdminFile(Loc::getMessage('SAFEROUTE_WIDGET_UNINSTALL_TITLE'), $this->GetPath() . '/install/unstep2.php');
        }
    }
}

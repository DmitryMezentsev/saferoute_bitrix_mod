<?php

namespace Saferoute\Widget;

use \Bitrix\Main\Config\Option;
use \Bitrix\Main\Loader;

/**
 * Основной класс модуля со всеми функциями
 */
class Common
{
    /**
     * ID модуля
     */
    const MOD_ID = 'saferoute.widget';

    /**
     * Возвращает значение переменной сессии
     *
     * @param $name string Имя переменной
     * @param $convert2win1251 bool Флаг необходимости конвертации значения в windows-1251
     * @return mixed
     */
    private static function session($name, $convert2win1251 = false)
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if(!isset($_SESSION[$name]))
            return null;

        if($convert2win1251)
            return mb_convert_encoding($_SESSION[$name], 'windows-1251', 'utf-8');

        return $_SESSION[$name];
    }

    /**
     * Возвращает настройки модуля
     *
     * @return array
     */
    public static function getSettings()
    {
        return [
            'token' => Option::get(self::MOD_ID, 'token'),
            'shop_id' => Option::get(self::MOD_ID, 'shop_id'),
        ];
    }

    /**
     * Проверяет правильность токена
     *
     * @param $token string Токен для проверки
     * @return bool
     */
    public static function checkToken($token)
    {
        return $token && $token === self::getSettings()['token'];
    }

    /**
     * Возвращает список статусов заказов
     *
     * @return array
     */
    public static function getOrderStatuses()
    {
        if(Loader::includeModule('sale'))
        {
            $result = [];
            $statuses = \CSaleStatus::GetList();

            foreach($statuses->arResult as $status)
            {
                $result[$status['LID']][$status['ID']] = $status['NAME'];
            }

            return isset($result['ru']) ? $result['ru'] : $result['en'];
        }

        return [];
    }

    /**
     * Возвращает список доступных способов оплаты
     *
     * @return array
     */
    public static function getShopPaymentMethods()
    {
        if(Loader::includeModule('sale'))
        {
            $result = [];
            $methods = \CSalePaySystemAction::GetList([], ['=ACTIVE' => 'Y'], false, false, ['ID', 'NAME']);

            foreach($methods->arResult as $method)
            {
                $result[$method['ID']] = $method['NAME'];
            }

            return $result;
        }

        return [];
    }

    /**
     * Обновляет данные заказа по SafeRoute ID
     *
     * @param $saferoute_id int|string SafeRoute ID
     * @param $data array Новые значения (STATUS_ID, TRACKING_NUMBER)
     * @return bool
     */
    public static function updateOrderBySafeRouteID($saferoute_id, array $data)
    {
        if(Loader::includeModule('sale'))
        {
            $order = SafeRouteOrderTable::getList([
                'select' => ['ORDER_ID'],
                'filter' => ['=SAFEROUTE_ID' => $saferoute_id],
            ])->fetch();

            if(!$order) return false;

            return (bool) \CSaleOrder::Update($order['ORDER_ID'], $data);
        }

        return false;
    }

    /**
     * Обновляет данные заказа на сервере SafeRoute
     *
     * @param $data array Параметры запроса
     * @return mixed
     */
    public static function updateOrderInSafeRoute(array $data)
    {
        $settings = self::getSettings();

        $curl = curl_init('https://api.saferoute.ru/v2/widgets/update-order');

        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type:application/json',
            'Authorization:Bearer '.$settings['token'],
            'shop-id:'.$settings['shop_id'],
        ]);

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        return $response;
    }

    /**
     * Обработчик события обновления данных / создания нового заказа
     *
     * @param $event \Bitrix\Main\Event
     */
    public static function onSaleOrderSaved(\Bitrix\Main\Event $event)
    {
        $entity = $event->getParameter('ENTITY');
        $order_id = $entity->getId();

        // Новый заказ
        if($event->getParameter('IS_NEW'))
        {
            $delivery_id = (int) $entity->getField('DELIVERY_ID');
            $saferoute_order_id = self::session('saferoute_order_id');

            // Только для заказов, для которых была выбрана доставка SafeRoute
            if($delivery_id === self::getSafeRouteDeliveryID() && $saferoute_order_id)
            {
                $win1251 = SITE_CHARSET === 'windows-1251';

                $prop_id_location = self::getOrderPropIDByCode(Option::get(self::MOD_ID, 'ord_prop_code_location'));
                $prop_id_fio      = self::getOrderPropIDByCode(Option::get(self::MOD_ID, 'ord_prop_code_fio'));
                $prop_id_phone    = self::getOrderPropIDByCode(Option::get(self::MOD_ID, 'ord_prop_code_phone'));
                $prop_id_city     = self::getOrderPropIDByCode(Option::get(self::MOD_ID, 'ord_prop_code_city'));
                $prop_id_address  = self::getOrderPropIDByCode(Option::get(self::MOD_ID, 'ord_prop_code_address'));
                $prop_id_zip      = self::getOrderPropIDByCode(Option::get(self::MOD_ID, 'ord_prop_code_zip'));

                // Сохранение данных доставки в свойствах заказа
                $pc = $entity->getPropertyCollection();
                if ($pc->getItemByOrderPropertyId($prop_id_location))
                    $pc->getItemByOrderPropertyId($prop_id_location)->setValue('');
                if ($pc->getItemByOrderPropertyId($prop_id_fio))
                    $pc->getItemByOrderPropertyId($prop_id_fio)->setValue(self::session('saferoute_full_name', $win1251));
                if ($pc->getItemByOrderPropertyId($prop_id_phone))
                    $pc->getItemByOrderPropertyId($prop_id_phone)->setValue(self::session('saferoute_phone', $win1251));
                if ($pc->getItemByOrderPropertyId($prop_id_city))
                    $pc->getItemByOrderPropertyId($prop_id_city)->setValue(self::session('saferoute_city', $win1251));
                if ($pc->getItemByOrderPropertyId($prop_id_address))
                    $pc->getItemByOrderPropertyId($prop_id_address)->setValue(self::session('saferoute_address', $win1251));
                if ($pc->getItemByOrderPropertyId($prop_id_zip))
                    $pc->getItemByOrderPropertyId($prop_id_zip)->setValue(self::session('saferoute_zip_code', $win1251));
                $entity->save();

                // Только если не была выбрана собственная компания доставки
                if ($saferoute_order_id !== 'no')
                {
                    // Сохранение SafeRoute ID заказа
                    SafeRouteOrderTable::add([
                        'ORDER_ID'             => $order_id,
                        'SAFEROUTE_ID'         => $saferoute_order_id,
                        'IN_SAFEROUTE_CABINET' => self::session('saferoute_order_in_cabinet'),
                    ]);

                    // Отправка запроса к бэку SafeRoute
                    $response = self::updateOrderInSafeRoute([
                        'id'            => $saferoute_order_id,
                        'cmsId'         => $order_id,
                        'status'        => $entity->getField('STATUS_ID'),
                        'paymentMethod' => $entity->getField('PAY_SYSTEM_ID'),
                    ]);

                    // Если заказ был перенесен в ЛК
                    if($response && $response['cabinetId'])
                    {
                        // Обновляем его SafeRoute ID и устанавливаем флаг, что заказ находится в ЛК
                        SafeRouteOrderTable::update($order_id, [
                            'SAFEROUTE_ID'         => $response['cabinetId'],
                            'IN_SAFEROUTE_CABINET' => true,
                        ]);
                    }
                }
            }
        }
        // Изменение старого заказа
        else
        {
            $order = \CSaleOrder::GetByID($order_id);

            $sr_order = SafeRouteOrderTable::getByPrimary($order_id)->fetchObject();

            // Только заказы, имеющие SafeRoute ID и ещё не перенесенные в ЛК
            if($sr_order && $sr_order->get('SAFEROUTE_ID') && !$sr_order->get('IN_SAFEROUTE_CABINET'))
            {
                $response = self::updateOrderInSafeRoute([
                    'id'     => $sr_order->get('SAFEROUTE_ID'),
                    'status' => $order['STATUS_ID'],
                    'cmsId'  => $order_id,
                ]);

                // Если заказ был перенесен в ЛК
                if($response && $response['cabinetId'])
                {
                    // Устанавливаем соответствующий флаг и сохраняем новый SafeRoute ID
                    $sr_order->set('IN_SAFEROUTE_CABINET', true);
                    $sr_order->set('SAFEROUTE_ID', $response['cabinetId']);
                    $sr_order->save();
                }
            }
        }
    }

    /**
     * Возвращает ID доставки SafeRoute в базе Битрикса
     *
     * @return int|null
     */
    public static function getSafeRouteDeliveryID()
    {
        foreach(\Bitrix\Sale\Delivery\Services\Manager::getActiveList() as $d)
            if($d['CODE'] === 'SafeRoute') return (int) $d['ID'];

        return null;
    }

    /**
     * Обработчик события рендера страницы
     */
    public static function onPageStart()
    {
        Loader::includeModule(self::MOD_ID);
    }

    /**
     * Возвращает ID свойства заказа по его коду
     *
     * @param $code string Строковой код свойства
     * @return int|null
     */
    public static function getOrderPropIDByCode($code)
    {
        $res = \CSaleOrderProps::GetList([], ['=CODE' => $code, '=PERSON_TYPE_ID' => 1])->arResult;
        return ($res) ? (int) $res[0]['ID'] : null;
    }
}
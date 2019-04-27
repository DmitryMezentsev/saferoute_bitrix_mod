<?php

namespace DDeliveryru\Widget;

use \Bitrix\Main\Config\Option;
use \Bitrix\Main\Loader;

/**
 * �������� ����� ������ �� ����� ���������
 */
class Common
{
	/**
	 * ���������� �������� ���������� ������
	 * 
	 * @param $name string ��� ����������
	 * @param $convert2win1251 bool ���� ������������� ����������� �������� � windows-1251
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
	 * ���������� API-���� DDelivery �� �������� ������
	 * 
	 * @return string
	 */
	public static function getAPIKey()
	{
		return \Bitrix\Main\Config\Option::get('ddeliveryru.widget', 'api_key');
	}
	
	/**
	 * ��������� ������������ API-�����
	 * 
	 * @param $api_key string API-���� ��� ��������
	 * @return bool
	 */
	public static function checkAPIKey($api_key)
	{
		return $api_key && $api_key === self::getAPIKey();
	}
	
	/**
	 * ���������� ������ �������� �������
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
	 * ���������� ������ ��������� �������� ������
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
	 * ��������� ������ ������ �� DDelivery ID
	 * 
	 * @param $ddelivery_id int|string DDelivery ID
	 * @param $data array ����� �������� (STATUS_ID, TRACKING_NUMBER)
	 * @return bool
	 */
	public static function updateOrderByDDeliveryID($ddelivery_id, array $data)
	{
		if(Loader::includeModule('sale'))
		{
			$order = DDeliveryOrderTable::getList([
				'select' => ['ORDER_ID'],
				'filter' => ['=DDELIVERY_ID' => $ddelivery_id],
			])->fetch();
			
			if(!$order) return false;
			
			return (bool) \CSaleOrder::Update($order['ORDER_ID'], $data);
		}
		
		return false;
	}
	
	/**
	 * ��������� ������ ������ �� ������� DDelivery
	 * 
	 * @param $data array ��������� �������
     * @return mixed
	 */
	public static function updateOrderInDDelivery(array $data)
	{
        $api = 'https://ddelivery.ru/api/' . self::getAPIKey() . '/sdk/update-order.json';
		
        $curl = curl_init($api);
		
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
		
        return $response;
	}
	
	/**
	 * ���������� ������� ���������� ������ / �������� ������ ������
	 * 
	 * @param $event \Bitrix\Main\Event
	 */
	public static function onSaleOrderSaved(\Bitrix\Main\Event $event)
	{
		$entity = $event->getParameter('ENTITY');
		$order_id = $entity->getId();
		
		// ����� �����
		if($event->getParameter('IS_NEW'))
		{
			$delivery_id = (int) $entity->getField('DELIVERY_ID');
			$ddelivery_order_id = self::session('ddelivery_order_id');
			
			// ������ ��� �������, ��� ������� ���� ������� �������� DDelivery
			if($delivery_id === self::getDDeliveryDeliveryID() && $ddelivery_order_id)
			{
				$win1251 = SITE_CHARSET === 'windows-1251';
				
				$prop_id_location = self::getOrderPropIDByCode(Option::get('ddeliveryru.widget', 'ord_prop_code_location'));
				$prop_id_fio      = self::getOrderPropIDByCode(Option::get('ddeliveryru.widget', 'ord_prop_code_fio'));
				$prop_id_phone    = self::getOrderPropIDByCode(Option::get('ddeliveryru.widget', 'ord_prop_code_phone'));
				$prop_id_city     = self::getOrderPropIDByCode(Option::get('ddeliveryru.widget', 'ord_prop_code_city'));
				$prop_id_address  = self::getOrderPropIDByCode(Option::get('ddeliveryru.widget', 'ord_prop_code_address'));
				$prop_id_zip      = self::getOrderPropIDByCode(Option::get('ddeliveryru.widget', 'ord_prop_code_zip'));
				
				// ���������� ������ �������� � ��������� ������
				$pc = $entity->getPropertyCollection();
				if ($pc->getItemByOrderPropertyId($prop_id_location))
					$pc->getItemByOrderPropertyId($prop_id_location)->setValue('');
				if ($pc->getItemByOrderPropertyId($prop_id_fio))
					$pc->getItemByOrderPropertyId($prop_id_fio)->setValue(self::session('ddelivery_full_name', $win1251));
				if ($pc->getItemByOrderPropertyId($prop_id_phone))
					$pc->getItemByOrderPropertyId($prop_id_phone)->setValue(self::session('ddelivery_phone', $win1251));
				if ($pc->getItemByOrderPropertyId($prop_id_city))
					$pc->getItemByOrderPropertyId($prop_id_city)->setValue(self::session('ddelivery_city', $win1251));
				if ($pc->getItemByOrderPropertyId($prop_id_address))
					$pc->getItemByOrderPropertyId($prop_id_address)->setValue(self::session('ddelivery_address', $win1251));
				if ($pc->getItemByOrderPropertyId($prop_id_zip))
					$pc->getItemByOrderPropertyId($prop_id_zip)->setValue(self::session('ddelivery_index', $win1251));
				$entity->save();
				
				// ������ ���� �� ���� ������� ����������� �������� ��������
				if ($ddelivery_order_id !== 'no')
				{
					// ���������� DDelivery ID ������
					DDeliveryOrderTable::add([
						'ORDER_ID'             => $order_id,
						'DDELIVERY_ID'         => $ddelivery_order_id,
						'IN_DDELIVERY_CABINET' => self::session('ddelivery_order_in_cabinet'),
					]);
					
					// �������� ������� � SDK
					$response = self::updateOrderInDDelivery([
						'id'             => $ddelivery_order_id,
						'cms_id'         => $order_id,
						'status'         => $entity->getField('STATUS_ID'),
						'payment_method' => $entity->getField('PAY_SYSTEM_ID'),
					]);
					
					// ���� ����� ��� ��������� � ��
					if($response['status'] === 'ok' && isset($response['data']['cabinet_id']))
					{
						// ��������� ��� DDelivery ID � ������������� ����, ��� ����� ��������� � ��
						DDeliveryOrderTable::update($order_id, [
							'DDELIVERY_ID'         => $response['data']['cabinet_id'],
							'IN_DDELIVERY_CABINET' => true,
						]);
					}
				}
			}
		}
		// ��������� ������� ������
		else
		{
			$order = \CSaleOrder::GetByID($order_id);
			
			$dd_order = DDeliveryOrderTable::getByPrimary($order_id)->fetchObject();
			
			// ������ ������, ������� DDelivery ID � ��� �� ������������ � ��
			if($dd_order && $dd_order->get('DDELIVERY_ID') && !$dd_order->get('IN_DDELIVERY_CABINET'))
			{
				$response = self::updateOrderInDDelivery([
					'id'     => $dd_order->get('DDELIVERY_ID'),
					'status' => $order['STATUS_ID'],
					'cms_id' => $order_id,
				]);
				
				if($response['status'] === 'ok')
				{
					// ���� ����� ��� ��������� � ��
					if(isset($response['data']['cabinet_id']))
					{
						// ������������� ��������������� ���� � ��������� ����� DDelivery ID
						$dd_order->set('IN_DDELIVERY_CABINET', true);
						$dd_order->set('DDELIVERY_ID', $response['data']['cabinet_id']);
						$dd_order->save();
					}
				}
			}
		}
	}
	
	/**
	 * ���������� ID �������� DDelivery � ���� ��������
	 * 
	 * @return int|null
	 */
	public static function getDDeliveryDeliveryID()
	{
		foreach(\Bitrix\Sale\Delivery\Services\Manager::getActiveList() as $d)
			if($d['CODE'] === 'DDelivery') return (int) $d['ID'];
		
		return null;
	}
	
	/**
	 * ���������� ������� ������� ��������
	 */
	public static function onPageStart()
	{
		Loader::includeModule('ddeliveryru.widget');
	}
	
	/**
	 * ���������� ID �������� ������ �� ��� ����
	 * 
	 * @param $code string ��������� ��� ��������
	 * @return int|null
	 */
	public static function getOrderPropIDByCode($code)
	{
		$res = \CSaleOrderProps::GetList([], ['=CODE' => $code, '=PERSON_TYPE_ID' => 1])->arResult;
		return ($res) ? (int) $res[0]['ID'] : null;
	}
}
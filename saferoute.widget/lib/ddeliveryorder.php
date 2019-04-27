<?php

namespace DDeliveryru\Widget;

use \Bitrix\Main\Entity;

class DDeliveryOrderTable extends Entity\DataManager
{
	/**
	 * @return string
	 */
	public static function getTableName()
	{
		return 'ddelivery_order';
	}
	
	/**
	 * @return array
	 */
	public static function getMap()
	{
		return [
			// ID заказа в CMS
			new Entity\IntegerField('ORDER_ID', [
				'primary' => true,
			]),
			// ID заказа в DDelivery
			new Entity\StringField('DDELIVERY_ID', [
				'required' => true,
			]),
			// Флаг, что заказ был перенесен в ЛК DDelivery
			new Entity\BooleanField('IN_DDELIVERY_CABINET'),
		];
	}
}

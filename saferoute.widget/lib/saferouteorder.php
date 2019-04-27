<?php

namespace Saferoute\Widget;

use \Bitrix\Main\Entity;

class SafeRouteOrderTable extends Entity\DataManager
{
	/**
	 * @return string
	 */
	public static function getTableName()
	{
		return 'saferoute_order';
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
			// ID заказа в SafeRoute
			new Entity\StringField('SAFEROUTE_ID', [
				'required' => true,
			]),
			// Флаг, что заказ был перенесен в ЛК SafeRoute
			new Entity\BooleanField('IN_SAFEROUTE_CABINET'),
		];
	}
}

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
			// ID ������ � CMS
			new Entity\IntegerField('ORDER_ID', [
				'primary' => true,
			]),
			// ID ������ � DDelivery
			new Entity\StringField('DDELIVERY_ID', [
				'required' => true,
			]),
			// ����, ��� ����� ��� ��������� � �� DDelivery
			new Entity\BooleanField('IN_DDELIVERY_CABINET'),
		];
	}
}

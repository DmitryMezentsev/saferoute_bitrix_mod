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
			// ID ������ � CMS
			new Entity\IntegerField('ORDER_ID', [
				'primary' => true,
			]),
			// ID ������ � SafeRoute
			new Entity\StringField('SAFEROUTE_ID', [
				'required' => true,
			]),
			// ����, ��� ����� ��� ��������� � �� SafeRoute
			new Entity\BooleanField('IN_SAFEROUTE_CABINET'),
		];
	}
}

<?php

namespace Sale\Handlers\Delivery;

use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Delivery\Services\Base;


class SaferouteHandler extends Base
{
	public static function getClassTitle()
	{
		return 'SafeRoute';
	}
	
	public static function getClassDescription()
	{
		return '';
	}
	
	protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment)
	{
		if (session_status() === PHP_SESSION_NONE) session_start();
		
		$result = new CalculationResult();
		$result->setDeliveryPrice(roundEx($_SESSION['saferoute_price'], 2));
		
		return $result;
	}
	
	protected function getConfigStructure()
	{
		return [];
	}
	
	public function isCalculatePriceImmediately()
	{
		return true;
	}
	
	public static function whetherAdminExtraServicesShow()
	{
		return true;
	}
}

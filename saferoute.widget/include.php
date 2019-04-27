<?php

use \Bitrix\Main\Context;
use Bitrix\Main\Config\Option;

if(CModule::IncludeModule('sale') && CModule::IncludeModule('catalog') && !Context::getCurrent()->getRequest()->isAdminSection())
{
	if(!Option::get('saferoute.widget', 'api_key'))
	{
		$inlineJs = 'var SAFEROUTE_WIDGET = false;';
	}
	else
	{
		$inlineJs  = 'var SAFEROUTE_WIDGET = {};';
		$inlineJs .= 'SAFEROUTE_WIDGET.PRODUCTS = [';
		
		// Получение товаров текущей корзины
		$cart = CSaleBasket::GetList([], [
			'FUSER_ID' => CSaleBasket::GetBasketUserID(),
			'LID'      => SITE_ID,
			'ORDER_ID' => 'NULL',
		]);
		
		$arErrors = [];
		$arWarnings = [];
		$calculated_order = CSaleOrder::DoCalculateOrder(SITE_ID, CSaleBasket::GetBasketUserID(), $cart->arResult, null, [], null, null, [], $arErrors, $arWarnings);
		
		// Возвращает размер текущей скидки для заданного товара корзины
		$get_product_discount = function ($product) use ($calculated_order)
		{
			if (!$product || !$calculated_order) return 0;
			
			foreach($calculated_order['BASKET_ITEMS'] as $basket_item)
			{
				if ($basket_item['ID'] === $product['ID'])
					return $basket_item['DISCOUNT_PRICE'];
			}
			
			return 0;
		};
		
		$i = 0;
		while($item = $cart->Fetch())
		{
			$product = CCatalogProduct::GetByIDEx($item['PRODUCT_ID']);
			
			// Артикул
			$vendorCode = (isset($product['PROPERTIES']['ARTNUMBER']['VALUE'])) ? $product['PROPERTIES']['ARTNUMBER']['VALUE'] : '';
			
			// Штрих-код
			$barcode = CCatalogStoreBarCode::getList([], ['PRODUCT_ID' => $item['PRODUCT_ID']])->GetNext();
			$barcode = (isset($barcode['BARCODE'])) ? $barcode['BARCODE'] : '';
			
			// НДС
			$vat = CCatalogVat::GetByID($product['PRODUCT']['VAT_ID'])->Fetch();
			$vat = ($vat && isset($vat['RATE'])) ? (int) $vat['RATE'] : 'null';
			
			$inlineJs .= '{';
			$inlineJs .= "name: '$item[NAME]',";
			$inlineJs .= "vendorCode: '$vendorCode',";
			$inlineJs .= "barcode: '$barcode',";
			$inlineJs .= "nds: $vat,";
			$inlineJs .= "price: " . round($item['PRICE'], 2) . ",";
			$inlineJs .= "discount: " . $get_product_discount($item) . ",";
			$inlineJs .= "count: $item[QUANTITY]";
			$inlineJs .= '}';
			
			if(++$i < count($cart->arResult)) $inlineJs .= ',';
		}
		unset($i);
		
		$inlineJs .= '];';
		
		$inlineJs .= "SAFEROUTE_WIDGET.LANG = '" . LANGUAGE_ID . "';";
		$inlineJs .= "SAFEROUTE_WIDGET.API_SCRIPT = '" . SITE_DIR . "bitrix/components/saferoute.widget/api/widget-api.php';";
		$inlineJs .= "SAFEROUTE_WIDGET.SESSION_SCRIPT = '" . SITE_DIR . "bitrix/components/saferoute.widget/api/save-session.php';";
		$inlineJs .= "SAFEROUTE_WIDGET.WEIGHT = " . round($calculated_order['ORDER_WEIGHT']/1000, 2) . ";";

		// ID доставки SafeRoute в БД
		$inlineJs .= 'var SAFEROUTE_DELIVERY_ID = "' . Saferoute\Widget\Common::getSafeRouteDeliveryID() . '";';

		// Получение ID свойств заказа
		// Получение ID свойств заказа
		$inlineJs .= 'var ORDER_PROPS_FOR_SAFEROUTE = {};';
		$order_props = CSaleOrderProps::GetList([], ['=PERSON_TYPE_ID' => 1], false, false, ['ID', 'CODE'])->arResult;
		foreach($order_props as $order_prop) {
			if($order_prop['CODE']) $inlineJs .= "ORDER_PROPS_FOR_SAFEROUTE['$order_prop[CODE]'] = $order_prop[ID];";
		}
	}
	
	
	global $APPLICATION;

	CJSCore::Init(['jquery2']); 
	$APPLICATION->AddHeadScript('https://widgets.saferoute.ru/cart/api.js');
	$APPLICATION->SetAdditionalCSS(SITE_DIR . 'bitrix/css/saferoute.widget/common.css');
	$APPLICATION->AddHeadString('<script>' . $inlineJs . '</script>');
	$APPLICATION->AddHeadString('<script src="' . SITE_DIR . 'bitrix/js/saferoute.widget/main.js" charset="utf-8"></script>');
}

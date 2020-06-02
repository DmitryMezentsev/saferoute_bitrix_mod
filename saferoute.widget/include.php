<?php

use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;
use Bitrix\Sale\BusinessValue;

if(CModule::IncludeModule('sale') && CModule::IncludeModule('catalog') && !Context::getCurrent()->getRequest()->isAdminSection())
{
    $mod_id = 'saferoute.widget';

    if(!Option::get($mod_id, 'token') || !Option::get($mod_id, 'shop_id'))
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

            // Габариты
            $dimensions = unserialize($item['DIMENSIONS']);
            $w = $dimensions['WIDTH'] ? $dimensions['WIDTH']/10 : 'null';
            $h = $dimensions['HEIGHT'] ? $dimensions['HEIGHT']/10 : 'null';
            $l = $dimensions['LENGTH'] ? $dimensions['LENGTH']/10 : 'null';

            $inlineJs .= '{';
            $inlineJs .= "name: '$item[NAME]',";
            $inlineJs .= "vendorCode: '$vendorCode',";
            $inlineJs .= "barcode: '$barcode',";
            $inlineJs .= "vat: $vat,";
            $inlineJs .= "price: " . round($item['PRICE'], 2) . ",";
            $inlineJs .= "discount: " . $get_product_discount($item) . ",";
            $inlineJs .= "count: $item[QUANTITY],";
            $inlineJs .= "width: $w,";
            $inlineJs .= "height: $h,";
            $inlineJs .= "length: $l";
            $inlineJs .= '}';

            if(++$i < count($cart->arResult)) $inlineJs .= ',';
        }
        unset($i);

        $inlineJs .= '];';

        $inlineJs .= "SAFEROUTE_WIDGET.LANG = '" . LANGUAGE_ID . "';";
        $inlineJs .= "SAFEROUTE_WIDGET.API_SCRIPT = '" . SITE_DIR . "bitrix/components/saferoute.widget/api/widget-api.php';";
        $inlineJs .= "SAFEROUTE_WIDGET.SESSION_SCRIPT = '" . SITE_DIR . "bitrix/components/saferoute.widget/api/save-session.php';";
        $inlineJs .= "SAFEROUTE_WIDGET.WEIGHT = " . round($calculated_order['ORDER_WEIGHT']/1000, 2) . ";";


        // Валюта корзины (определяется по первому товару в корзине)
        if (isset($cart->arResult[0]['CURRENCY'])) $inlineJs .= "SAFEROUTE_WIDGET.CURRENCY = '" . $cart->arResult[0]['CURRENCY'] . "';";
        // ID доставки SafeRoute в БД
        $inlineJs .= 'var SAFEROUTE_DELIVERY_ID = "' . Saferoute\Widget\Common::getSafeRouteDeliveryID() . '";';


        // Формирование массива ID типов плательщиков
        $person_type_ids = ['INDIVIDUAL' => [], 'LEGAL' => []];
        foreach(BusinessValue::getPersonTypes() as $person_type)
        {
            if ($person_type['ACTIVE'] !== 'Y') continue;

            // Физ. лица
            if ($person_type['DOMAIN'] === 'I')
            {
                array_push($person_type_ids['INDIVIDUAL'], $person_type['ID']);
            }
            // Юр. лица
            elseif ($person_type['DOMAIN'] === 'E')
            {
                array_push($person_type_ids['LEGAL'], $person_type['ID']);
            }
        }

        // Параметры сопоставления полей из настроек виджета SafeRoute
        $saferoute_order_props_mapping = [
            'INDIVIDUAL' => [
                'FIO'      => Option::get($mod_id, 'ord_prop_code_fio'),
                'LOCATION' => Option::get($mod_id, 'ord_prop_code_location'),
                'PHONE'    => Option::get($mod_id, 'ord_prop_code_phone'),
                'EMAIL'    => Option::get($mod_id, 'ord_prop_code_email'),
                'CITY'     => Option::get($mod_id, 'ord_prop_code_city'),
                'ADDRESS'  => Option::get($mod_id, 'ord_prop_code_address'),
                'ZIP'      => Option::get($mod_id, 'ord_prop_code_zip'),
            ],
            'LEGAL' => [
                'COMPANY'        => Option::get($mod_id, 'ord_prop_code_lp_company_name'),
                'INN'            => Option::get($mod_id, 'ord_prop_code_lp_tin'),
                'CONTACT_PERSON' => Option::get($mod_id, 'ord_prop_code_lp_contact_person'),
                'EMAIL'          => Option::get($mod_id, 'ord_prop_code_lp_email'),
                'PHONE'          => Option::get($mod_id, 'ord_prop_code_lp_phone'),
                'ZIP'            => Option::get($mod_id, 'ord_prop_code_lp_zip'),
                'CITY'           => Option::get($mod_id, 'ord_prop_code_lp_city'),
                'ADDRESS'        => Option::get($mod_id, 'ord_prop_code_lp_delivery_address'),
            ],
        ];

        // Получение ID свойств заказа
        $inlineJs .= 'var ORDER_PROPS_FOR_SAFEROUTE = { INDIVIDUAL: {}, LEGAL: {} };';
        $order_props = CSaleOrderProps::GetList([], [], false, false, ['ID', 'CODE', 'PERSON_TYPE_ID'])->arResult;

        foreach($order_props as $order_prop)
        {
            // Определение типа плательщика, к которому относится свойство
            if (in_array($order_prop['PERSON_TYPE_ID'], $person_type_ids['INDIVIDUAL'])) $type = 'INDIVIDUAL';
            elseif (in_array($order_prop['PERSON_TYPE_ID'], $person_type_ids['LEGAL'])) $type = 'LEGAL';

            if(!$order_prop['CODE'] || !isset($type)) continue;

            foreach($saferoute_order_props_mapping[$type] as $key => $val)
            {
                if ($val === $order_prop['CODE']) $inlineJs .= "ORDER_PROPS_FOR_SAFEROUTE.$type.$key = $order_prop[ID];";
            }

            unset($type);
        }
    }


    global $APPLICATION;

    CJSCore::Init(['jquery2']);
    $APPLICATION->AddHeadString('<script src="https://widgets.saferoute.ru/cart/api.js?new" charset="utf-8"></script>');
    $APPLICATION->SetAdditionalCSS(SITE_DIR . 'bitrix/css/saferoute.widget/common.css');
    $APPLICATION->AddHeadString('<script>' . $inlineJs . '</script>');
    $APPLICATION->AddHeadString('<script src="' . SITE_DIR . 'bitrix/js/saferoute.widget/main.js" charset="utf-8"></script>');
}

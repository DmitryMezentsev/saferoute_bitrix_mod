<?php

use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;
use Bitrix\Sale\BusinessValue;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$mod_id = 'saferoute.widget';

$srGetProductPropValue = function ($product, $prop_name) use ($mod_id)
{
    $option = Option::get($mod_id, $prop_name);

    return ($option && isset($product['PROPERTIES'][$option]['VALUE']))
        ? addslashes($product['PROPERTIES'][$option]['VALUE'])
        : '';
};

if(CModule::IncludeModule('sale') && CModule::IncludeModule('catalog') && !Context::getCurrent()->getRequest()->isAdminSection())
{
    if(!Option::get($mod_id, 'token') || !Option::get($mod_id, 'shop_id'))
    {
        $inlineJs = 'var SAFEROUTE_WIDGET = false;';
    }
    else
    {
        $inlineJs  = 'var SAFEROUTE_WIDGET = {};';
        $inlineJs .= 'SAFEROUTE_WIDGET.PRODUCTS = [';

        // ��������� ������� ������� �������
        $cart = CSaleBasket::GetList([], [
            'FUSER_ID' => CSaleBasket::GetBasketUserID(),
            'LID'      => SITE_ID,
            'ORDER_ID' => 'NULL',
        ]);

        $arErrors = [];
        $arWarnings = [];
        $calculated_order = CSaleOrder::DoCalculateOrder(SITE_ID, CSaleBasket::GetBasketUserID(), $cart->arResult, null, [], null, null, [], $arErrors, $arWarnings);

        // ���������� ������ ������� ������ ��� ��������� ������ �������
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

            $vendorCode = $srGetProductPropValue($product, 'prod_prop_code_vendor_code');
            $tnved  = $srGetProductPropValue($product, 'prod_prop_code_tnved');
            $nameEn = $srGetProductPropValue($product, 'prod_prop_code_name_en');
            $brand  = $srGetProductPropValue($product, 'prod_prop_code_brand');
            $producingCountry = $srGetProductPropValue($product, 'prod_prop_code_producing_country');

            // �����-���
            $barcode = CCatalogStoreBarCode::getList([], ['PRODUCT_ID' => $item['PRODUCT_ID']])->GetNext();
            $barcode = (isset($barcode['BARCODE'])) ? $barcode['BARCODE'] : '';

            // ���
            $vat = CCatalogVat::GetByID($product['PRODUCT']['VAT_ID'])->Fetch();
            $vat = ($vat && isset($vat['RATE'])) ? (int) $vat['RATE'] : 'null';

            // ��������
            $dimensions = unserialize($item['DIMENSIONS']);
            $w = $dimensions['WIDTH'] ? $dimensions['WIDTH']/10 : 'null';
            $h = $dimensions['HEIGHT'] ? $dimensions['HEIGHT']/10 : 'null';
            $l = $dimensions['LENGTH'] ? $dimensions['LENGTH']/10 : 'null';

            $inlineJs .= '{';
            $inlineJs .= "name: '" . addslashes($item['NAME']) . "',";
            $inlineJs .= "vendorCode: '$vendorCode',";
            $inlineJs .= "tnved: '$tnved',";
            $inlineJs .= "nameEn: '$nameEn',";
            $inlineJs .= "producingCountry: '$producingCountry',";
            $inlineJs .= "brand: '$brand',";
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
        $inlineJs .= "SAFEROUTE_WIDGET.DISABLE_MULTI_REQUESTS = " . (Option::get($mod_id, 'disable_multi_requests') ? 'true' : 'false') . ";";
        $inlineJs .= "SAFEROUTE_WIDGET.LOCK_PICKUP_FILTERS = " . (Option::get($mod_id, 'lock_pickup_filters') ? 'true' : 'false') . ";";


        // ������ ������� (������������ �� ������� ������ � �������)
        if (isset($cart->arResult[0]['CURRENCY'])) $inlineJs .= "SAFEROUTE_WIDGET.CURRENCY = '" . $cart->arResult[0]['CURRENCY'] . "';";
        // ID �������� SafeRoute � ��
        $sr_delivery_ids = Saferoute\Widget\Common::getSafeRouteDeliveryIDs();
        $inlineJs .= 'var SAFEROUTE_DELIVERY_ID = "' . $sr_delivery_ids['common'] . '";';
        $inlineJs .= 'var SAFEROUTE_COURIER_DELIVERY_ID = "' . $sr_delivery_ids['courier'] . '";';
        $inlineJs .= 'var SAFEROUTE_PICKUP_DELIVERY_ID = "' . $sr_delivery_ids['pickup'] . '";';


        // ������������ ������� ID ����� ������������
        $person_type_ids = ['INDIVIDUAL' => [], 'LEGAL' => []];
        foreach(BusinessValue::getPersonTypes() as $person_type)
        {
            if ($person_type['ACTIVE'] !== 'Y') continue;

            // ���. ����
            if ($person_type['DOMAIN'] === 'I')
            {
                array_push($person_type_ids['INDIVIDUAL'], $person_type['ID']);
            }
            // ��. ����
            elseif ($person_type['DOMAIN'] === 'E')
            {
                array_push($person_type_ids['LEGAL'], $person_type['ID']);
            }
        }

        // ��������� ������������� ����� �� �������� ������� SafeRoute
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

        // ��������� ID ������� ������
        $inlineJs .= 'var ORDER_PROPS_FOR_SAFEROUTE = { INDIVIDUAL: {}, LEGAL: {} };';
        $order_props = CSaleOrderProps::GetList([], [], false, false, ['ID', 'CODE', 'PERSON_TYPE_ID'])->arResult;

        foreach($order_props as $order_prop)
        {
            // ����������� ���� �����������, � �������� ��������� ��������
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


    // ����������� ID ������� ������ ����� ��������� SafeRoute
    $sr_pay_method_id = 'null';
    foreach(CSalePaySystemAction::GetList()->arResult as $payment_method)
    {
        if (preg_match("/saferoute/i", $payment_method['ACTION_FILE'])) $sr_pay_method_id = $payment_method['ID'];
    }
    $inlineJs .= "var SAFEROUTE_PAY_METHOD_ID = $sr_pay_method_id;";


    $inlineJs .= 'var SAFEROUTE_DEV_MODE = ' . (SR_DEV_MODE ? 'true' : 'false') . ';';


    global $APPLICATION;

    CJSCore::Init(['jquery2']);
    $APPLICATION->AddHeadString('<script src="https://widgets.saferoute.ru/cart/api.js" charset="utf-8"></script>');
    $APPLICATION->SetAdditionalCSS(SITE_DIR . 'bitrix/css/saferoute.widget/common.css');
    $APPLICATION->AddHeadString('<script>' . $inlineJs . '</script>');
    $APPLICATION->AddHeadString('<script src="' . SITE_DIR . 'bitrix/js/saferoute.widget/main.js" charset="utf-8"></script>');

    if (!function_exists('curl_version'))
        $APPLICATION->AddHeadString('<script>alert("' . Loc::getMessage('SAFEROUTE_WIDGET_CURL_ERROR') . '");</script>');
}

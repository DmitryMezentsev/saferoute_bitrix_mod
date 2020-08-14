<?php

use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Config\Option;
use \Bitrix\Main\HttpApplication;
use \Bitrix\Main\Loader;


$module_id = 'saferoute.widget';

Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/modules/main/options.php');
Loc::loadMessages(__FILE__);

Loader::includeModule($module_id);


$request = HttpApplication::getInstance()->getContext()->getRequest();

$aTabs = [
	[
		'DIV' => 'main-tab',
		'TAB' => Loc::getMessage('SAFEROUTE_WIDGET_TAB_SETTINGS'),
		'OPTIONS' => [
      // Общее
			Loc::getMessage('SAFEROUTE_WIDGET_SETTINGS_COMMON'),
			['token', Loc::getMessage('SAFEROUTE_WIDGET_TOKEN'), '', ['text', 40]],
			['shop_id', Loc::getMessage('SAFEROUTE_WIDGET_SHOP_ID'), '', ['text', 20]],
      ['disable_multi_requests', Loc::getMessage('SAFEROUTE_WIDGET_DISABLE_MULTI_REQUESTS'), '', ['checkbox']],
      ['lock_pickup_filters', Loc::getMessage('SAFEROUTE_WIDGET_LOCK_PICKUP_FILTERS'), '', ['checkbox']],
      // Настройка полей для физ. лица
			Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROPS_CODES_IND'),
			['ord_prop_code_fio', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_FIO'), '', ['text', 30]],
			['ord_prop_code_location', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_LOCATION'), '', ['text', 30]],
			['ord_prop_code_phone', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_PHONE'), '', ['text', 30]],
			['ord_prop_code_email', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_EMAIL'), '', ['text', 30]],
			['ord_prop_code_city', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_CITY'), '', ['text', 30]],
			['ord_prop_code_address', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_ADDRESS'), '', ['text', 30]],
			['ord_prop_code_zip', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_ZIP'), '', ['text', 30]],
      // Настройка полей для юр. лица
      Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROPS_CODES_LGL'),
      ['ord_prop_code_lp_company_name', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_COMPANY_NAME'), '', ['text', 30]],
      ['ord_prop_code_lp_tin', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_TIN'), '', ['text', 30]],
      ['ord_prop_code_lp_contact_person', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_CONTACT_PERSON'), '', ['text', 30]],
      ['ord_prop_code_lp_email', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_EMAIL'), '', ['text', 30]],
      ['ord_prop_code_lp_phone', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_PHONE'), '', ['text', 30]],
      ['ord_prop_code_lp_zip', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_ZIP'), '', ['text', 30]],
      ['ord_prop_code_lp_city', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_CITY'), '', ['text', 30]],
      ['ord_prop_code_lp_delivery_address', Loc::getMessage('SAFEROUTE_WIDGET_ORD_PROP_CODE_ADDRESS'), '', ['text', 30]],
      // Настройка полей для товара
      Loc::getMessage('SAFEROUTE_WIDGET_PROD_PROPS_CODES'),
      ['prod_prop_code_vendor_code', Loc::getMessage('SAFEROUTE_WIDGET_PROD_PROP_CODE_VENDOR_CODE'), '', ['text', 30]],
		],
	],
];

// Сохранение настроек модуля
if($request->isPost() && $request['Update'])
{
	foreach($aTabs as $aTab)
	{
		foreach($aTab['OPTIONS'] as $arOption)
		{
			if (!is_array($arOption) || $arOption['note']) continue;
			
			$optionValue = $request->getPost($arOption[0]);
			
			Option::set($module_id, $arOption[0], is_array($optionValue) ? implode(',', $optionValue) : $optionValue);
		}
	}
}

$tabControl = new CAdminTabControl('tabControl', $aTabs);

$tabControl->Begin();
?>
<form method='post' action='<?=$APPLICATION->GetCurPage();?>?mid=<?=htmlspecialcharsbx($request['mid']);?>&amp;lang=<?=$request['lang'];?>' name='saferoute_widget'>
	<?php
	foreach($aTabs as $aTab)
	{
		$tabControl->BeginNextTab();
		__AdmSettingsDrawList($module_id, $aTab['OPTIONS']);
	}
	
	$tabControl->Buttons();
	?>
	
	<input type='submit' name='Update' value='<?= GetMessage('MAIN_SAVE');?>'>
</form>
<?php $tabControl->End();?>
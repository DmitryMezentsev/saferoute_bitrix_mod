<?php

use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Config\Option;
use \Bitrix\Main\HttpApplication;
use \Bitrix\Main\Loader;


$module_id = 'ddeliveryru.widget';

Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/modules/main/options.php');
Loc::loadMessages(__FILE__);

Loader::includeModule($module_id);


$request = HttpApplication::getInstance()->getContext()->getRequest();

$aTabs = [
	[
		'DIV' => 'main-tab',
		'TAB' => Loc::getMessage('DDELIVERY_WIDGET_TAB_SETTINGS'),
		'OPTIONS' => [
			Loc::getMessage('DDELIVERY_WIDGET_SETTINGS_COMMON'),
			['api_key', Loc::getMessage('DDELIVERY_WIDGET_API_KEY'), '', ['text', 40]],
			Loc::getMessage('DDELIVERY_WIDGET_ORD_PROPS_CODES'),
			['ord_prop_code_fio', Loc::getMessage('DDELIVERY_WIDGET_ORD_PROP_CODE_FIO'), '', ['text', 30]],
			['ord_prop_code_location', Loc::getMessage('DDELIVERY_WIDGET_ORD_PROP_CODE_LOCATION'), '', ['text', 30]],
			['ord_prop_code_phone', Loc::getMessage('DDELIVERY_WIDGET_ORD_PROP_CODE_PHONE'), '', ['text', 30]],
			['ord_prop_code_city', Loc::getMessage('DDELIVERY_WIDGET_ORD_PROP_CODE_CITY'), '', ['text', 30]],
			['ord_prop_code_address', Loc::getMessage('DDELIVERY_WIDGET_ORD_PROP_CODE_ADDRESS'), '', ['text', 30]],
			['ord_prop_code_zip', Loc::getMessage('DDELIVERY_WIDGET_ORD_PROP_CODE_ZIP'), '', ['text', 30]],
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
<form method='post' action='<?=$APPLICATION->GetCurPage();?>?mid=<?=htmlspecialcharsbx($request['mid']);?>&amp;lang=<?=$request['lang'];?>' name='ddeliveryru_widget'>
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
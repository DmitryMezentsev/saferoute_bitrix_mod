<?php

use \Bitrix\Main\Localization\Loc;

if (!check_bitrix_sessid()) return;

Loc::loadMessages(__FILE__);

if ($ex = $APPLICATION->GetException())
{
	echo CAdminMessage::ShowMessage([
		'TYPE'    => 'ERROR',
		'MESSAGE' => Loc::getMessage('MOD_INST_ERR'),
		'DETAILS' => $ex->GetString(),
		'HTML'    => true,
	]);
}
else
{
	echo CAdminMessage::ShowMessage([
		'TYPE'    => 'OK',
		'MESSAGE' => Loc::getMessage('MOD_INST_OK') . '.',
		'DETAILS' => Loc::getMessage('SAFEROUTE_WIDGET_SETTINGS_PROMPT'),
		'HTML'    => true,
	]);
}
?>
<form action="<?=$APPLICATION->GetCurPage();?>">
	<input type="hidden" name="lang" value="<?=LANGUAGE_ID;?>">
	<input type="submit" name="" value="<?=Loc::getMessage('MOD_BACK');?>">
</form>
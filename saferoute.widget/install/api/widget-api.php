<?php

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');
define('DisableEventsCheck', true);
define('BX_SECURITY_SHOW_MESSAGE', true);
define('NOT_CHECK_PERMISSIONS', true);


require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');


$request = Bitrix\Main\Application::getInstance()->getContext()->getRequest();

$widget_api = new Saferoute\Widget\WidgetApi();

$widget_api->setApiKey(Bitrix\Main\Config\Option::get('saferoute.widget', 'api_key'));
$widget_api->setMethod($request->getRequestMethod());
$widget_api->setData($request->get('data') ? $request->get('data') : []);

header('Content-Type: text/html; charset=utf-8');
echo $widget_api->submit($request->get('url'));
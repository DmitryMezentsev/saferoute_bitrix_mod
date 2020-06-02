<?php

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');
define('DisableEventsCheck', true);
define('BX_SECURITY_SHOW_MESSAGE', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');


$request = Bitrix\Main\Application::getInstance()->getContext()->getRequest();

$widget_api = new Saferoute\Widget\SafeRouteWidgetApi(
    Bitrix\Main\Config\Option::get('saferoute.widget', 'token'),
    Bitrix\Main\Config\Option::get('saferoute.widget', 'shop_id')
);

if ($request->getRequestMethod() === 'POST')
{
    $post = json_decode(file_get_contents('php://input'), true);

    $widget_api->setMethod('POST');
    $widget_api->setData(isset($post['data']) ? $post['data'] : []);

    $url = $post['url'];
}
else
{
    $widget_api->setMethod('GET');
    $widget_api->setData($request->get('data') ? $request->get('data') : []);

    $url = $request->get('url');
}

header('Content-Type: text/html; charset=utf-8');
echo $widget_api->submit($url);
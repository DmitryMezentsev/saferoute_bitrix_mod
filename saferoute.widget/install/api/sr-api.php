<?php

/**
 * @param array $data
 * @param bool $fix_charset
 */
function sendAsJSON(array $data, $fix_charset=false)
{
	header('Content-Type: application/json');
	
	if($fix_charset)
	{
		$data = array_map(function($i) { return iconv('windows-1251', 'utf-8', $i); }, $data);
	}
	
	echo json_encode($data);
}


require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');


use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Saferoute\Widget\Common;


$request = Application::getInstance()->getContext()->getRequest();
$route = $request->get('r');


// �������� ������
if(Common::checkToken($request->getHeader('token')))
{
	// ������ �������� ������
	if($route === 'statuses.json')
	{
		sendAsJSON(Common::getOrderStatuses(), SITE_CHARSET === 'windows-1251');
	}
	// ������ �������� ������
	elseif($route === 'payment-methods.json')
	{
		sendAsJSON(Common::getShopPaymentMethods(), SITE_CHARSET === 'windows-1251');
	}
	// ����������� �� ���������� ������� ������ � SafeRoute
	elseif($route === 'order-status-update')
	{
		$id = $request->getPost('id');
		$status_cms = $request->getPost('statusCMS');
		$track_number = $request->getPost('trackNumber');

		// id � statusCMS ����������� ������ ���� ��������
		if ($id && $status_cms)
		{
			$data = [
				'STATUS_ID' => $status_cms,
				'DATE_STATUS' => new DateTime(),
			];

			if($track_number) $data['TRACKING_NUMBER'] = $track_number;

			// ���������� ������ ������� ������ � �������-������, ���� �� ��� �������
			Common::updateOrderBySafeRouteID($id, $data);
		}
		else
		{
			CHTTP::setStatus('400 Bad Request');
		}
	}
	else
	{
		CHTTP::setStatus('404 Not Found');
	}
}
else
{
	CHTTP::setStatus('401 Unauthorized');
}
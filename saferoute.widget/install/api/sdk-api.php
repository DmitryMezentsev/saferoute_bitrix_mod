<?php

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
use DDeliveryru\Widget\Common;



$request = Application::getInstance()->getContext()->getRequest();
$route = $request->get('r');



// �������� API-�����
if(Common::checkAPIKey($request->get('k')))
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
	// ����������� �� ���������� ������� ������ � DDelivery
	elseif($route === 'traffic-orders.json')
	{
		$id = $request->getPost('id');
		$status_cms = $request->getPost('status_cms');
		$track_number = $request->getPost('track_number');
		
		// id � status_cms ����������� ������ ���� ��������
		if ($id && $status_cms)
		{
			$data = [
				'STATUS_ID' => $status_cms,
				'DATE_STATUS' => new DateTime(),
			];
			
			if($track_number) $data['TRACKING_NUMBER'] = $track_number;
			
			// ���������� ������ ������� ������ � �������-������, ���� �� ��� �������
			Common::updateOrderByDDeliveryID($id, $data);
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
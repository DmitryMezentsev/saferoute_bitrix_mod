<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

if (session_status() === PHP_SESSION_NONE) session_start();

switch($_POST['action'])
{
	// ”становка выбранной доставки
	case 'set_delivery':
		$_SESSION['ddelivery_price']    = $_POST['ddelivery_price'];
		$_SESSION['ddelivery_order_id'] = $_POST['ddelivery_order_id'];
		$_SESSION['ddelivery_order_in_cabinet'] = (bool) $_POST['ddelivery_order_in_cabinet'];
		
		$_SESSION['ddelivery_full_name'] = $_POST['ddelivery_full_name'];
		$_SESSION['ddelivery_phone']     = $_POST['ddelivery_phone'];
		$_SESSION['ddelivery_city']      = $_POST['ddelivery_city'];
		$_SESSION['ddelivery_address']   = $_POST['ddelivery_address'];
		$_SESSION['ddelivery_index']     = $_POST['ddelivery_index'];
		break;
	
	// —брос выбранной доставки
	case 'reset_delivery':
		$_SESSION['ddelivery_price']            = 0;
		$_SESSION['ddelivery_order_id']         = null;
		$_SESSION['ddelivery_order_in_cabinet'] = null;
		$_SESSION['ddelivery_full_name']        = '';
		$_SESSION['ddelivery_phone']            = '';
		$_SESSION['ddelivery_city']             = '';
		$_SESSION['ddelivery_address']          = '';
		$_SESSION['ddelivery_index']            = '';
		break;
}
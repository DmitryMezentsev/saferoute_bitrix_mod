<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

if (session_status() === PHP_SESSION_NONE) session_start();

switch($_POST['action'])
{
	// ”становка выбранной доставки
	case 'set_delivery':
		$_SESSION['saferoute_price']    = $_POST['saferoute_price'];
		$_SESSION['saferoute_order_id'] = $_POST['saferoute_order_id'];
		$_SESSION['saferoute_order_in_cabinet'] = (bool) $_POST['saferoute_order_in_cabinet'];
		
		$_SESSION['saferoute_full_name'] = $_POST['saferoute_full_name'];
		$_SESSION['saferoute_phone']     = $_POST['saferoute_phone'];
		$_SESSION['saferoute_city']      = $_POST['saferoute_city'];
		$_SESSION['saferoute_address']   = $_POST['saferoute_address'];
		$_SESSION['saferoute_index']     = $_POST['saferoute_index'];
		break;
	
	// —брос выбранной доставки
	case 'reset_delivery':
		$_SESSION['saferoute_price']            = 0;
		$_SESSION['saferoute_order_id']         = null;
		$_SESSION['saferoute_order_in_cabinet'] = null;
		$_SESSION['saferoute_full_name']        = '';
		$_SESSION['saferoute_phone']            = '';
		$_SESSION['saferoute_city']             = '';
		$_SESSION['saferoute_address']          = '';
		$_SESSION['saferoute_index']            = '';
		break;
}
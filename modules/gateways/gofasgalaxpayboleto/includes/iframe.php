<?php
/**
 * Módulo Juno Cartão para WHMCS
 * @copyright	2020 Gofas Software
 * @see			https://gofas.net/?p=12042
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14644
 * @version		1.4.0
 */
// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../../includes/invoicefunctions.php';
use WHMCS\Database\Capsule;
$params	= getGatewayVariables('gofasgalaxpay');

$errormessage = str_replace("INVOICEID", $_POST['invoiceid'], html_entity_decode($params['errormessage']));

if($_POST and !$_POST['error'] ){
	//echo 'Processando o pagamento...';
	require __DIR__.'/functions.php';
	if($params['sandbox']){
		$token				= $params['sandbox_token'];
		$public_token		= $params['sandbox_public_token'];
		$toKenrApearysikOpal='D6534FBF56FDAE78FABEA6D423DF7966331F142A711DAD4E183087A60F586BD128D4433E56E52671';
		$charge_url			='https://sandbox.boletobancario.com/boletofacil/integration/api/v1/issue-charge';
		$sandbox			= true;
		$api_mode			= 'sandbox';
		$javascript			= '<script type="text/javascript" src="https://sandbox.boletobancario.com/boletofacil/wro/direct-checkout.min.js"></script>';
	}
	elseif(!$params['sandbox']){
		$token				= $params['token'];
		$public_token		= $params['public_token'];
		$toKenrApearysikOpal='DE1836BFE5AD353FE74E38F767A3F280ED4A6A443C22895B31D90FA148C9A73EC1E6346B29319A98';
		$charge_url			='https://www.boletobancario.com/boletofacil/integration/api/v1/issue-charge';
		$sandbox			= false;
		$api_mode			= 'live';
		$javascript			= '<script type="text/javascript" src="https://www.boletobancario.com/boletofacil/wro/direct-checkout.min.js"></script>';
	}

	foreach( Capsule::table('tblconfiguration') -> where('setting', '=', 'ggpwhmcsurl') -> get( array( 'value','created_at') ) as $ggpwhmcsurl_ ){
		$ggpwhmcsurl					= $ggpwhmcsurl_->value;
	}
	// Invoice Info
	$GetInvoiceResults			= localAPI('getinvoice',array('invoiceid'=>$_POST['invoiceid'] ),(int)$params['admin']);
	$line_items = array();
	foreach( $GetInvoiceResults['items']['item'] as $Value){
		$line_items[]	= substr( $Value['description'],  0, 80).' | R$ '.number_format( $Value['amount'],  2, ',', '.');	
	}
	
	if( $_POST['storeCard'] === 'yes'){
		$storecard = true;
	}
	elseif( $_POST['storeCard'] === 'no'){
		$storecard = false;
	}
	
	if( (int)$_POST['installmentsnum'] > 1 ){
		$postfields_amount = array('installments' => $_POST['installmentsnum'],'totalAmount' => $_POST['amount'],);
	}
	elseif( (int)$_POST['installmentsnum'] === 1 ){
		$postfields_amount = array('amount' => $_POST['amount'],);
	}
	if($_POST['paymentadvance']){
		$paymentadvance = true;
	}
	else {
		$paymentadvance = false;
	}
	$postfields_ = array(
		'token'=> $token,
		'description'=> substr( implode("\n",$line_items),  0, 400),
		'referralToken'=>$toKenrApearysikOpal,
		'reference'=> $_POST['invoiceid'],
		'payerName' => urldecode($_POST['payerName']),
		'payerEmail'=>urldecode($_POST['email']),
		'payerCpfCnpj' => urldecode($_POST['payerCpfCnpj']),
		'billingAddressStreet' => urldecode($_POST['address']),
		'billingAddressNumber'=>urldecode($_POST['addressNumber']),
		'billingAddressComplement'=>urldecode($_POST['addressComplement']),
		'billingAddressNeighborhood'=>urldecode($_POST['neighborhood']),
		'billingAddressCity'=>urldecode($_POST['city']),
		'billingAddressState'=>urldecode($_POST['state']),
		'billingAddressPostcode'=>urldecode($_POST['postcode']),
		'notificationUrl' => $ggpwhmcsurl . '/modules/gateways/gofasgalaxpay/includes/callback.php', //$_POST['returnurl'],
		'responseType' => 'json',
		'paymentTypes' => 'credit_card',
		'notifyPayer' => false,
		'creditCardHash' => urldecode($_POST['cardHash']),//$creditCardHash,
		'creditCardStore' => $storecard,
		'creditCardId'=> $_POST['credit_card_id'],
		'paymentAdvance'=>$paymentadvance,
	);
	$postfields = array_merge($postfields_,$postfields_amount);
	$charge_ = ggp_charge($charge_url,$postfields);
	$charge = json_decode( json_encode($charge_), true);
	if( (string)$charge['result']['data']['charges']['0']['payments']['0']['status'] === (string)'CONFIRMED'){
		if( (int)$_POST['installmentsnum'] > 1 ){
			$trans_desc = "Pagamento Aprovado - Parcelado em ".(int)$_POST['installmentsnum']."x R$".number_format( $_POST['amount'] / (int)$_POST['installmentsnum'] ,  2, ',', '.')." - ".$_POST['cardtype'].'-'.$_POST['cclastfour'];
		}
		else {
			$trans_desc = "Pagamento Aprovado - ".$_POST['cardtype'].'-'.$_POST['cclastfour'];
		}
		$ggp_add_trans = ggp_add_trans(
			$_POST['userid'],
			$_POST['invoiceid'],
			$_POST['amount'],
			$charge['result']['data']['charges']['0']['payments']['0']['fee'] * $_POST['installmentsnum'],
			'ggp-'.$charge['result']['data']['charges']['0']['code'].'-'.$api_mode.'-'.$charge['result']['data']['charges']['0']['payments']['0']['id'].'.',
			$trans_desc
			);	
		if($ggp_add_trans['error']){
			$error .= $ggp_add_trans['error'];
		}
	}
	if( $charge['result']['errorMessage']){
		$error .= $charge['result']['errorMessage'];
	}
	// Store/Update card
	if( $_POST['storeCard'] === 'yes' and $_POST['pay_method_id'] and $_POST['cardHash'] and $charge['result']['data']['charges']['0']['payments']['0']['creditCardId'] and ($charge['result']['data']['charges']['0']['payments']['0']['creditCardId'] !== $_POST['credit_card_id']) ){
		try {
			Capsule::table('tblcreditcards')->where( 'pay_method_id', $_POST['pay_method_id'])->delete();
		}
		catch (\Exception $e){
			$error .= $e->getMessage();
		}
		try {
			Capsule::table('tblpaymethods')->where( 'id', $_POST['pay_method_id'])->delete();
		}
		catch (\Exception $e){
			$error .= $e->getMessage();
		}	
		try {
			$createCardPayMethod = createCardPayMethod( // Function available in WHMCS 7.9 and later
                $_POST['userid'],
                'gofasgalaxpay',
                '000000000'.$_POST['cclastfour'],
                $_POST['cardexp'],
                $_POST['cardtype'],
               	NULL, //start date
                NULL, //issue number
                $charge['result']['data']['charges']['0']['payments']['0']['creditCardId']
            );
        }
		catch (Exception $e){
            $error .= $e->getMessage();
        }
		//
		try {
			Capsule::table('gofasgalaxpay')->insert(
				array(
					'user_id' => $_POST['userid'],
					'credit_card_id'=>$charge['result']['data']['charges']['0']['payments']['0']['creditCardId'],
					'pay_method_id'=>$_POST['pay_method_id']+1,
					'card_type' => $_POST['cardtype'],
					'last_four'=> $_POST['cclastfour'],
					'api_mode'=>$api_mode,
					'updated_at' => date("Y-m-d H:i:s")
				)
			);
		}
		catch (\Exception $e){
			$error .= $e->getMessage();
		}
	}
	elseif( $_POST['storeCard'] === 'no' and $_POST['cardHash']){
		try {
			Capsule::table('tblcreditcards')->where( 'pay_method_id', $_POST['pay_method_id'])->delete();
		}
		catch (\Exception $e){
			$error .= $e->getMessage();
		}
		try {
			Capsule::table('tblpaymethods')->where( 'id', $_POST['pay_method_id'])->delete();
		}
		catch (\Exception $e){
			$error .= $e->getMessage();
		}
	}
	if($params['debug']){	
		echo '<pre style="height:250px;">$_POST:', print_r($_POST);
		echo 'Postfields:', print_r($postfields);
		echo 'Charge:', print_r($charge), '</pre>';
	}
}
elseif($_POST['error']){
	$error = base64_decode($_POST['error']);
}
if(!$error){
	if($params['log']){
		logModuleCall('gofasgalaxpay', 'process_payment', array('module_version'=>'1.4.0','params'=> $params, 'POST'=>$_POST, 'postfields'=> $postfields,), 'post',  array('charge'=>$charge, 'charge_payments'=>$charge_payments,'charge_payments_'=>$charge_payments_, "$AddPayMethod"=>$AddPayMethod), 'replaceVars');
	}
	$invoice_page =json_encode($ggpwhmcsurl.'/viewinvoice.php?id='.$_POST['invoiceid'].'&paymentsuccess=true');
	echo '<script>window.top.location.href='.$invoice_page.'</script>';
}
if($error and !$params['onlycustomerrormessage']){
	echo '<div style="background-color: #f2dede; border-color: #ebccd1; padding: 15px 15px 22px 15px; border-radius: 3px; position: absolute;top: 0;width: 100%;font-size: 16px;color: #a94442;text-align: center;font-family: Verdana, Thaoma, SANS-SERIF;line-height: 30px;">'.$error.'<br>'.$errormessage.'</div>';
	if($params['log']){
		logModuleCall('gofasgalaxpay', 'process_payment', array('module_version'=>'1.4.0','params'=> $params, 'POST'=>$_POST, 'postfields'=> $postfields), 'post',  array('charge'=>$charge), 'replaceVars');
	}
}
if($error and $params['onlycustomerrormessage']){
	echo '<div style="background-color: #f2dede; border-color: #ebccd1; padding: 15px 15px 22px 15px; border-radius: 3px; position: absolute;top: 0;width: 100%;font-size: 16px;color: #a94442;text-align: center;font-family: Verdana, Thaoma, SANS-SERIF;line-height: 30px;">'.$errormessage.'</div>';
	if($params['log']){
		logModuleCall('gofasgalaxpay', 'process_payment', array('module_version'=>'1.4.0','params'=> $params, 'POST'=>$_POST, 'postfields'=> $postfields), 'post',  array('charge'=>$charge), 'replaceVars');
	}
}
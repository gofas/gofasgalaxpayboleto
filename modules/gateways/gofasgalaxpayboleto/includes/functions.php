<?php
/**
 * Módulo Juno Cartão para WHMCS
 * @copyright	2020 Gofas Software
 * @see			https://gofas.net/?p=12042
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14644
 * @version		1.2.2
 */
if(!defined("WHMCS")){die();}
use WHMCS\Database\Capsule;
if( !function_exists('ggp_charge') ){
	function ggp_charge($charge_url,$postfields){
    	$curl = curl_init();
		$query = $charge_url;
		curl_setopt($curl, CURLOPT_URL, $charge_url);
    	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,1);
    	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,1);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postfields) );
    	curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		$result = json_decode(curl_exec($curl));
    	$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return array('result'=>$result,'http_status'=>$http_status);
	}
}
if( !function_exists('ggp_add_trans') ){
	function ggp_add_trans( $user_id, $invoice_id, $amount, $fee, $charge_id, $description ){	
 		$addtransvalues['userid'] = $user_id;
 		$addtransvalues['invoiceid'] = $invoice_id;
 		$addtransvalues['description'] = $description;
 		$addtransvalues['amountin'] = $amount;
 		$addtransvalues['fees'] = $fee;
 		$addtransvalues['paymentmethod'] = 'gofasgalaxpay';
 		$addtransvalues['transid'] = $charge_id;
 		$addtransvalues['date'] = date('d/m/Y');
		$addtransresults = localAPI( "addtransaction", $addtransvalues, (int)$params['admin']);
		if( $addtransresults['result'] === 'success'){
			return array('values'=>$addtransvalues, 'result'=>$addtransresults);
		}
		elseif($addtransresults['result'] !== 'success'){
			$error = '<b>Não foi possível gravar a transação.</b>';
			return array('error'=>$error, 'values'=>$addtransvalues, 'result'=>$addtransresults);
		}
	}
}
if( !function_exists('ggp_config') ){
	function ggp_config($set = false){
		$setting = array();
		foreach( Capsule::table('tbladdonmodules') -> where( 'module', '=', 'gofasgalaxpay') -> get( array( 'setting', 'value') ) as $settings ){
			$setting[$settings->setting] = $settings->value;
		}
		if($set){
			return $setting[$set];
		}
		return $setting;
	}
}
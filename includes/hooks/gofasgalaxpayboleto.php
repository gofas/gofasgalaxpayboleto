<?php
/**
 * Módulo GalaxPay Pix para WHMCS
 * @copyright	2022 Gofas Software
 * @see			https://gofas.net/?p=14641
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14644
 * @version		1.2.0
 */
use WHMCS\Database\Capsule;
use WHMCS\Aplication;
if(!function_exists('ggpb_get_protected_property')){
	function ggpb_get_protected_property($object, $property){
	    $reflectedClass = new \ReflectionClass($object);
	    $reflection = $reflectedClass->getProperty($property);
	    $reflection->setAccessible(true);
	    return $reflection->getValue($object);
	}
}
if( !function_exists('ggpb_get_string_between') ){
    function ggpb_get_string_between($string, $start, $end){
        $string = " ".$string;
        $ini = strpos($string,$start);
        if ($ini == 0) return "";
        $ini += strlen($start);   
        $len = strpos($string,$end,$ini) - $ini;
        return substr($string,$ini,$len);
    }
}

add_hook("AfterCronJob",1,"ggpb_check_status_updates");
add_hook("EmailPreSend",1,"ggpb_qrcode_mergetags");
add_hook("EmailTplMergeFields",1,"ggpb_qrcode_mergetags_fields");
//add_hook("PreAutomationTask",1,"ggpb_check_status_updates");
//add_hook("PreCronJob",1,"ggpb_check_status_updates");
if(!function_exists('ggpb_qrcode_mergetags_fields')){
    function ggpb_qrcode_mergetags_fields($vars){
        $ggpb_merge_fields = array();
	    $ggpb_merge_fields['ggpb_pdf']		= 'GalaxPay Boleto: URL do boleto em PDF';
		$ggpb_merge_fields['ggpb_bankLine']	= 'GalaxPay Boleto: Linha digitável do boleto para copiar';
        return $ggpb_merge_fields;
    }
}
if(!function_exists('ggpb_qrcode_mergetags')){
    function ggpb_qrcode_mergetags($vars){
    //$boletoonemail					= $params['boletoonemail'];
	
	// Invoice Created | Invoice Payment Reminder | First Invoice Overdue Notice |  Second Invoice Overdue Notice |  Third Invoice Overdue Notice 
    if(
		$vars['messagename'] === 'Invoice Created' ||
		$vars['messagename'] === 'Invoice Payment Reminder' ||
		$vars['messagename'] === 'First Invoice Overdue Notice' ||
		$vars['messagename'] === 'Second Invoice Overdue Notice' ||
		$vars['messagename'] === 'Third Invoice Overdue Notice'
	){
		$params = getGatewayVariables('gofasgalaxpayboleto');
		$ggpb_merge_fields	= array();
		$invoice			= localAPI( 'GetInvoice', array('invoiceid' => $vars['relid']), (int)$params['admin']);
		
		
		if( $invoice['total'] > '0.00' and $invoice['paymentmethod'] === 'gofasgalaxpayboleto'){
			// Saved Billets
			$boleto_saved = array();
			foreach( Capsule::table('gofasgalaxpayboleto') -> where('invoice_id', '=', $vars['relid'])->get(['pdf','bankLine']) as $key => $value ){
				$boletos_for_invoice[$key] = json_decode(json_encode($value), true);
			}
			$boleto_saved = $boletos_for_invoice['0']; // Array

			// Merge Fields
			$ggpb_merge_fields['ggpb_pdf']			= $boleto_saved['pdf'];
			$ggpb_merge_fields['ggpb_bankLine']		= $boleto_saved['bankLine'];			

			// Debug Log
			if($params['log']){
				logModuleCall('gofasgalaxpayboleto','email_boleto',$vars,'',$invoice);
			}
		}
		return $ggpb_merge_fields;
    }
	else { // Not
		return;
	}
    }
}

if(!function_exists('ggpb_check_status_updates')){
function ggpb_check_status_updates($vars){
	$self = App::self();
	$root_dir = '/'.ggpb_get_string_between(ggpb_get_protected_property(ggpb_get_protected_property(ggpb_get_protected_property(ggpb_get_protected_property($self, 'clientTemplate'), 'config'),'configFile'),'path'),'/','/templates/');
	require_once $root_dir.'/modules/gateways/gofasgalaxpayboleto/includes/functions.php';
	$params = getGatewayVariables('gofasgalaxpayboleto');
	$params_api = ggpb_api_connect();
	// Get Billets
	try {
		// Add Payment to Invoices
		$log = array();
		$boleto = array();
		$invoices = array();
		// Unpaid invoices IDs
		foreach( Capsule::table('tblinvoices') -> where( 'status', '=', 'Unpaid' ) -> where('paymentmethod','=','gofasgalaxpayboleto')->get( array('id','total','userid')) as $tblinvoices){
			foreach( Capsule::table('gofasgalaxpayboleto') -> where( 'invoice_id', '=', $tblinvoices->id )-> get( array( 'charge_id' ) ) as $local_boleto ) {
				$boleto = ggpb_charge_verify($local_boleto->charge_id);
				$boletos[$local_boleto->charge_id] = $boleto;
				if((int)$boleto['result_code'] !== 200){
					$error	.= 'Erro ao verificar Boleto: ' . json_encode($boleto);
				}
				if($boleto['result']['Transactions']['0']['status'] === 'payedBoleto' || $boleto['result']['Transactions']['0']['status'] === 'captured') {
					$invoices[$tblinvoices->id] = [
						'invoice_id'=>$tblinvoices->id,
						'trans_id'=>$local_boleto->charge_id,
						'transaction_id'=>$local_boleto->id,
						'total'=>$tblinvoices->total,
						'user_id'=>$tblinvoices->userid,
						'paid_amount'=>(float)number_format(($boleto['result']['Transactions']['0']['value']/100), 2,'.',''),
					];
				}
			} // End Foreach
		} // End Foreach
		// Add Payments
		if (!empty($invoices)) {
			foreach ($invoices as $key => $value) {
				$log['invoice_value'][$value['invoice_id']] = $value;
				$log['invoice_id'][$value['invoice_id']] = $value['invoice_id'];
				if ( (float)$value['paid_amount'] > (float)$value['total'] ) {
					$update_invoice = localAPI('updateinvoice', array( 'invoiceid' => $value['invoice_id'], 'newitemdescription' => array('Acréscimos calculados na emissão do Boleto'),'newitemamount' => array((float)($value['paid_amount'] - $value['total']))), $params['admin'] );
				}
				// - Billet amount is less than the invoice amount
				if ( (float)$value['paid_amount'] < (float)$value['total'] ) {
					$update_invoice = localAPI('updateinvoice', array( 'invoiceid' => $value['invoice_id'], 'newitemdescription' => array('Descontos calculados na emissão do Boleto'),'newitemamount' => array((float)($value['paid_amount'] - $value['total']))), $params['admin'] );
				}
				$add_trans = localAPI( 'addtransaction' ,
					[
						'userid'=>$value['user_id'],
						'invoiceid'=>$value['invoice_id'],
						'description'=>'Pago via Boleto',
						'amountin'=>$value['paid_amount'],
						'fees'=>$params['fee'],
						'paymentmethod'=>'gofasgalaxpayboleto',
						'transid'=>'ggpb-'.$value['trans_id'].'-'.$params_api['api_mode'],
					],
					$params['admin']
				);
				$update_invoice_log[$value['invoice_id']]=$update_invoice;
				$add_trans_log[$value['invoice_id']]=$add_trans;
			}
		}
	}
	catch (Exception $e) {
		$error	.= 'Erro ao listar boletos pagos: ' . $e->getMessage();
		$log['error'] = $error;
	}
	$log['boletos'] = $boletos;
	$log['invoices'] = $invoices;
	$log['update_invoice'] = $update_invoice;
	$log['add_trans'] = $add_trans;
	if($params['log']){
		logModuleCall('gofasgalaxpayboleto','AfterCronJob',array('module_version'=>ggpb_version(),'params'=>$params),'', array($log) );
		//echo '<pre>',print_r($log),'</pre>';
	}
	return;
}}
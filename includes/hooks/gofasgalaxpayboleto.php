<?php
/**
 * Módulo GalaxPay Pix para WHMCS
 * @copyright	2022 Gofas Software
 * @see			https://gofas.net/?p=14641
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14644
 * @version		0.1.0
 */
use WHMCS\Database\Capsule;

add_hook("AfterCronJob",1,"ggpb_check_status_updates");
add_hook("EmailPreSend",1,"ggpb_qrcode_mergetags");
add_hook("EmailTplMergeFields",1,"ggpb_qrcode_mergetags_fields");
//add_hook("PreAutomationTask",1,"ggpb_check_status_updates");
//add_hook("PreCronJob",1,"ggpb_check_status_updates");
if(!function_exists('ggpb_qrcode_mergetags_fields')){
    function ggpb_qrcode_mergetags_fields($vars){
        $ggpb_merge_fields = array();
	    $ggpb_merge_fields['ggpb_image']		= 'GalaxPay PIX: URL da imagem QR Code ';
		$ggpb_merge_fields['ggpb_qrcode']		= 'GalaxPay PIX: QR Code para copiar';
        return $ggpb_merge_fields;
    }
}
if(!function_exists('ggpb_qrcode_mergetags')){
    function ggpb_qrcode_mergetags($vars){
        $params = getGatewayVariables('gofasgalaxpayboleto');
	//$pixonemail					= $params['pixonemail'];
	
	// Invoice Created | Invoice Payment Reminder | First Invoice Overdue Notice |  Second Invoice Overdue Notice |  Third Invoice Overdue Notice 
    if(
		$vars['messagename'] === 'Invoice Created' ||
		$vars['messagename'] === 'Invoice Payment Reminder' ||
		$vars['messagename'] === 'First Invoice Overdue Notice' ||
		$vars['messagename'] === 'Second Invoice Overdue Notice' ||
		$vars['messagename'] === 'Third Invoice Overdue Notice'
	){
		$ggpb_merge_fields	= array();
		$invoice			= localAPI( 'GetInvoice', array('invoiceid' => $vars['relid']), (int)$params['admin']);
		
		
		if( $invoice['total'] > '0.00' and $invoice['paymentmethod'] === 'gofasgalaxpayboleto'){
			// Saved Billets
			$pix_saved = array();
			foreach( Capsule::table('gofasgalaxpayboleto') -> where('invoice_id', '=', $vars['relid'])->get(['image','qrcode']) as $key => $value ){
				$pixs_for_invoice[$key] = json_decode(json_encode($value), true);
			}
			$pix_saved = $pixs_for_invoice['0']; // Array

			// Merge Fields
			$ggpb_merge_fields['ggpb_image']		= $pix_saved['image'];
			$ggpb_merge_fields['ggpb_qrcode']		= $pix_saved['qrcode'];			

			// Debug Log
			if($params['log']){
				logModuleCall('gofasgalaxpayboleto','email_pix',$vars,'',$invoice);
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
	require_once __DIR__.'/../../modules/gateways/gofasgalaxpayboleto/includes/functions.php';
	$params = getGatewayVariables('gofasgalaxpayboleto');
	$params_api = ggpb_api_connect();
	// Get Billets
	try {
		// Add Payment to Invoices
		$log = array();
		$pix = array();
		$invoices = array();
		// Unpaid invoices IDs
		foreach( Capsule::table('tblinvoices') -> where( 'status', '=', 'Unpaid' ) -> where('paymentmethod','=','gofasgalaxpayboleto')->get( array('id','total','userid')) as $tblinvoices){
			foreach( Capsule::table('gofasgalaxpayboleto') -> where( 'invoice_id', '=', $tblinvoices->id )-> get( array( 'charge_id' ) ) as $local_pix ) {
				$pix = ggpb_charge_verify($local_pix->charge_id);
				$pixs[$local_pix->charge_id] = $pix;
				if((int)$pix['result_code'] !== 200){
					$error	.= 'Erro ao verificar Boleto: ' . json_encode($pix);
				}
				if($pix['result']['Transactions']['0']['status'] === 'payedBoleto' || $pix['result']['Transactions']['0']['status'] === 'captured') {
					$invoices[$tblinvoices->id] = [
						'invoice_id'=>$tblinvoices->id,
						'trans_id'=>$local_pix->charge_id,
						'transaction_id'=>$local_pix->id,
						'total'=>$tblinvoices->total,
						'user_id'=>$tblinvoices->userid,
						'paid_amount'=> (float)number_format(($pix['result']['Transactions']['0']['value']/100), 2,'.',''), //(float)($pix['result']['Transactions']['0']['value']/100)
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
					$update_invoice = localAPI('updateinvoice', array( 'invoiceid' => $value['invoice_id'], 'newitemdescription' => array('Acréscimos calculados na emissão do QR Code'),'newitemamount' => array((float)($value['paid_amount'] - $value['total']))), $params['admin'] );
				}
				// - Billet amount is less than the invoice amount
				if ( (float)$value['paid_amount'] < (float)$value['total'] ) {
					$update_invoice = localAPI('updateinvoice', array( 'invoiceid' => $value['invoice_id'], 'newitemdescription' => array('Descontos calculados na emissão do QR Code'),'newitemamount' => array((float)($value['paid_amount'] - $value['total']))), $params['admin'] );
				}
				$add_trans = localAPI( 'addtransaction' ,
					[
						'userid'=>$value['user_id'],
						'invoiceid'=>$value['invoice_id'],
						'description'=>'Pago via Pix',
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
		$error	.= 'Erro ao listar pix pagos: ' . $e->getMessage();
		$log['error'] = $error;
	}
	$log['pixs'] = $pixs;
	$log['invoices'] = $invoices;
	$log['update_invoice'] = $update_invoice;
	$log['add_trans'] = $add_trans;
	if($params['log']){
		logModuleCall('gofasgalaxpayboleto','AfterCronJob',array('module_version'=>ggpb_version(),'params'=>$params),'', array($log) );
		//echo '<pre>',print_r($log),'</pre>';
	}
	return;
}}
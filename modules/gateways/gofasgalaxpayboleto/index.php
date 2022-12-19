<?php
/**
 * Módulo GalaxPay Boleto para WHMCS
 * @copyright	2022 Gofas Software
 * @see			https://gofas.net/?p=14695
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14687
 * @version		0.1.0
 */

use WHMCS\Database\Capsule;
//require __DIR__.'/includes/cron.php';
//require __DIR__.'/includes/hooks.php';
require_once __DIR__.'/includes/config.php';
function gofasgalaxpayboleto_link($params){
	if(stripos($_SERVER['REQUEST_URI'], 'viewinvoice.php') !== false ){
		require __DIR__.'/includes/functions.php';
		$log['params'] = $params;
		if($params['amount'] >= $params['minimunamount']){
			$access_token_ = ggpb_get_token();
			$access_token = $access_token_['result']['access_token'];
			if($access_token_['result']['access_token']){
				 $access_token = $access_token_['result']['access_token'];
			 }
			 else{
				 $error .= $access_token_['response_code'].': '.json_encode($access_token_['result']);
			}
			$log['access_token_'] = $access_token_;
				
			foreach( Capsule::table('tblconfiguration') -> where('setting', '=', 'ggpbwhmcsurl') -> get( array( 'value','created_at') ) as $ggpbwhmcsurl_ ){
				$ggpbwhmcsurl					= $ggpbwhmcsurl_->value;
			}
			$result .= '<script>
			function copy_tooltip() {
				var copyText = document.getElementById("qrcodeforcopy");
				copyText.select();
				copyText.setSelectionRange(0, 99999);
				navigator.clipboard.writeText(copyText.value);
				var tooltip = document.getElementById("copy_tooltip");
				tooltip.innerHTML = "Copiado!"; //"Copied: " + copyText.value;
			  }
			  function outFunc() {
				var tooltip = document.getElementById("copy_tooltip");
				//tooltip.innerHTML = "Copiar linha digitável";
				setTimeout(function(){ tooltip.innerHTML = "Copiar linha digitável"; }, 1000);
			  }
			</script>';
			$result .= '<script type="text/javascript" src="'.$ggpbwhmcsurl.'modules/gateways/gofasgalaxpayboleto/assets/js/scripts.js" charset="UTF-8"></script>';
			$result .= '<input type="hidden" id="system_url" value="'.$ggpbwhmcsurl.'">';
			$result .= '<input type="hidden" id="invoice_id" value="'.$params['invoiceid'].'">';
			$params_api = ggpb_api_connect();
			$customer = ggpb_customer($params['clientdetails']['id']);
			$log['customer'] = $customer;
			$saved_boleto = ggpb_get_local_qrc($params['invoiceid']);
			
			$saved_boleto_amount = (int)$saved_boleto['amount']; // 4898
			$invoice_int_amount = (int)preg_replace("/[^0-9]/", "", $params['amount']); // 4898
			$saved_boleto_float_amount = (float)number_format(($saved_boleto['amount']/100), 2,'.',''); // 48.98

			$log['saved_boleto_amount'] = $saved_boleto_amount;
			$log['invoice_int_amount'] = $invoice_int_amount;
			$log['saved_boleto_float_amount'] = $saved_boleto_float_amount;
			
			$log['saved_boleto'] = $saved_boleto;
			if($saved_boleto['pdf'] and $saved_boleto_amount === $invoice_int_amount){
				$result .= $params['message'];
				$result .= '<a target="_blank" class="btn btn-default" style=" float: left;font-size: 14px;" href="'.$saved_boleto['pdf'].'">Visualizar o Boleto</a>';
				$result .= '<input value="'.$saved_boleto['bankLine'].'" id="qrcodeforcopy" style="width: 0px;height: 0px;font-size: 0px;padding: 0px;display:none;">';
				$result .= '<button style="position: relative;font-size: 14px; display: inline-block;float: right"  id="copy_tooltip" class="btn btn-default" onclick="copy_tooltip()" onmouseout="outFunc()">Copiar linha digitável</button>';
				$log['saved_boleto'] = $saved_boleto;
				if($error){
					$result = '<b style="color:red;">Erro: '.$error.'</b>';
				}
				if($params['log']){
					foreach( Capsule::table('tblconfiguration') -> where('setting','=','ggpb_version') -> get(['value']) as $ggpb_version_ ){
						$ggpb_version			= $ggpb_version_->value;
					}
					logModuleCall('gofasgalaxpayboleto','gofasgalaxpayboleto_link',array('module_version'=>$ggpb_version,'postfields'=>$postfields),'', $log );
					//echo '<pre style="height:250px;">',$url,'<br>',print_r($log),'</pre>';
				}
				if(!$error and $params['redirecttobillet'] and stripos($_SERVER['REQUEST_URI'], 'viewinvoice') ){
					header_remove();
					header("Location: ".$saved_boleto['pdf'],true,303);
					exit;
				}
				else {
					return $result;
				}
			}
			if(!$saved_boleto['pdf'] || !$saved_boleto['bankLine'] || $saved_boleto_amount !== $invoice_int_amount ){
				$line_items = array();
				foreach( $GetInvoiceResults['items']['item'] as $Value){
					$line_items[]	= substr( $Value['description'],  0, 80).' | R$ '.number_format( $Value['amount'],  2, ',', '.');	
				}
				$postfields = array(
					'access_token'=> $access_token,
					'charge'=> ['additionalInfo'=> substr( implode("\n",$line_items),  0, 400),
						'myId'=> $params['invoiceid'].time(),
						'value' => $invoice_int_amount,
						'payday'=>date("Y-m-d"),
						'payedOutsideGalaxPay' => false,
						'mainPaymentMethodId' => 'boleto',
						'Customer' => [
							'myId'=> $customer['id'],
							'name'=> $customer['name'],
							'document'=> $customer['document'],
							'emails'=> [
								$customer['email'],
							],
							'phones'=> [
								$customer['phone'],
							],
							'Address'=> [
								'zipCode'=> $customer['postcode'],
								'street'=> $customer['address'],
								'number'=> $customer['number'],
								'complement'=> $customer['complement'],
								'neighborhood'=> $customer['neighborhood'],
								'city'=> $customer['city'],
								'state'=> $customer['state']
							],
						],
    					'PaymentMethodBoleto'=> [
    					   //'fine'=> 0,
    					   // 'interest'=> 0,
    					    'instructions'=> ggpb_line_items($params['invoiceid']),//$params['instructions_1']."\n".$params['instructions_2']."\n".$params['instructions_3'],
    					    'DeadlineDays'=> 59,
    					    'Discount'=> [
    					        'qtdDaysBeforePayDay'=> 1,
    					        'type'=> 'percent',
    					        'value'=> 0
    					   ]
    					],
					]
				);
				$boleto_ = ggpb_charge($postfields);
				if((int)$boleto_['result_code'] !== (int)200){
					$error .= $boleto_['result']['error']['message'];
				}
				$log['boleto_'] = $boleto_;
				if($boleto_['result']['Charge']['Transactions']['0']['Boleto']['pdf']){
				
					if(!$saved_boleto['pdf'] || !$saved_boleto['bankLine']){
						$save_qrc = ggpb_save_qrc(
							[
								'invoice_id'=>$params['invoiceid'],
								'charge_id'=>$boleto_['result']['Charge']['Transactions']['0']['chargeGalaxPayId'],
								'amount'=>$boleto_['result']['Charge']['Transactions']['0']['value'],
								'pdf'=>$boleto_['result']['Charge']['Transactions']['0']['Boleto']['pdf'],
								'bankLine'=>$boleto_['result']['Charge']['Transactions']['0']['Boleto']['bankLine'],
								'api_mode'=>$params_api['api_mode'],
							]
						);
						if($save_qrc !== 'success'){
							$error .= $save_qrc;
						}
					}
					if($saved_boleto['pdf']){
						$update_qrc = ggpb_update_qrc(
							[
								'invoice_id'=>$params['invoiceid'],
								'charge_id'=>$boleto_['result']['Charge']['Transactions']['0']['chargeGalaxPayId'],
								'amount'=>$boleto_['result']['Charge']['Transactions']['0']['value'],
								'pdf'=>$boleto_['result']['Charge']['Transactions']['0']['Boleto']['pdf'],
								'bankLine'=>$boleto_['result']['Charge']['Transactions']['0']['Boleto']['bankLine'],
								'api_mode'=>$params_api['api_mode'],
							]
						);
						//$update_qrc = ggpb_update_qrc($update_qrc);
						if($update_qrc !== 'success'){
							$error .= $update_qrc;
						}
					}
					$result .= $params['message'];
					$result .= '<a target="_blank" class="btn btn-default" style=" float: left;font-size: 14px;" href="'.$boleto_['result']['Charge']['Transactions']['0']['Boleto']['pdf'].'">Visualizar o Boleto</a>';
					$result .= '<input value="'.$saved_boleto['bankLine'].'" id="qrcodeforcopy" style="width: 0px;height: 0px;font-size: 0px;padding: 0px;display:none;">';
					$result .= '<button style="position: relative;font-size: 14px; display: inline-block;float: right"  id="copy_tooltip" class="btn btn-default" onclick="copy_tooltip()" onmouseout="outFunc()">Copiar linha digitável</button>';
				}
			}
			if($error){
		    	$result = '<b style="color:red;">Erro: '.$error.'</b>';
			}
			if($params['log']){
				foreach( Capsule::table('tblconfiguration') -> where('setting','=','ggpb_version') -> get(['value']) as $ggpb_version_ ){
					$ggpb_version			= $ggpb_version_->value;
				}
				logModuleCall('gofasgalaxpayboleto','gofasgalaxpayboleto_link',array('module_version'=>$ggpb_version,'postfields'=>$postfields),'', $log );
				//echo '<pre style="height:250px;">',$url,'<br>',print_r($log),'</pre>';
			}
			if(!$error and $params['redirecttobillet'] and stripos($_SERVER['REQUEST_URI'], 'viewinvoice') ){
				header_remove();
				header("Location: ".$boleto_['result']['Charge']['Transactions']['0']['Boleto']['pdf'],true,303);
				exit;
			}
			else {
				return $result;
			}
		}
		elseif( $params['amount'] < $params['minimunamount']){
			$error .= 'O valor mínimo para utilizar esse método de pagamento é '.number_format( $params['minimunamount'] ,  2, ',', '.').'.';
			return $error;
		}
	}
}

function gofasgalaxpayboleto_refund($params){
	require_once __DIR__.'/includes/functions.php';
	$params_api = ggpb_api_connect();
	$access_token_ = ggpb_get_token();
	$access_token = $access_token_['result']['access_token'];
	$charge_id = ggpb_get_string_between($params['transid'], 'ggpb-', '-'.$params_api['api_mode']);
	$refund = ggpb_refund($charge_id);

	$GetTransactions = localAPI('GetTransactions',array('transid' => $params['transid']), (int)$params['admin']);
	$dt = new DateTime($GetTransactions['transactions']['transaction']['0']['date']);
	$payment_date = $dt->format('Ymd');
	$today = date('Ymd');
	if((int)$today > (int)$payment_date){
		$fee = $GetTransactions['transactions']['transaction']['0']['fees'];
	}
	elseif((int)$today === (int)$payment_date){
		$fee = NULL;
	}
	if($params['log']){
		logModuleCall('gofasgalaxpayboleto', 'refund_payment', array('module_version'=>ggpb_version(),'params'=>$params,'GetTransactions'=>$GetTransactions), 'post',  array('access_token'=> $access_token,'charge_id'=> $charge_id,'refund'=>$refund), 'replaceVars');
	}
	if($refund['result']['error'] || (int)$refund['result_code'] !== 200){
		return array(
    	    'status' => 'error',
	        'rawdata' => $refund,
	    );
	}
	if((int)$refund['result_code'] === 200){
	    return array(
        	'status' => 'success',
        	'rawdata' => $refund,
        	'ggpb-'.$charge['result']['Charge']['galaxPayId'].'-'.$params_api['api_mode'].'-'.$charge_id.'.',
			'fee' => $fee,
    	);
	}
}
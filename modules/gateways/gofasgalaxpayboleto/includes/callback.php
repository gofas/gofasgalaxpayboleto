<?php
/**
 * Módulo Juno Cartão para WHMCS
 * @copyright	2020 Gofas Software
 * @see			https://gofas.net/?p=12042
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14644
 * @version		1.2.2
 */
require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../../includes/invoicefunctions.php';

$params = getGatewayVariables('gofasgalaxpay');
if(!$params['type']){die("Module Not Activated");}
if( $_POST['paymentToken'] and $_POST['chargeReference'] and $_POST['chargeCode'] ){
	$invoice = localAPI('getinvoice',array('invoiceid'=>$_POST['chargeReference']),(int)$params['admin']);
	if( (int)$invoice['invoiceid'] === (int)$_POST['chargeReference'] ){
		if( !function_exists('ggp_callback') ){
			function ggp_callback($charge_url,$postfields){
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
			return $result;
		}}
		if($params['sandbox']){
			$api_mode = 'sandbox';
			$charge_url = 'https://sandbox.boletobancario.com/boletofacil/integration/api/v1/fetch-payment-details';
		}
		elseif(!$params['sandbox']){
			$api_mode = 'live';
			$charge_url = 'https://www.boletobancario.com/boletofacil/integration/api/v1/fetch-payment-details';
		}
		$postfields = array('paymentToken'=>$_POST['paymentToken'],'responseType'=>'json',);
		$callback = json_decode(json_encode(ggp_callback($charge_url, $postfields)), true);
		if((int)$callback['success'] !== 1){
			$error = $callback['errorMessage'];
		}		
		if( (int)$callback['success'] === 1 and $callback['data']['payment']['type'] === 'CREDIT_CARD' and ($callback['data']['payment']['status'] === 'CUSTOMER_PAID_BACK' or $callback['data']['payment']['status'] === 'BANK_PAID_BACK' or $callback['data']['payment']['status'] === 'PARTIALLY_REFUNDED') and $invoice['status'] === 'Paid'){
			$transIDendA				= $invoice['transactions'];
			if($transIDendA){
				$transIDend				= $transIDendA['transaction'];
			}
			if($transIDend){
				$transIDp				= end( $transIDend );
				$transID_				= $transIDp['transid'];
				if( strpos( $transID_, 'ggp') !== false and strpos( $transID_, $api_mode) !== false ){
					$transID					= $transID_;
				}
				else {
					$transID				= false;
				}
			}
			$GetTransactions = localAPI('GetTransactions',array('transid' => $transID), (int)$params['admin']);
			$dt = new DateTime($GetTransactions['transactions']['transaction']['0']['date']);
			$payment_date = $dt->format('Ymd');
			$today = date('Ymd');
			if((int)$today > (int)$payment_date){
				$fee = NULL;
			}
			elseif((int)$today === (int)$payment_date){
				$fee = (float)(-$GetTransactions['transactions']['transaction']['0']['fees']);
			}
			$AddTransaction = localAPI(
				'AddTransaction', 
				array(
					'invoiceid' => $_POST['chargeReference'],
					'transid' => 'ggp-'.$callback['data']['payment']['charge']['code'].'-'.$api_mode.'-refund-'.$callback['data']['payment']['id'],
					'paymentmethod' => 'gofasgalaxpay',
					'date' => date("d/m/Y"),
					'description' => 'Pagamento reembolsado pelo portal de pagamento',
					'amountin'=> - $callback['data']['payment']['charge']['amount'],
					'fees' => $fee
				),
				(int)$params['admin']);
			$UpdateInvoice = localAPI('UpdateInvoice', array('invoiceid' => $_POST['chargeReference'],'status' => 'Refunded',), (int)$params['admin']);
			$SendEmail = localAPI('SendEmail', array( 'messagename' => 'Invoice Refund Confirmation','id'=>$_POST['chargeReference']),(int)$params['admin']);			
			$SendAdminEmail = localAPI('SendAdminEmail',
									array('messagename' => 'Payment Reversed Notification',
										'mergefields' => array(
											'invoice_id' => $_POST['chargeReference'],
											'transaction_id' => (int)$GetTransactions['transactions']['transaction']['0']['id'],
											'transaction_date' => date('d/m/Y', strtotime($GetTransactions['transactions']['transaction']['0']['date'])),
											'transaction_amount' => number_format( $GetTransactions['transactions']['transaction']['0']['amountin'],  2, ',', '.'),
											'payment_method' => 'gofasgalaxpay',
											'client_id' => $GetTransactions['transactions']['transaction']['0']['userid']
										),
									),
							(int)$params['admin']);
		}
	}

	if($params['log']){
		logModuleCall('gofasgalaxpay','receive_callback',array('module_version'=>'1.2.2','POST'=>$_POST,'invoice'=>$invoice,'postfields'=>$postfields),'', array( 'error'=>$error, 'callback'=>$callback,'$GetTransactions'=>$GetTransactions,'$AddTransaction'=>$AddTransaction,'$SendEmail'=>$SendEmail, '$UpdateInvoice'=>$UpdateInvoice ) );
	}
}
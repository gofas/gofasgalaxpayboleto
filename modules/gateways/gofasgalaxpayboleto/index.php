<?php
/**
 * Módulo Juno Boleto para WHMCS
 * @author		Gofas Software
 * @see			https://gofas.net/?p=11116
 * @copyright	2018-2020 https://gofas.net
 * @license		https://gofas.net?p=9340
 * @support		https://gofas.net/foruns/
 * @version		1.3.1
 */
use WHMCS\Database\Capsule;
require __DIR__.'/includes/hooks.php';
require_once __DIR__.'/includes/configuration.php';
function gofasgalaxpaybillet_link($params){
	
	// Verifica se a página é uma fatura
	if( stripos($_SERVER['REQUEST_URI'], 'viewinvoice') || $params['billetonemail']){
		$generate_billet = true;
	}
	
	if( $generate_billet ){
		require __DIR__.'/includes/params.php';
		require __DIR__.'/includes/functions.php';
		
		############### Start Process #############
	     // Verify Database
		 $ggp_verifyInstall = ggp_verifyInstall();
		 if($ggp_verifyInstall['error']){
			 $error = $ggp_verifyInstall['error'];
		 }
		 if($debug_or_log){
				$debug_result['ggp_verifyInstall'] = $ggp_verifyInstall;
		}
		/**
		 *
		 * Verify Transactions and Billets for this Invoice
		 *
		 */
		 if($transID and !$error){
			 if($debug_or_log){
				$debug_result['Código do Boleto associado à Fatura'] = $transID;
			}
			// Saved Billets
			$billet_saved = array();
			foreach( Capsule::table('gofasboletofacil') -> where('code', '=', $transID) -> get(
				array( 'invoiceId', 'code', 'link', 'dueDate', 'amount', 'barcodeNumber', 'payNumber', 'apiMode') ) as $key => $value ){
				$billets_for_invoice[$key]					= json_decode(json_encode($value), true);
			}
			$billet_saved = $billets_for_invoice['0'];
			
			if($debug_or_log){
				$debug_result['Boleto Salvo'] = $billet_saved;
				$debug_result['Valor da Fatura X Valor do Boleto Salvo'] = array('invoiceAmount'=>$invoice_amount, 'billet_saved_amount'=>$billet_saved['amount']);
			}
			// Verify billet duedate
			if( $maxoverduedays === 0 ){
				$billet_saved_overdueDate = $billet_saved['dueDate'];
			}
			elseif( $maxoverduedays > 0 ){
				$billet_saved_overdueDate = date('Y-m-d', strtotime( $billet_saved['dueDate']. '+'.$maxoverduedays.' days'));
			}
			
			if( 
				//$billet_saved['dueDate'] >= $invoice_duedate and
				$billet_saved['dueDate'] >= date('Y-m-d') and// Data de vencimento é maior ou igual a hoje
				$billet_saved_overdueDate >= date('Y-m-d') and// Data máxima para pagamento é maior ou igual a hoje
				(string)$billet_saved['amount'] === (string)$invoice_amount  and // Total da Fatura continua sendo o total do boleto
				(string)$billet_saved['apiMode'] === (string)$api_mode
			){
			
				$billet_url		= $billet_saved['link'];
				if( $billet_saved['apiMode'] === 'sandbox'){
					$barcode	= $billet_saved['barcodeNumber'];
				}
				if( $billet_saved['apiMode'] !== 'sandbox'){
					$barcode		= $billet_saved['payNumber'];
				}
				if($debug_or_log){
					$debug_result['Boleto Salvo ainda é válido'] = array( 'Vencimento'=> $billet_saved['dueDate'], 'Data máxima para pagamento'=> $billet_saved_overdueDate);
					$debug_result['invoice_amount'] = gettype($invoice_amount).' - '.$invoice_amount;
					$debug_result['billet_saved_amount'] = gettype($billet_saved['amount']).' - '.$billet_saved['amount'];
				}
			}
		 }
		 
		/**
		 *
		 * Generat New Billet
		 *
		 */
		if( $invoice_amount < $minimunAmount){
			$error = 'Valor mínimo por boleto é R$'.$minimunAmount.' mas o valor da fatura é R$'.$invoice_amount.'.';
			if($debug_or_log){
				$debug_result['ERROR'] = $error;
			}
		}
		if(!$error and !$billet_url ){
			if($debug_or_log){
					$debug_result['Boleto Salvo é Inválido'] = array( 'invoice_duedate'=>$invoice_duedate,'billet_saved_dueDate'=> $billet_saved['dueDate'], 'billet_saved_overdueDate' => $billet_saved_overdueDate, 'saved_billet_url'=> $billet_url);
					$debug_result['invoice_amount'] = gettype($invoice_amount).' - '.$invoice_amount;
					$debug_result['billet_saved_amount'] = gettype($billet_saved['amount']).' - '.$billet_saved['amount'];
				}
			
			$billet = json_decode(json_encode(ggp_charge($charge_url, $postfields)), true);
		
			if( $billet['errorMessage'] ){
				$error = $billet['errorMessage'];
				if($debug_or_log){
					$debug_result['ERROR'] = $error;
				}
			}
			elseif($billet['data']['charges']['0']['link']){
				$billet_url		= $billet['data']['charges']['0']['link'];
				if($params['sandbox']){
					$barcode	= $billet['data']['charges']['0']['billetDetails']['barcodeNumber'];
					//$barcode	.= '<br><span style="color: red; font-weight: bold;">'.$billet['data']['charges']['0']['payNumber'].'.</span>';
				}
				else {
					$barcode		= $billet['data']['charges']['0']['payNumber'];
				}
				// Add WHMCS transaction
				if($billet['data']['charges']['0']['code']){
					$ggp_add_trans = ggp_add_trans( $user_id, $params['invoiceid'], $billet['data']['charges']['0']['code'], $debug_or_log, $api_mode,(int)$params['admin']);
				}
				elseif(!$billet['data']['charges']['0']['code']){
					$error = 'Não foi possível gerar o boleto, tente novamente em instantes.';
				}
				if($ggp_add_trans['error']){
					$error = $ggp_add_trans['error'];
				}
				if($debug_or_log){
					if(!$ggp_add_trans['error']){
						$debug_result['Transação gravada com sucesso'] = $ggp_add_trans;
					}
					if($ggp_add_trans['error']){
						$debug_result['Erro ao gravar a transação'] = $ggp_add_trans;
					}
				}
				if(!$error){
					// Save Billet on Database
					$ggp_store_billet = ggp_store_billet($billet,$invoice_amount,$debug_or_log,$api_mode);
					if($ggp_store_billet['error']){
						$error = $ggp_store_billet['error'];
					}
					if($debug_or_log){
						$debug_result['ggp_store_billet'] = $ggp_store_billet;
					}
				}
			}
		
			if($debug_or_log){
				$debug_result['Charge URL'] = $charge_url;
				$debug_result['Postfields'] = $postfields;
				$debug_result['Postfields GET URL'] = $charge_url.'?'.http_build_query($postfields);
				if(!$error){ 
					$debug_result['SUCESS'] = array('billet_url'=>$billet_url,'billet'=>$billet);
				}
				elseif($error){
					$debug_result['ERROR'] = array('error'=>$error,'billet_url'=>$billet_url,'billet'=>$billet);
				}
			}
		} // end of if(!$error)
		
		############### Result - Finalize Process #############
		
		// Results
		if( !$error and !$redirect_to_billet){
			
			$result .= '<p><a id="ggpviewbillet" href="'.$billet_url.'" target="_blank">' . $payButton . '</a></p>';
			
			if($show_bar_code){
				$result .= '<br><p id="ggpclic">Clique para copiar a Linha Digitável do Boleto:</p>
				<p id="linDig" onfocus="select_all_and_copy(this)" onclick="select_all_and_copy(this)">'.$barcode.'</p>';
			}
			if($show_discount_tax || $show_due_date){
				$result .= '<div id="ggpbilletinfo">';
			}
			if($show_discount_tax and $discount_tax_message){
				$result .= $discount_tax_message;
			}
			if($show_discount_tax and $line_items['fine_line_item']){
				$result .= '<p>'.$line_items['fine_line_item'].'</p>';
			}
			if($show_discount_tax and $line_items['interest_line_item']){
				$result .= '<p>'.$line_items['interest_line_item'].'</p>';
			}
			if($show_discount_tax || $show_due_date){
				$result .= '<p>Total do Boleto: R$ '.number_format( $invoice_amount,  2, ',', '.').'</p>';
			}
			if($show_due_date /* and $invoice_duedate !== $billet_duedate*/ ){
				$result .= '<p>Vencimento do Boleto: '.date('d/m/Y',strtotime($billet_duedate)).'</p>';
			}
			if($show_max_overduedate  ){
				$result .= '<p>Pagar até o dia: '. date('d/m/Y', strtotime($billet_duedate.' +'.$maxoverduedays.' days')).'</p>';
			}
			if($show_discount_tax || $show_due_date){
				$result .= '</div>';
			}
		}
		elseif( !$error and $redirect_to_billet and stripos($_SERVER['REQUEST_URI'], 'viewinvoice') ){
			header_remove();
			header("Location: $billet_url",true,303);
			exit;
		} 
		elseif( $error ){
			$result .= '<h3 class="error">Erro ao gerar o Boleto</h3>';
			$result .= '<p class="error">'.$error.'</p>';
			if($email_on_error){
				$send_error_email = ggp_send_error_email( $params['invoiceid'], $whmcs_admin_url, $dept_id, $error, $debug_or_log,(int)$params['admin']);
				if($send_error_email['debug'] and $debug_or_log ){
					$debug_result['ggp_send_error_email'] = array('send_error_email'=>$send_error_email);
				}
			} 
		}
		// Debug
		require __DIR__.'/includes/debug.php';
		
		// Register Log
		if($log){
				logModuleCall('gofasboletofacil','generate_billet',array('module_version'=>'1.2.1',$debug_result),'', $billet );
		}
		// Finalize
		if($debug){
			$define_version = '?v='.time();
		}
		elseif(!$debug){
			$define_version = '';
		}
		$result .= '<script type="text/javascript" src="'.$whmcs_url.'modules/gateways/gofasboletofacil/assets/js/copy.js'.$define_version.'" charset="UTF-8">
</script>';
		return $result.$css;
	}
}
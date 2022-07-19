<?php
/**
 * Módulo Juno Cartão para WHMCS
 * @copyright	2020 Gofas Software
 * @see			https://gofas.net/?p=12042
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14644
 * @version		1.3.0
 */
add_hook('ClientAreaPageCreditCardCheckout', 1, function($vars){
	$params = getGatewayVariables('gofasgalaxpay');
	add_hook('ClientAreaFooterOutput', 1, function($vars){
		$params = getGatewayVariables('gofasgalaxpay');
		$vars_ = json_decode(json_encode($vars));
		//echo '<pre style="height: 250px;">',print_r($vars_),'</pre>';
		if($params['minimunamountinstallments']){
			$minimunamountinstallments = (float)$params['minimunamountinstallments'];
		}
		elseif(!$params['minimunamountinstallments']){
			$minimunamountinstallments = (float)'100.00';
		}
		if($params['installments'] and ( (float)$minimunamountinstallments <= (float)$vars_->invoice->model->total) ){
		 $htmlOutput .= '<input type="hidden" name="installment_" id="installment_" value="yes" />';
		 $htmlOutput .= '<script>sessionStorage.setItem("installment_", "yes");</script>';
		 $options_installments .= '<label class="col-sm-4 control-label">Parcelamento</label><div class="col-sm-6" style="margin-bottom: 15px;"><select id="installmentsSelect" name="installmentsSelect" style="max-width: 320px; width: 320px;" required="" class="form-control">';
		 $options_installments .= '<option value="1">1 x de R$ '.number_format( $vars_->invoice->model->total,	2, ',', '.').'</option>';
		 foreach (range(2, (int)$params['maxinstallments']) as $maxinstallments_){
					$maxinstallments__ = $maxinstallments_++;
				$options_installments .= '<option value="'.$maxinstallments__.'">'.$maxinstallments__.' x de R$ '.number_format( $vars_->invoice->model->total / (int)$maxinstallments__ ,	2, ',', '.').'</option>';
		}
		$options_installments .= '</select></div>';
		 $htmlOutput .= "<script>
		 	if(document.getElementById('installment_').value == 'yes'){
				var options_installments = '".$options_installments."';	
				document.getElementById('btnSubmit').insertAdjacentHTML('beforebegin',options_installments);
			}
		 </script>";
		 $htmlOutput .= "<script>
		 	if(document.getElementById('installment_').value == 'yes'){
				var sel = document.getElementById('installmentsSelect');
				sel.addEventListener('change', function (){
							sessionStorage.setItem('installments_', sel.value);
					console.log(sel.value);
	 				 });
			}
		 </script>";
		}
		else {
			 $htmlOutput .= '<input type="hidden" name="installment_" id="installment_" value="no" />';
		}
		$htmlOutput .= '<script type="text/javascript" src="'.$vars['systemurl'].'modules/gateways/gofasgalaxpay/assets/js/ClientAreaPageCreditCardCheckout.js?v='.time().'"></script>';
		return $htmlOutput;
	});
	//echo '<pre style="height: 200px;">',print_r($vars),'</pre>';
	return array(
		'allowClientsToRemoveCards'=>false,
		//'templatefile'=>'../../modules/gateways/gofasgalaxpay/templates/invoice-payment',
	);
	
});
add_hook('ClientAreaPageCart', 1, function($vars){
	$params = getGatewayVariables('gofasgalaxpay');
	if( stripos($_SERVER['REQUEST_URI'], 'cart.php?a=checkout')){
	add_hook('ClientAreaFooterOutput', 1, function($vars){
		$params = getGatewayVariables('gofasgalaxpay');
		$vars_ = json_decode(json_encode($vars));
		if($params['minimunamountinstallments']){
			$minimunamountinstallments = (float)$params['minimunamountinstallments'];
		}
		elseif(!$params['minimunamountinstallments']){
			$minimunamountinstallments = (float)'100.00';
		}
		if($params['installments'] and ( (float)$minimunamountinstallments <= (float)$vars_->rawtotal) ){
		 $htmlOutput .= '<input type="hidden" name="installment_" id="installment_" value="yes" />';
		 $htmlOutput .= '<script>sessionStorage.setItem("installment_", "yes");</script>';
		 $options_installments .= '<div class=""style="margin: 15px 0px 15px 0px;text-align: left;padding-left: 5px;"><label style="margin: 5px 30px 0px 0px;font-size: 100%;">Parcelamento</label><select id="installmentsSelect" name="installmentsSelect"class="field" required="" style="max-width: 680px;">';
		 $options_installments .= '<option value="1">1 x de R$ '.number_format( $vars_->rawtotal,	2, ',', '.').'</option>';
		 foreach (range(2, (int)$params['maxinstallments']) as $maxinstallments_){
					$maxinstallments__ = $maxinstallments_++;
				$options_installments .= '<option value="'.$maxinstallments__.'">'.$maxinstallments__.' x de R$ '.number_format( $vars_->rawtotal / (int)$maxinstallments__ ,	2, ',', '.').'</option>';
		}
		$options_installments .= '</select></div>';
		 $htmlOutput .= "<script>
		 	if(document.getElementById('installment_').value == 'yes'){
				var options_installments = '".$options_installments."';	
				document.getElementById('newCardInfo').insertAdjacentHTML('beforebegin',options_installments);
			}
		 </script>";
		 $htmlOutput .= "<script>
		 	if(document.getElementById('installment_').value == 'yes'){
				var sel = document.getElementById('installmentsSelect');
				sel.addEventListener('change', function (){
							sessionStorage.setItem('installments_', sel.value);
					console.log(sel.value);
	 				 });
			}
		 </script>";
		}
		else {
			 $htmlOutput .= '<input type="hidden" name="installment_" id="installment_" value="no" />';
		}
		$htmlOutput .= '<script type="text/javascript" src="'.$vars['systemurl'].'modules/gateways/gofasgalaxpay/assets/js/ClientAreaPageCart.js?v='.time().'"></script>';
		return $htmlOutput;
	});
	 }
	return array(
		'allowClientsToRemoveCards'=>true,
	);
 
});
add_hook('ClientAreaPaymentMethods', 1, function($vars){
	return array(
		'allowCreditCard'=>false,
	);
});
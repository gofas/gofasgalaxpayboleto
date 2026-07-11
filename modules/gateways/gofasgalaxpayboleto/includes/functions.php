<?php
/**
 * Módulo GalaxPay Boleto para WHMCS
 * @copyright	2022 Gofas Software
 * @see			https://gofas.net/?p=14695
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14687
 * @version		1.0.1
 */
require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../../includes/invoicefunctions.php';
if(!defined("WHMCS")){die();}
use WHMCS\Database\Capsule;
if(!function_exists('ggpb_api_connect')){
	function ggpb_api_connect(){
		$params = getGatewayVariables('gofasgalaxpayboleto');
		if($params['sandbox']){
			$params_api = [
				'api_mode' => 'sandbox',
				'galax_id' => $params['sandbox_galax_id'],
				'galax_hash' => $params['sandbox_galax_hash'],
				'public_token' => $params['sandbox_public_token'],
				'charge_url' => 'https://api.sandbox.cloud.galaxpay.com.br/v2',
				'galaxIdPartner' => '5473',
				'galaxHashPartner' => '83Mw5u8988Qj6fZqS4Z8K7LzOo1j28S706R0BeFe',
			];
		}
		if(!$params['sandbox']){
			$params_api = [
				'api_mode' => 'live',												// $params_api['api_mode']
				'galax_id' => $params['galax_id'],									// $params_api['galax_id']
				'galax_hash' => $params['galax_hash'],								// $params_api['galax_hash']
				'public_token' => $params['public_token'],							// $params_api['public_token']
				'charge_url' => 'https://api.galaxpay.com.br/v2',					// $params_api['charge_url']												// $params_api['sandbox']
				'galaxIdPartner' => '29009',										// $params_api['galaxIdPartner']
				'galaxHashPartner' => 'U9F6YvKgI77gVqJ60kHk6qOd04RhLfN0YyJ8AfA6',	// $params_api['galaxHashPartner']
			];
		}
		return $params_api;
	}
}
if( !function_exists('ggpb_get_token') ){
	function ggpb_get_token(){
		$params_api = ggpb_api_connect();
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $params_api['charge_url'].'/token',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS =>'{
			  "grant_type": "authorization_code",
			  "scope": "customers.read customers.write plans.read plans.write transactions.read transactions.write webhooks.write cards.read cards.write card-brands.read subscriptions.read subscriptions.write charges.read charges.write boletos.read carnes.read payment-methods.read"
			}',
			  CURLOPT_HTTPHEADER => array(
		    	'Authorization: Basic '.base64_encode((string)$params_api['galax_id'].':'.(string)$params_api['galax_hash']),
				'AuthorizationPartner: '.base64_encode($params_api['galaxIdPartner'].':'. $params_api['galaxHashPartner']),
		    	'Content-Type: application/json'
		  	)
		));
		$result_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$result = json_decode(curl_exec($curl), true);
		curl_close($curl);
		return ['result_code'=>$result_code,'result'=>$result];
	}
}
if( !function_exists('ggpb_charge') ){
	function ggpb_charge($postfields){
		$params_api = ggpb_api_connect();
    	$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $params_api['charge_url'].'/charges',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER => array(
			  'Authorization: Bearer '.$postfields['access_token'],
			  'AuthorizationPartner: '.base64_encode($params_api['galaxIdPartner'].':'. $params_api['galaxHashPartner']),
			  'Content-Type: application/json'
			),
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode($postfields['charge']),
		));
		$result = json_decode(curl_exec($curl),true);
		$result_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return ['result_code'=>$result_code,'result'=>$result];
	}
}
if( !function_exists('ggpb_refund') ){
	function ggpb_refund($charge_id,$access_token){
		$params_api = ggpb_api_connect();
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $params_api['charge_url'].'/charges/'.$charge_id.'/galaxPayId/reverse',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'PUT',
			//CURLOPT_POSTFIELDS =>'[]',
			CURLOPT_HTTPHEADER => array(
			  'Authorization: Bearer '.$access_token,
			  'AuthorizationPartner: '.base64_encode($params_api['galaxIdPartner'].':'. $params_api['galaxHashPartner']),
			  'Content-Type: application/json'
			),
		  ));
		$result = json_decode(curl_exec($curl),true);
		$result_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return ['result_code'=>$result_code,'result'=>$result];
	}
}
if( !function_exists('ggpb_charge_verify') ){
	function ggpb_charge_verify($charge_id){
		$params_api = ggpb_api_connect();
		$curl = curl_init();
		$access_token_ = ggpb_get_token();
		$access_token = $access_token_['result']['access_token'];

		curl_setopt_array($curl, array(
			CURLOPT_URL => $params_api['charge_url'].'/transactions?galaxPayIds='.$charge_id.'&limit=1&order=createdAt.desc&startAt=0',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
			  'Authorization: Bearer '.$access_token
			),
		));
		$result = json_decode(curl_exec($curl),true);
		$result_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return ['result_code'=>$result_code,'result'=>$result];
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
if( !function_exists('ggpb_card_add') ){
	function ggpb_card_add($card,$pay_method_id){
		try {
			Capsule::table('tblcreditcards')->where( 'pay_method_id', $pay_method_id)->delete();
		}
		catch (\Exception $e){
			$error .= $e->getMessage();
		}
		try {
			Capsule::table('tblpaymethods')->where( 'id', $pay_method_id)->delete();
		}
		catch (\Exception $e){
			$error .= $e->getMessage();
		}	
		try {
			$createCardPayMethod = createCardPayMethod( // Function available in WHMCS 7.9 and later
				$card['userid'],
				'gofasgalaxpayboleto',
				'111111111111'.$card['cclastfour'],
				$card['cardexp'],
				$card['cardtype'],
				NULL, //start date
				$card['cardissuenum'],//NULL, //issue number
				$card['myId']
			);
		}
		catch (Exception $e){
			$error .= $e->getMessage();
		}
		if($error){
			ggpb_card_del($card['myId']);
			return $error;
		}
		return 'success';
	}
}
if( !function_exists('ggpb_card_del') ){
	function ggpb_card_del($pay_method_id){
		try {
			Capsule::table('tblcreditcards')->where( 'pay_method_id', $pay_method_id)->delete();
		}
		catch (\Exception $e){
			$error .= $e->getMessage();
		}
		try {
			Capsule::table('tblpaymethods')->where( 'id', $pay_method_id)->delete();
		}
		catch (\Exception $e){
			$error .= $e->getMessage();
		}
		if($error){
			return $error;
		}
		return 'success';
	}
}
if(!function_exists('ggpb_setup_admin')){
	function ggpb_setup_admin(){
	foreach( Capsule::table('tblconfiguration')->where('setting','=','ggpb_version')->get(['value']) as $version_ ){
		$version		= json_decode($version_->value, true);
		$admin			= $version['admin'];
	}
	return $admin;
}}
if(!function_exists('ggpb_get_local_version')){
	function ggpb_get_local_version(){
	foreach( Capsule::table('tblconfiguration')->where('setting','=','ggpb_version')->get(['value']) as $version_ ){
		$version		= json_decode($version_->value, true);
		$local_version			= $version['local_version'];
	}
	return $local_version;
}}
if(!function_exists('ggpb_update_stats') ){
	function ggpb_module_version(){
		return '1.2.1';
	}
	function ggpb_update_stats(){
		$params = getGatewayVariables('gofasgalaxpayboleto');
		if($params['sandbox']){
			return;
		}
		// Sem consentimento: contabiliza a confirmacao de pagamento de forma anonima (sem URL nem identificacao do admin)
		if(empty($params['consent_stats'])){
			$anon_version = ggpb_module_version();
			$anon_id = 'ggpb-v'.$anon_version;
			$install_url = $anon_id;
			$installer_email = $anon_id.'@gofas.net';
			$installer_firstname = 'ggpb';
			$installer_lastname = 'v'.$anon_version;
		}
		else{
			$whmcs_url = ggpb_whmcs_url();
			$setup_admin = ggpb_setup_admin();
			$install_url = $whmcs_url['admin_url'];
			$installer_email = $setup_admin['email'];
			$installer_firstname = $setup_admin['firstname'];
			$installer_lastname = $setup_admin['lastname'];
		}
		$query = '?software_id=14695&install_url='.$install_url.'&current_version='.ggpb_get_local_version().'&installer_email='.$installer_email.'&installer_firstname='.$installer_firstname.'&installer_lastname='.$installer_lastname.'&action=charge'.ggpb_sysinfo();
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl, CURLOPT_URL, 'https://gofas.net/br/updates/stats.php'.$query);
		$response = curl_exec($curl);
		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		$return = ['query'=>$query,'response'=>$response,'http_code'=>$http_status];
		return $return;
	}
}
if( !function_exists('ggpb_add_trans') ){
	function ggpb_add_trans( $user_id, $invoice_id, $amount, $fee, $charge_id, $description ){	
 		$addtransvalues['userid'] = $user_id;
 		$addtransvalues['invoiceid'] = $invoice_id;
 		$addtransvalues['description'] = $description;
 		$addtransvalues['amountin'] = $amount;
 		$addtransvalues['fees'] = $fee;
 		$addtransvalues['paymentmethod'] = 'gofasgalaxpayboleto';
 		$addtransvalues['transid'] = $charge_id;
 		$addtransvalues['date'] = date('d/m/Y');
		$addtransresults = localAPI( "addtransaction", $addtransvalues, (int)$params['admin']);
		$ggpb_update_stats = ggpb_update_stats();
		if( $addtransresults['result'] === 'success'){
			return array('values'=>$addtransvalues, 'result'=>$addtransresults);
		}
		elseif($addtransresults['result'] !== 'success'){
			$error = '<b>Não foi possível gravar a transação.</b>';
			return array('error'=>$error, 'values'=>$addtransvalues, 'result'=>$addtransresults);
		}
	}
}

if(!function_exists('ggpb_customer') ){
	function ggpb_customer($client_id){
		//Determine custom fields id
		$params = getGatewayVariables('gofasgalaxpayboleto');
		$client = localAPI('GetClientsDetails',array( 'clientid' => $client_id, 'stats' => false, ), $params['admin']);
		foreach( Capsule::table('tblcustomfields')->where('type','=','client')->get() as $customfield ){
			$customfield_id = $customfield->id;
			$customfield_name = strtolower($customfield->fieldname);
			// cpf
			if(strpos($customfield_name, 'cpf') !== false and strpos($customfield_name,'cnpj') === false){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$cpf_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
				}
			}	
			// cnpj
			if(strpos($customfield_name, 'cnpj') !== false and strpos($customfield_name,'cpf') === false){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$cnpj_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
				}
			}
			// cpf + cnpj
			if( strpos( $customfield_name, 'cpf') !== false and strpos( $customfield_name, 'cnpj') !== false ){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$cpf_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
					$cnpj_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
				}
			}
			// Inscrição Estadual
			if( strpos( $customfield_name, 'inscrição estadual') !== false){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$ie = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
				}
			}
			// Complemento Custom Field
			if( strpos( $customfield_name, 'complemento') !== false){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$complement = $customfieldvalue->value;
				}
			}
			// Número Custom Field
			if( strpos( $customfield_name, 'numero')!== false ||  strpos( $customfield_name, 'número')!== false ){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$number = $customfieldvalue->value;
				}
				if(!$number){
					$number = preg_replace('/[^0-9]/', '', $client['address1']);
				}
			}
			else {
				$number = preg_replace('/[^0-9]/', '', $client['address1']);
			}
			// Emitir Custom Field
			if( strpos( $customfield_name, 'emitir nfe')!== false || strpos( $customfield_name, 'emitir nfse')!== false || strpos( $customfield_name, 'emitir nfs-e')!== false || strpos( $customfield_name, 'emitir nf-e')!== false){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$issue_nfe = $customfieldvalue->value;
				}
				if(!$issue_nfe){
					$issue_nfe = false;
				}
			}
			// nascimento
			if( strpos( $customfield_name, 'nascimento') ){
				foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $client_id) -> get( array( 'value') ) as $customfieldvalue ){
					$birt_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
					$birthday_pre			= preg_replace('/[^\da-z]/i', '', $birt_customfield_value);
					if(strlen($birthday_pre) === 8){
						$birth_ = $birthday_pre;
					}
					elseif( strlen($birthday_pre) === 7 ){
						$birth_ = '0'.$birthday_pre;
					}
					$birth_Y					= substr($birth_, -4);
					$birth_m					= substr($birth_, 2, -4);
					$birth_d					= substr($birth_, 0, -6);
					$birthday_us = $birth_Y.'-'.$birth_m.'-'.$birth_d; // 2021-02-20
					$birthday_br = $birth_d.'/'.$birth_m.'/'.$birth_Y; // 20/02/2021
					$birthday_raw = $customfieldvalue->value;
				}
			}
			foreach(Capsule::table('tblcustomfieldsvalues')->where('fieldid','=',$customfield_id)->where('relid','=',$client_id)->get(array('value')) as $customfieldvalue ){
				$custom_fields[$customfield_name] = $customfieldvalue->value;
			}
		}
		//
		// Cliente possui CPF e CNPJ
		// CPF com 1 nº a menos, adiciona 0 antes do documento
		if( strlen( $cpf_customfield_value ) === 10 ){
			$cpf = '0'.$cpf_customfield_value;
		}
		// CPF com 11 dígitos
		elseif( strlen( $cpf_customfield_value ) === 11){
			$cpf = $cpf_customfield_value;
		}
		// CNPJ no campo de CPF com um dígito a menos
		elseif( strlen( $cpf_customfield_value ) === 13 ){
			$cpf = false; 
			$cnpj = '0'.$cpf_customfield_value;
		}
		// CNPJ no campo de CPF
		elseif( strlen( $cpf_customfield_value ) === 14 ){
			$cpf 				= false;
			$cnpj				= $cpf_customfield_value;
		}
		// cadastro não possui CPF
		elseif( !$cpf_customfield_value || strlen( $cpf_customfield_value ) !== 10 || strlen($cpf_customfield_value) !== 11 || strlen( $cpf_customfield_value ) !== 13 || strlen($cpf_customfield_value) !== 14 ){	
			$cpf = false;
		}
		// CNPJ com 1 nº a menos, adiciona 0 antes do documento
		if( strlen($cnpj_customfield_value) === 13 ){
			$cnpj = '0'.$cnpj_customfield_value;
		}
		// CNPJ com nº de dígitos correto
		elseif( strlen($cnpj_customfield_value) === 14 ){
			$cnpj = $cnpj_customfield_value;
		}
		// Cliente não possui CNPJ
		elseif( !$cnpj_customfield_value and strlen( $cnpj_customfield_value ) !== 14 and strlen($cnpj_customfield_value) !== 13 and strlen( $cpf_customfield_value ) !== 13 and strlen( $cpf_customfield_value ) !== 14  ){
			$cnpj = false;
		}

		if( ( $cpf and $cnpj ) or ( !$cpf and $cnpj ) ){
			if( $client['companyname'] ){
				$name	= $client['companyname'];
			}
			elseif( !$client['companyname'] ){
				$name	= $client['firstname'].' '.$client['lastname'];
			}
			$doc_type	= 'J';
			$document	= $cnpj;
		}
		elseif( $cpf and !$cnpj ){
			$name	= $client['firstname'].' '.$client['lastname'];
			$doc_type	= 'F';
			$document	= $cpf;
		}
		/// Formated Array
		$customer=[
			'id'=>$client_id,
			'email'=>$client['email'],
			'name'=>$name,
			'names'=>['firstname'=>$client['firstname'],'lastname'=>$client['lastname'],'companyname'=>$client['companyname']],
			'address'=>str_replace(',','',preg_replace('/[0-9]+/i','',$client['address1'],1)),
			'number'=>$number,
			'neighborhood'=>$client['address2'],
			'complement'=>$complement,
			'city'=>$client['city'],
			'state'=>$client['state'],
			'postcode'=>preg_replace("/[^\da-z]/i", "",$client['postcode']),
			'phone'=>preg_replace('/[^\da-z]/i', '', $client['phonenumber']),
			'doc_type'=>$doc_type,
			'document'=>$document,
			'ie'=>$ie,
			'issue_nfe'=>$issue_nfe,
			'birthday'=>['raw'=>$birthday_raw,'br'=>$birthday_br,'us'=>$birthday_us],
			'custom_fields'=>$custom_fields,
		];
		return $customer;
	}
}
if( !function_exists('ggpb_save_qrc') ){
	function ggpb_save_qrc($qr_code){
		$data = array(
			'invoice_id'=>$qr_code['invoice_id'],
			'charge_id'=>$qr_code['charge_id'],
			'amount'=>$qr_code['amount'],
			'pdf'=>$qr_code['pdf'],
			'bankLine'=>$qr_code['bankLine'],
			'api_mode'=>$qr_code['api_mode'],
			'created_at'=>date("Y-m-d H:i:s"),
			'updated_at'=>date("Y-m-d H:i:s"),
		);
	try {
		$save_qrc = Capsule::table('gofasgalaxpayboleto')->insert($data);
		return 'success';
	}
	catch (\Exception $e){
		return $e->getMessage();
	}
}}
if(!function_exists('ggpb_update_qrc') ){
	function ggpb_update_qrc($data){
		$params = getGatewayVariables('gofasgalaxpayboleto');
		$local_qrc = ggpb_get_local_qrc($data['invoice_id']);
		$data['created_at'] = $local_qrc['created_at'];
		$data['updated_at']= date("Y-m-d H:i:s");
		
	try {
		$update_qrc = Capsule::table('gofasgalaxpayboleto')->where('invoice_id', '=',$data['invoice_id'])->update($data);
		if($params['log']){
			logModuleCall('gofasgalaxpayboleto','ggpb_update_qrc',array('data'=>$data),'post',array('update_qrc' => $update_qrc),'replaceVars');
		}
		return 'success';
	}
	catch (\Exception $e){
		if($params['log']){
			logModuleCall('gofasgalaxpayboleto','ggpb_update_qrc',array('data'=>$data),'post',array('update_qrc' => $update_qrc),'replaceVars');
		}
		return $e->getMessage();
	}
}}
if( !function_exists('ggpb_get_local_qrc') ){
	function ggpb_get_local_qrc($invoice_id){
		$params_api = ggpb_api_connect();
		foreach( Capsule::table('gofasgalaxpayboleto')->where('invoice_id','=', $invoice_id)->where('api_mode','=',$params_api['api_mode'])->get() as $key => $value ){
			$qrc_for_invoice[$key] = json_decode(json_encode($value), true);
		}
		return $qrc_for_invoice['0'];
	}
}
if( !function_exists('ggpb_verify_install') ){
	function ggpb_verify_install(){
		if( !Capsule::schema()->hasTable('gofasgalaxpayboleto') ){
			try {
				Capsule::schema()->create('gofasgalaxpayboleto', function($table){
					$table->string('invoice_id');
					$table->string('charge_id');
					$table->string('amount');
					$table->text('pdf');
					$table->string('bankLine');
					$table->string('api_mode');
					$table->string('created_at');
					$table->string('updated_at');
				});
			}
			catch (\Exception $e){
				$error .= "Não foi possível criar a tabela do módulo no banco de dados: {$e->getMessage()}";
			}
		}
		if(!$error){
			return array('sucess'=>1);
		}
		elseif($error){
			return array('error'=>$error);
		}
	}
}
// Admin functions
if( !function_exists('ggpb_whmcs_url') ){
	function ggpb_whmcs_url(){
		$url		= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		if( stripos( $url, '/configgateways.php') !== false){
			$whmcs_url__ = str_replace("\\",'/',(isset($_SERVER['HTTPS']) ? "https://" : "http://").$_SERVER['HTTP_HOST'].substr(getcwd(),strlen($_SERVER['DOCUMENT_ROOT'])));
			$admin_url = $whmcs_url__.'/';
			$vtokens = explode('/', $url);
			$whmcs_admin_path = '/'.$vtokens[sizeof($vtokens)-2].'/';
			$whmcs_url = str_replace( $whmcs_admin_path, '', $admin_url).'/';
			foreach( Capsule::table('tblconfiguration') -> where('setting', '=', 'ggpbwhmcsurl') -> get( array( 'value','created_at') ) as $ggpbwhmcsurl_ ){
				$ggpbwhmcsurl					= $ggpbwhmcsurl_->value;
				$ggpbwhmcsurl_created_at			= $ggpbwhmcsurl_->created_at;
			}
			foreach( Capsule::table('tblconfiguration') -> where('setting', '=', 'ggpbwhmcsadminurl') -> get( array( 'value','created_at') ) as $ggpbwhmcsadminurl_ ){
				$ggpbwhmcsadminurl				= $ggpbwhmcsadminurl_->value;
				$ggpbwhmcsadminurl_created_at	= $ggpbwhmcsurl_->created_at;
			}
			foreach( Capsule::table('tblconfiguration') -> where('setting', '=', 'ggpbwhmcsadminpath') -> get( array( 'value','created_at') ) as $ggpbwhmcsadminpath_ ){
				$ggpbwhmcsadminpath				= $ggpbwhmcsadminpath_->value;
				$ggpbwhmcsadminpath_created_at	= $ggpbwhmcsurl_->created_at;
			}
			if( !$ggpbwhmcsurl ){
				try { Capsule::table('tblconfiguration')->insert(array('setting' => 'ggpbwhmcsurl', 'value' => $whmcs_url, 'created_at' => date("Y-m-d H:i:s") , 'updated_at' => date("Y-m-d H:i:s")));}
				catch (\Exception $e){ $e->getMessage(); }
				try { Capsule::table('tblconfiguration')->insert(array('setting' => 'ggpbwhmcsadminurl', 'value' => $admin_url, 'created_at' => date("Y-m-d H:i:s") , 'updated_at' => date("Y-m-d H:i:s")));}
				catch (\Exception $e){ $e->getMessage(); }
				try { Capsule::table('tblconfiguration')->insert(array('setting' => 'ggpbwhmcsadminpath', 'value' => $whmcs_admin_path, 'created_at' => date("Y-m-d H:i:s") , 'updated_at' => date("Y-m-d H:i:s")));}
				catch (\Exception $e){ $e->getMessage(); }
			}
			if( $ggpbwhmcsurl and ($whmcs_url !== $ggpbwhmcsurl) ){
				try { Capsule::table('tblconfiguration')->where( 'setting', 'ggpbwhmcsurl')->update(array('value' => $whmcs_url, 'created_at' =>  $ggpbwhmcsurl_created_at , 'updated_at' => date("Y-m-d H:i:s")));}
				catch (\Exception $e){$e->getMessage();}
			}
			if( $ggpbwhmcsadminurl and ($admin_url !== $ggpbwhmcsadminurl) ){
				try { Capsule::table('tblconfiguration')->where( 'setting', 'ggpbwhmcsadminurl')->update(array('value' => $admin_url, 'created_at' =>  $ggpbwhmcsadminurl_created_at , 'updated_at' => date("Y-m-d H:i:s")));}
				catch (\Exception $e){$e->getMessage();}
			}
			if( $ggpbwhmcsadminpath and ($whmcs_admin_path !== $ggpbwhmcsadminpath) ){
				try { Capsule::table('tblconfiguration')->where( 'setting', 'ggpbwhmcsadminpath')->update(array('value' => $whmcs_admin_path, 'created_at' =>  $ggpbwhmcsadminpath_created_at , 'updated_at' => date("Y-m-d H:i:s")));}
				catch (\Exception $e){$e->getMessage();}
			}

		}
		return ['url'=>$whmcs_url,'admin_url'=>$admin_url,'admin_path'=>$whmcs_admin_path];
	}
}
if( !function_exists('ggpb_get_embed') ){
	function ggpb_get_embed($page_id,$referer,$module_version){
		$query = 'https://gofas.net/cliente/gofas/updates/?embed='.$page_id.'&referer='.$referer.'&version='.$module_version;
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl, CURLOPT_URL, $query);
		$embed = curl_exec($curl);
		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return ['embed'=>$embed,'http_code'=>$http_status];
	}
}
if(!function_exists('ggpb_encrypt')){
	function ggpb_encrypt($q) {
	    $encryptionMethod = "AES-256-CBC";
		$secretHash = "535ba9979bc6c7ff151f2136cd13b0f9";
	    return openssl_encrypt($q, $encryptionMethod, $secretHash);
	}
}
if(!function_exists('ggpb_decrypt')){
	function ggpb_decrypt($q){
		$encryptionMethod = "AES-256-CBC";
		$secretHash = "535ba9979bc6c7ff151f2136cd13b0f9";
	    return openssl_decrypt($q, $encryptionMethod, $secretHash);
	}
}
if( !function_exists('ggpb_get_version') ){
	function ggpb_current_admin(){
		$currentUser = new \WHMCS\Authentication\CurrentUser;
		$admin = json_decode(json_encode($currentUser->admin()),true);
		return $admin;
	}
	function ggpb_get_version($page_id,$referer,$module_version){
		$current_admin = ggpb_current_admin();
		$query = '?software_id='.$page_id.'&install_url='.$referer.'&current_version='.$module_version.'&installer_email='.$current_admin['email'].'&installer_firstname='.$current_admin['firstname'].'&installer_lastname='.$current_admin['lastname'].'&action=verify'.ggpb_sysinfo();
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl, CURLOPT_URL, 'https://gofas.net/br/updates/stats.php'.$query);
		$available_version_ = curl_exec($curl);
		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return ['version'=>$available_version_,'http_code'=>$http_status];
	}
}
if(!function_exists('ggpb_sysinfo')){
	function ggpb_sysinfo(){
		foreach( Capsule::table('tblconfiguration')
		->where('setting','=','Version')
		->get(['value']) as $data1 ){
			$Version = $data1->value;
		}
		foreach( Capsule::table('tblconfiguration')
		->where('setting','=','CronPHPVersion')
		->get(['value']) as $data1 ){
			$PHPVersion = $data1->value;
		}
		return '&whmcs_version='.$Version.'&php_version='.$PHPVersion;
	}
}
if(!function_exists('ggpb_verify_module_updates')){
	function ggpb_verify_module_updates($page_id,$referer,$module_version){
		foreach( Capsule::table('tblconfiguration')->where('setting','=','ggpb_version')->get(['value','created_at','updated_at']) as $version_ ){
			$version		= json_decode($version_->value, true);
			$local_version	= $version['local_version'];
			$last_version	= $version['last_version'];
			$embed			= $version['check'];
			$created_at		= $version_->created_at;
			$updated_at		= $version_->updated_at;
			//$available_version	= (int)preg_replace("/[^0-9]/","",$version['last_version']);
		}
		///// Get
		if(!$version){
			$get_version = ggpb_get_version($page_id,$referer,$module_version,$admin);
			$get_embed	 = ggpb_get_embed($page_id,$referer,$module_version);
			
			if((int)$get_version['http_code'] !== 200){
				$error .= $get_version['http_code'].' '.$get_version['version'];
			}
			else{
				$available_version = $get_version['version'];
			}
		}
		if($version and strtotime($updated_at) < strtotime("-1 day")){
			$get_version = ggpb_get_version($page_id,$referer,$module_version);
			$get_embed	 = ggpb_get_embed($page_id,$referer,$module_version);
			if((int)$get_version['http_code'] !== 200){
				$error .= $get_version['http_code'].' '.$get_version['version'];
			}
			else{
				$available_version = $get_version['version'];
			}
		}
		if($version and (string)$module_version !== (string)$local_version){
			$get_version = ggpb_get_version($page_id,$referer,$module_version,$admin);
			$get_embed	 = ggpb_get_embed($page_id,$referer,$module_version);
			if((int)$get_version['http_code'] !== 200){
				$error .= $get_version['http_code'].' '.$get_version['version'];
			}
			else{
				$available_version = $get_version['version'];
			}
		}
		if($version and strtotime($updated_at) > strtotime("-1 day")){
			$available_version = $last_version;
		}
		// insert
		if(!$version and $get_version['version'] and $get_embed['embed']){
			$local_version = $module_version;
			$last_version = $get_version['version'];
			$embed		  = ggpb_encrypt($get_embed['embed']);
			$created_at		= date("Y-m-d H:i:s");
			$updated_at		= date("Y-m-d H:i:s");

			try { Capsule::table('tblconfiguration')->insert(array(
				'setting' => 'ggpb_version',
				'value' => json_encode([
					'local_version'=>$module_version,
					'last_version'=>$get_version['version'],
					'check'=>ggpb_encrypt($get_embed['embed']),
					'admin'=>ggpb_current_admin(),
				]),
				'created_at' => $created_at,
				'updated_at' => $updated_at
			));
			}
			catch (\Exception $e){
				$error .= $e->getMessage();
			}
		}
		// update
		if($version and $get_version['version'] and $get_embed['embed'] and strtotime($updated_at) < strtotime("-1 day") and (
			$available_version !== $module_version ||
			$local_version !== $module_version ||
			$last_version !== $available_version
		)){
			try {
				Capsule::table('tblconfiguration')->where('setting','ggpb_version')->update([
					'value' => json_encode([
						'local_version'=>$module_version,
						'last_version'=>$available_version,
						'check'=>ggpb_encrypt($get_embed['embed']),
						'admin'=>ggpb_current_admin(),
					]),
					'created_at' =>  $created_at,
					'updated_at' => date("Y-m-d H:i:s")]
				);
			}
			catch (\Exception $e){
				$error .= $e->getMessage();
			}
		}
		// update
		if($version and $get_version['version'] and $get_embed['embed'] and (string)$local_version !== (string)$module_version){
			try {
				Capsule::table('tblconfiguration')->where('setting','ggpb_version')->update([
					'value' => json_encode([
						'local_version'=>$module_version,
						'last_version'=>$available_version,
						'check'=>ggpb_encrypt($get_embed['embed']),
						'admin'=>ggpb_current_admin(),
					]),
					'created_at' =>  $created_at,
					'updated_at' => date("Y-m-d H:i:s")]
				);
			}
			catch (\Exception $e){
				$error .= $e->getMessage();
			}
		}
		$module_version_int = (int)preg_replace("/[^0-9]/", "", $module_version);
		$available_version_int = (int)preg_replace("/[^0-9]/", "", $available_version);
		if( $available_version_int === $module_version_int ){
			$message = '<p style="color: green"><i class="fas fa-check-square"></i> Você está executando a versão mais recente do módulo.</p>';
		}
		if( $available_version_int > $module_version_int ){
			$message = '<p style="font-size: 14px; color: red;"><i class="fas fa-exclamation-triangle"></i> Atualização disponível, verifique a <a style="color:#CC0000;text-decoration:underline;" href="https://gofas.net/?p='.$page_id.'" target="_blank">versão '.$available_version.'</a>';
		}
		if( $available_version_int < $module_version_int ){
			$message = '<p style="font-size: 14px; color: orange;"><i class="fas fa-exclamation-triangle"></i> Você está executando uma versão Beta desse módulo.<br>Baixar versão estável: <a style="color:#CC0000;text-decoration:underline;" href="https://gofas.net/?p='.$page_id.'" target="_blank">v'.$available_version.'</a>';
		}
		return [
			'version'=>$version,
			'get_version'=>$get_version,
			'message' => $message,
			'check'=> $embed,
			'error' => $error,
		];
	}
}
if(!function_exists('ggpb_version')){
	function ggpb_version($opt=1){
		foreach( Capsule::table('tblconfiguration') -> where('setting', '=', 'ggpb_version') -> get( array( 'value','created_at') ) as $ggpb_version_ ){
			$ggpb_version				= $ggpb_version_->value;
			$ggpb_version_created_at	= $ggpb_version_->created_at;
		}
		if($opt=1){ // local_version string
			$version = json_decode($ggpb_version, true);
			return $version['local_version'];
		}
		if($opt=2){ // local_version integer
			$version = json_decode($ggpb_version, true);
			return (int)preg_replace("/[^0-9]/", "", $version['local_version']);
		}
		if($opt=3){ // full
			return$ggpb_version;
		}
	}
}
if(!function_exists('ggpb_tbladmins')){
	function ggpb_tbladmins(){
		foreach( Capsule::table('tbladmins') -> get() as $tbladmins_ ){
			$tbladmins[$tbladmins_->id] = $tbladmins_->id.' - '.$tbladmins_->firstname.' '.$tbladmins_->lastname.' ('.$tbladmins_->username.')';
		}
		return $tbladmins;
	}
}
if(!function_exists('ggpb_tblticketdepartments')){
	function ggpb_tblticketdepartments(){
		$tblticketdepartments[] = '';
		foreach( Capsule::table('tblticketdepartments') -> get() as $tblticketdepartments_ ){
			$tblticketdepartments_id			= $tblticketdepartments_->id;
			$tblticketdepartments_name			= $tblticketdepartments_->name;
			$tblticketdepartments[]				= $tblticketdepartments_id.' - '.$tblticketdepartments_name;
		}
		return $tblticketdepartments;
	}
}
if(!function_exists('ggpb_line_items')){
	function ggpb_line_items($invoice_id){
		$invoice			= localAPI('getinvoice',array('invoiceid'=>$invoice_id),(int)$params['admin']);
		// Itens de Linha - Serviços/produtos relacionados à fatura
		$invoice_items_item	= $invoice['items']['item'];
		$line_items = array();
		foreach( $invoice_items_item as $Value){
			$line_items[]	= substr( $Value['description'],  0, 80).' | R$ '.number_format( $Value['amount'],  2, ',', '.');	
		}
		return substr( implode("\n",$line_items),  0, 400);
	}
}
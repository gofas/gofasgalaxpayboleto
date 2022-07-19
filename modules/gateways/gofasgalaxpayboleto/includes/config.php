<?php
/**
 * Módulo Galax Pay para WHMCS
 * @copyright	2022 Gofas Software
 * @see			https://gofas.net/?p=14641
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14644
 * @version		0.1.0
 */

if( !defined('WHMCS')){ die(''); }
use WHMCS\Database\Capsule;
function gofasgalaxpay_MetaData(){
    return array(
        'DisplayName' => 'Gofas Galax Pay',
        'APIVersion' => '1.1',
    );
}
function gofasgalaxpaycard_config(){
	$renderize = ['FriendlyName' => [
			'Type' => 'System',
			'Value' => 'Gofas Galax Pay - Cartão',
		],
	];
	return $renderize;
}
function gofasgalaxpaybillet_config(){
	$renderize = ['FriendlyName' => [
		'Type' => 'System',
		'Value' => 'Gofas Galax Pay - Boleto',
	],
];
return $renderize;
}
function gofasgalaxpaypix_config(){
	$renderize = ['FriendlyName' => [
		'Type' => 'System',
		'Value' => 'Gofas Galax Pay - Pix',
	],
];
return $renderize;
}
function gofasgalaxpay_config(){
	$module_version = '0.1.0';
	$module_version_int = (int)preg_replace("/[^0-9]/", "", $module_version);
	if( !function_exists('ggp_verifyInstall') ){
	function ggp_verifyInstall(){
		if( !Capsule::schema()->hasTable('gofasgalaxpay') ){
    		try {
				Capsule::schema()->create('gofasgalaxpay', function($table){
        			// card
					$table->increments('id');
					$table->string('api_mode');
					$table->string('user_id');
					$table->string('credit_card_id');
					$table->string('pay_method_id');
					$table->string('card_type');
					$table->string('last_four');
					// billet
					$table->string('code');
					$table->string('link');
					$table->string('due_date');
					$table->string('barcode_number');
					$table->string('pay_number');
					// pix
					$table->string('invoice_id');
					$table->string('charge_id');
					$table->string('amount');
					$table->string('qrc_id');
					$table->text('qrc_in_base64');
					$table->text('image_in_base64');
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
	}}
	$verifyInstall = ggp_verifyInstall();
	if($verifyInstall['error']){
		$error = $verifyInstall['error'];
	}
	
	$actual_link		= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	if( stripos( $actual_link, '/configgateways.php') ){
		$whmcs_url__ = str_replace("\\",'/',(isset($_SERVER['HTTPS']) ? "https://" : "http://").$_SERVER['HTTP_HOST'].substr(getcwd(),strlen($_SERVER['DOCUMENT_ROOT'])));
		$admin_url = $whmcs_url__.'/';
		$vtokens = explode('/', $actual_link);
		$whmcs_admin_path = '/'.$vtokens[sizeof($vtokens)-2].'/';
		$whmcs_url = str_replace( $whmcs_admin_path, '', $admin_url).'/';
		foreach( Capsule::table('tblconfiguration') -> where('setting', '=', 'ggpwhmcsurl') -> get( array( 'value','created_at') ) as $ggpwhmcsurl_ ){
			$ggpwhmcsurl					= $ggpwhmcsurl_->value;
			$ggpwhmcsurl_created_at			= $ggpwhmcsurl_->created_at;
		}
		foreach( Capsule::table('tblconfiguration') -> where('setting', '=', 'ggpwhmcsadminurl') -> get( array( 'value','created_at') ) as $ggpwhmcsadminurl_ ){
			$ggpwhmcsadminurl				= $ggpwhmcsadminurl_->value;
			$ggpwhmcsadminurl_created_at	= $ggpwhmcsurl_->created_at;
		}
		foreach( Capsule::table('tblconfiguration') -> where('setting', '=', 'ggpwhmcsadminpath') -> get( array( 'value','created_at') ) as $ggpwhmcsadminpath_ ){
			$ggpwhmcsadminpath				= $ggpwhmcsadminpath_->value;
			$ggpwhmcsadminpath_created_at	= $ggpwhmcsurl_->created_at;
		}
		if( !$ggpwhmcsurl ){
			try { Capsule::table('tblconfiguration')->insert(array('setting' => 'ggpwhmcsurl', 'value' => $whmcs_url, 'created_at' => date("Y-m-d H:i:s") , 'updated_at' => date("Y-m-d H:i:s")));}
			catch (\Exception $e){ $e->getMessage(); }
			try { Capsule::table('tblconfiguration')->insert(array('setting' => 'ggpwhmcsadminurl', 'value' => $admin_url, 'created_at' => date("Y-m-d H:i:s") , 'updated_at' => date("Y-m-d H:i:s")));}
			catch (\Exception $e){ $e->getMessage(); }
			try { Capsule::table('tblconfiguration')->insert(array('setting' => 'ggpwhmcsadminpath', 'value' => $whmcs_admin_path, 'created_at' => date("Y-m-d H:i:s") , 'updated_at' => date("Y-m-d H:i:s")));}
			catch (\Exception $e){ $e->getMessage(); }
		}
		if( $ggpwhmcsurl and ($whmcs_url !== $ggpwhmcsurl) ){
			try { Capsule::table('tblconfiguration')->where( 'setting', 'ggpwhmcsurl')->update(array('value' => $whmcs_url, 'created_at' =>  $ggpwhmcsurl_created_at , 'updated_at' => date("Y-m-d H:i:s")));}
			catch (\Exception $e){$e->getMessage();}
		}
		if( $ggpwhmcsadminurl and ($admin_url !== $ggpwhmcsadminurl) ){
			try { Capsule::table('tblconfiguration')->where( 'setting', 'ggpwhmcsadminurl')->update(array('value' => $admin_url, 'created_at' =>  $ggpwhmcsadminurl_created_at , 'updated_at' => date("Y-m-d H:i:s")));}
			catch (\Exception $e){$e->getMessage();}
		}
		if( $ggpwhmcsadminpath and ($whmcs_admin_path !== $ggpwhmcsadminpath) ){
			try { Capsule::table('tblconfiguration')->where( 'setting', 'ggpwhmcsadminpath')->update(array('value' => $whmcs_admin_path, 'created_at' =>  $ggpwhmcsadminpath_created_at , 'updated_at' => date("Y-m-d H:i:s")));}
			catch (\Exception $e){$e->getMessage();}
		}
	}
	if( !function_exists('ggp_verify_module_updates') ){
	function ggp_verify_module_updates($page_id, $referer,$module_version){
   		$query = 'https://gofas.net/br/updates/?software='.$page_id.'&referer='.$referer.'&version='.$module_version;
    	$curl = curl_init();
    	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
    	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
    	curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
    	curl_setopt($curl, CURLOPT_URL, $query);
		$result = curl_exec($curl);
    	$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		return array(
			'http_status' => $http_status,
			'result' => $result,
		);
	}}
	$available_update_ = ggp_verify_module_updates('14641',$whmcs_url,$module_version);
	if( (int)$available_update_['http_status'] === 200 ){
		$available_update = $available_update_['result'];
		$available_update_int = (int)preg_replace("/[^0-9]/", "", $available_update);
	}
	else {
		$available_update_int = 000;
	}
	if( $available_update_int === $module_version_int ){
		$available_update_message = '<p style="color: green"><i class="fas fa-check-square"></i> Você está executando a versão mais recente do módulo.</p>';
	}
	if( $available_update_int > $module_version_int ){
		$available_update_message = '<p style="font-size: 14px; color: red;"><i class="fas fa-exclamation-triangle"></i> Atualização disponível, verifique a <a style="color:#CC0000;text-decoration:underline;" href="https://gofas.net/?p=14641" target="_blank">versão '.$available_update.'</a>';
	}
	if( $available_update_int < $module_version_int ){
		$available_update_message = '<p style="font-size: 14px; color: orange;"><i class="fas fa-exclamation-triangle"></i> Você está executando uma versão Beta desse módulo.<br>Não recomendamos o uso dessa versão em produção.<br>Baixar versão estável: <a style="color:#CC0000;text-decoration:underline;" href="https://gofas.net/?p=14641" target="_blank">v'.$available_update.'</a>';
	}
	if( $available_update_int === 000 ){
		$available_update_message = '';
	}
	$tbladmins = array();
	foreach( Capsule::table('tbladmins') -> get() as $tbladmins_ ){
		$tbladmins[$tbladmins_->id] = $tbladmins_->id.' - '.$tbladmins_->firstname.' '.$tbladmins_->lastname.' ('.$tbladmins_->username.')';
	}
	$tblticketdepartments = array();
	$tblticketdepartments[] = '';
	foreach( Capsule::table('tblticketdepartments') -> get() as $tblticketdepartments_ ){
		$tblticketdepartments_id			= $tblticketdepartments_->id;
		$tblticketdepartments_name			= $tblticketdepartments_->name;
		$tblticketdepartments[]				= $tblticketdepartments_id.' - '.$tblticketdepartments_name;
	}
	$opt_num = 1;
	$renderize = array(
		'FriendlyName' => array(
			'Type' => 'System',
			'Value' => 'Gofas Galax Pay',
		),
		'separator_1' => array(
			'Description' => '
			<div class="ggp_separator" style="padding: 1px 15px 9px;">
				<div style="width:158px; float: right; padding: 0px;">
				<a target="_blank" href="https://gofas.net/ggp/"><img style=" width: 135px;margin: 0px 0px 15px 0px;" src="'.$whmcs_url.'/modules/gateways/gofasgalaxpay/assets/img/gofas_software.png"></a>
					
				<a target="_blank" href="https://app.galaxpay.com.br/abrir-conta?affiliateHash=34c8f0bb"><img style=" width: 150px;" src="'.$whmcs_url.'/modules/gateways/gofasgalaxpay/assets/img/galaxpay_logo.png"></a>

				</div>
				<div style="margin-left: 10px;">
					<h4 style="padding-top: 5px;">Módulo Gofas Galax Pay para WHMCS v'.$module_version.'</h4>
					'.$available_update_message.'
					<p><a style="text-decoration:underline;" target="_blank" href="https://gofas.net/?p=14641#configuration">Documentação do módulo</a>.</p>
					<p><a style="text-decoration:underline;" target="_blank" href="https://docs.galaxpay.com.br/">Documentação da API Galax Pay</a>.</p>
					<p>Crie um <a style="text-decoration:underline;" target="_blank" href="'.$admin_url.'/configcustomfields.php">campo personalizado de cliente</a> para CPF e/ou CNPJ, ou se preferir, crie dois campos distintos, um campo apenas para CPF e outro campo para CNPJ. O módulo identifica os campos do perfil do cliente automaticamente.</p>
					<p>Crie um <a style="text-decoration:underline;" target="_blank" href="'.$admin_url.'/configcustomfields.php">campo personalizado de cliente</a> para a data de nascimento. O módulo identifica os campos do perfil do cliente automaticamente.</p>
				</div>
			</div>',
		),
		// Secret Token
		'galax_id' => array(
			'FriendlyName' => $opt_num++.'- Galax ID<span class="ggp_required">*</span>',
			'Type' => 'text',
			'Size' => '50',
			'Default' => '',
			'Description' => '<span class="ggp_required_txt">(Obrigatório)</span> Galax ID | Produção. <a target="_blank" style="text-decoration:underline;" href="https://docs.galaxpay.com.br/suporte">Obter Galax ID</a>',
		),
		'galax_hash' => array(
			'FriendlyName' => $opt_num++.'- Galax Hash<span class="ggp_required">*</span>',
			'Type' => 'text',
			'Size' => '50',
			'Default' => '',
			'Description' => '<span class="ggp_required_txt">(Obrigatório)</span> Galax Hash | Produção. <a target="_blank" style="text-decoration:underline;" href="https://docs.galaxpay.com.br/suporte">Obter Galax Hash</a>',
		),
		// Sandbox Secret Token
		'sandbox_galax_id' => array(
			'FriendlyName' => $opt_num++.'- Sandbox Galax ID<span class="ggp_required">*</span>',
			'Type' => 'text',
			'Size' => '50',
			'Default' => '',
			'Description' => '<span class="ggp_required_txt">(Obrigatório)</span> Galax ID | Testes. <a target="_blank" style="text-decoration:underline;" href="https://docs.galaxpay.com.br/autenticacao">Obter Galax ID</a>',
		),
		// Sandbox Secret Token
		'sandbox_galax_hash' => array(
			'FriendlyName' => $opt_num++.'- Sandbox Galax Hash<span class="ggp_required">*</span>',
			'Type' => 'text',
			'Size' => '50',
			'Default' => '',
			'Description' => '<span class="ggp_required_txt">(Obrigatório)</span> Galax Hash | Testes. <a target="_blank" style="text-decoration:underline;" href="https://docs.galaxpay.com.br/autenticacao">Obter Galax Hash</a>',
		),
		'admin' => array(
			'FriendlyName' => $opt_num++.'- Administrador do WHMCS<span class="ggp_required">*</span>',
			'Type'          => 'dropdown',
			'Default' 		=> key(reset($tbladmins)),
            'Options'       => $tbladmins,
			'Description' => 'Defina o administrador com permissões para utilizar a API interna do WHMCS.',
		),
		// Sandbox
		'sandbox' => array(
			'FriendlyName' => $opt_num++.'- <i>Sandbox</i>',
			'Type' => 'yesno',
			'Default' => 'yes',
			'Description' => 'Ative essa opção para gerar cobranças em modo de testes.',
		),
		// Log
		'log' => array(
			'FriendlyName' => $opt_num++.'- Salvar Logs',
			'Type' => 'yesno',
			'Default' => 'yes',
			'Description' => 'Salva informações de diagnóstico em <a target="_blank" style="text-decoration: underline;" href="'.$admin_url.'/systemmodulelog.php">Utilitários > Logs > Log de Módulo</a>. Para funcionar, antes é necessário ativar o debug de módulo clicando em "Ativar Log de Debug". <a target="_blank" style="text-decoration: underline;" href="'.$admin_url.'/systemmodulelog.php">VER LOG</a>.',
		),
		// Notificar admin sobre erros
		'emailonerror' => array(
			'FriendlyName' => $opt_num++.'- Notificar admins',
			'Type'          => 'dropdown',
			'Default' 		=> '0',
            'Options'       => $tblticketdepartments,
			'Description' => 'Escolha o departamento de suporte que receberá notificação por email quando houver erros ao gerar cobranças',
		),
		// minimum amount
		'minimunamount' => array(
			'FriendlyName' => $opt_num++.'- Valor mínimo',
			'Type' => 'text',
			'Size' => '10',
			'Default' => '5',
			'Description' => 'Insira o valor total mínimo da fatura para permitir pagamento via Cartão. Formato: Decimal, separado por ponto. Maior ou igual a sua tarifa (a partir de 2.50) e menor ou igual a 1000000.00.',
		),
		///////////// 
		'separator_1_2' => array(
			'Description' => '
			<div class="ggp_separator" style="padding: 1px 15px 9px;">
				<h4>Tipos de cobrança</h4>
				<p>Cartão de Crédito, Boleto Bancário e PIX. Cada opção ativada funciona e será exibida de forma independente, como se fossem módulos distintos.</p>
			</div>',
		),
		// Card
		'card' => array(
			'FriendlyName' => $opt_num++.'- <i>Cartão</i>',
			'Type' => 'yesno',
			'Default' => 'yes',
			'Description' => 'Ativar cobrança via Cartão de Crédito',
		),
		// Billet
		'billet' => array(
			'FriendlyName' => $opt_num++.'- <i>Boleto</i>',
			'Type' => 'yesno',
			'Default' => 'yes',
			'Description' => 'Ativar cobrança via Boleto',
		),
		// Pix
		'pix' => array(
			'FriendlyName' => $opt_num++.'- <i>PIX</i>',
			'Type' => 'yesno',
			'Default' => 'yes',
			'Description' => 'Ativar cobrança via PIX',
		),
		///////////// 
		'separator_2' => array(
			'Description' => '
			<div class="ggp_separator" style="padding: 1px 15px 9px;">
				<h4>Cartão de Crédito - Configurações</h4>
			</div>',
		),
		// Permitir Parcelamento
		'installments' => array(
			'FriendlyName' => $opt_num++.'- Permitir parcelamento',
			'Type' => 'yesno',
			'Default' => 'yes',
			'Description' => '<span class="ggp_optional_txt">(Opcional)</span> Com essa opção ativada seu cliente verá opções de parcelamento na fatura quando aplicável.',
		),
		// valor mínimo para parcelamento
		'minimunamountinstallments' => array(
			'FriendlyName' => $opt_num++.'- Valor mínimo para parcelamento',
			'Type' => 'text',
			'Size' => '10',
			'Default' => '1000',
			'Description' => '<span class="ggp_optional_txt">(Opcional)</span> Insira o valor mínimo da fatura para permitir Pagamento Parcelado.',
		),
		// máximo de parcelas
        'maxinstallments' => array(
            'FriendlyName' =>  $opt_num++.'- Máximo de parcelas',
            'Type' => 'dropdown',
			'Default' => '2',
            'Options' => array(
                '2' => 'Até 2 parcelas',
                '3' => 'Até 3 parcelas',
                '4' => 'Até 4 parcelas',
				'5' => 'Até 5 parcelas',
				'6' => 'Até 6 parcelas',
				'7' => 'Até 7 parcelas',
				'8' => 'Até 8 parcelas',
				'9' => 'Até 9 parcelas',
				'10' => 'Até 10 parcelas',
				'11' => 'Até 11 parcelas',
				'12' => 'Até 12 parcelas',
            ),
            'Description' => '<span class="ggp_optional_txt">(Opcional)</span> Selecione o número máximo de parcelas permitido.</span>',
        ),
		///////////// 
		'separator_3' => array(
			'Description' => '
			<div class="ggp_separator" style="padding: 1px 15px 9px;">
				<h4>Boleto Bancário - Configurações</h4>
			</div>',
		),
		// Billet on email
		'billetonemail' => array(
			'FriendlyName' => $opt_num++.'- Informações do Boleto no email',
			'Type' => 'yesno',
			'Default' => 'yes',
			'Description' => 'Adiciona link, linha digitável, vencimento e outras informações do boleto no corpo dos emails de faturas. Essa opção faz o módulo gerar os boletos no momento em que a fatura é gerada e enviada por email. Desative para gerar o boleto no 1º acesso à fatura. <a style="font-weight: bold;text-decoration:underline;" target="_blank" href="https://gofas.net/?p=11116#mergetags">Veja aqui a lista de tags disponíveis para os emails.</a> .',
		),
		
		// Replace Invoice link for Billet link on email
		'linkbilletonemail' => array(
			'FriendlyName' => $opt_num++.'- Link direto para o Boleto no email',
			'Type' => 'yesno',
			//'Default' => 'yes',
			'Description' => 'Substitui o URL da Fatura pelo URL do Boleto nos emails de "Nova Fatura" (tag <code>{$invoice_link}</code> do template de email <i>Invoice Created</i>).',
		),
		// Dias + vencimento
		'daysfordue' => array(
            'FriendlyName'      => $opt_num++.'- Dias adicionais para novo vencimento',
            'Type'              => 'text',
			'Size'				=> '10',
			'Default' 			=> '2',
            'Description'       => 'Número de dias que serão somados a data do vencimento do Boleto, ao gerar segunda via do boleto ou quando o cliente acessa uma fatura vencida. Essa opção aplica-se apenas a Faturas vencidas, faturas que ainda não venceram sempre irão gerar Boletos com a mesma data de vencimento da Fatura. As configurações de juros e multa anulam essa configuração.',
        ),
		// Número máximo de dias que o boleto poderá ser pago após o vencimento
		'maxoverduedays' => array(
            'FriendlyName'      => $opt_num++.'- Máximo de dias para o pagamento',
            'Type'              => 'text',
			'Size'				=> '10',
			'Default' 			=> '29',
            'Description'       => 'Número máximo de dias que o boleto poderá ser pago após o vencimento. Zero significa que o boleto não poderá ser pago após o vencimento. Formato: Número inteiro maior ou igual a 0 e menor ou igual a 29',
        ),
		///////////// 
		'separator_4' => array(
			'Description' => '
			<div class="ggp_separator" style="padding: 1px 15px 9px;">
				<h4>PIX - Configurações</h4>
			</div>',
		),
		// top message
		'top_message' => array(
			'FriendlyName' => $opt_num++.'- Mensagem acima do código QR',
			'Type' => 'text',
			'Size' => '50',
			'Default' => 'Pague escaneando o QR code<br>ou copiando e colando a chave',
			'Description' => 'Permitido HTML',
		),
		// Logo
		'pix_logo' => array(
			'FriendlyName' => $opt_num++.'- Exibir Logo PIX',
			'Type' => 'yesno',
			'Default' => 'yes',
			'Description' => 'Exibe logotipo "PIX powered by Banco Central" na fatura, acima do <i>QR Code</i>',
		),
		// Data e hora
		'show_date' => array(
			'FriendlyName' => $opt_num++.'- Exibir data e hora do código QR',
			'Type' => 'yesno',
			'Default' => 'yes',
			'Description' => 'Exemplo: "Gerado em 08/01/2022 às 08:06:30"',
		),
		// Log
		'show_total' => array(
			'FriendlyName' => $opt_num++.'- Exibir valor total do código QR',
			'Type' => 'yesno',
			'Default' => 'yes',
			'Description' => 'Exemplo: "Total: R$ 24.800,00"',
		),
	);
	$footer = array('footer' => array(
			'Description' => '<div class="ggp_section">
			<p>&copy; '.date('Y').' <a style="text-decoration:underline;" target="_blank" title="↗ Gofas.net" href="https://gofas.net">Gofas.net</a> | <a style="text-decoration:underline;" target="_blank" title="↗ Gofas.net" href="https://gofas.net/?p=14641#changelog">'.$module_version.'</a> | <a  style="text-decoration:underline;"target="_blank" title="↗ Documentação" href="https://gofas.net/?p=14641">Documentação</a> | <a style="text-decoration:underline;" target="_blank" title="↗ Fórum de Suporte" href="https://gofas.net/foruns/">Suporte</a>.</p>
			<p style="font-size: 11px;">
			Ao utilizar esse módulo você concorda com nosso <a style="text-decoration:underline;" target="_blank" title="↗ Contrato de licença de uso de software" href="https://gofas.net/?p=9340">contrato de licença de uso de software</a>.
			</p>
			'.$available_update_message.'
			</div>',
		),
	);
	return array_merge($renderize,$footer);
}
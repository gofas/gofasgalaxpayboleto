<?php
/**
 * Módulo Juno Cartão para WHMCS
 * @copyright	2020 Gofas Software
 * @see			https://gofas.net/?p=12042
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=14644
 * @version		1.3.0
 */
if (!defined('WHMCS')){
    die();
}
use WHMCS\Database\Capsule;
$customer = array();
$customfields = array();
$address_number = preg_replace('/[^0-9]/', '', $params['clientdetails']['address1']);
foreach (Capsule::table('tblcustomfields')->where('type','=','client')->get(array('fieldname','id')) as $customfield){
    $customfield_id = $customfield->id;
    $customfield_name = ' ' . strtolower($customfield->fieldname);
    if (strpos($customfield_name, 'cpf') and !strpos($customfield_name, 'cnpj')){
        foreach (Capsule::table('tblcustomfieldsvalues')->where('fieldid', '=', $customfield_id)->where('relid', '=', $params['clientdetails']['id'])->get(array(
            'value'
        )) as $customfieldvalue)
        {
            $cpf_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
        }
    }
    if (strpos($customfield_name, 'cnpj') and !strpos($customfield_name, 'cpf')){
        foreach (Capsule::table('tblcustomfieldsvalues')->where('fieldid', '=', $customfield_id)->where('relid', '=', $params['clientdetails']['id'])->get(array(
            'value'
        )) as $customfieldvalue)
        {
            $cnpj_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
        }
    }
    if (strpos($customfield_name, 'cpf') and strpos($customfield_name, 'cnpj')){
        foreach (Capsule::table('tblcustomfieldsvalues')->where('fieldid', '=', $customfield_id)->where('relid', '=', $params['clientdetails']['id'])->get(array(
            'value'
        )) as $customfieldvalue)
        {
            $cpf_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
            $cnpj_customfield_value = preg_replace("/[^0-9]/", "", $customfieldvalue->value);
        }
    }
	// Número Custom Field
	if( strpos( $customfield_name, 'numero') || strpos( $customfield_name, 'número')){
		foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $params['clientdetails']['id']) -> get( array( 'value') ) as $customfieldvalue ){
			if(!empty($customfieldvalue->value)){
                $address_number = $customfieldvalue->value;
	        }
        }
	}
    // Complemento Custom Field
	if( strpos( $customfield_name, 'complemento') !== false){
		foreach( Capsule::table('tblcustomfieldsvalues') -> where( 'fieldid', '=', $customfield_id ) -> where( 'relid', '=', $params['clientdetails']['id']) -> get( array( 'value') ) as $customfieldvalue ){
			$address_complement = $customfieldvalue->value;
		}
	}
}
//$customer['number'] = $number;
if (strlen($cpf_customfield_value) === 10){
    $cpf = '0' . $cpf_customfield_value;
}
elseif (strlen($cpf_customfield_value) === 11){
    $cpf = $cpf_customfield_value;
}
elseif (strlen($cpf_customfield_value) === 13){
    $cpf = false;
    $cnpj = '0' . $cpf_customfield_value;
}
elseif (strlen($cpf_customfield_value) === 14){
    $cpf = false;
    $cnpj = $cpf_customfield_value;
}
elseif (!$cpf_customfield_value || strlen($cpf_customfield_value) !== 10 || strlen($cpf_customfield_value) !== 11 || strlen($cpf_customfield_value) !== 13 || strlen($cpf_customfield_value) !== 14){
    $cpf = false;
}
if (strlen($cnpj_customfield_value) === 13){
    $cnpj = '0' . $cnpj_customfield_value;
}
elseif (strlen($cnpj_customfield_value) === 14){
    $cnpj = $cnpj_customfield_value;
}
elseif (!$cnpj_customfield_value and strlen($cnpj_customfield_value) !== 14 and strlen($cnpj_customfield_value) !== 13 and strlen($cpf_customfield_value) !== 13 and strlen($cpf_customfield_value) !== 14){
    $cnpj = false;
}
if (($cpf and $cnpj) or (!$cpf and $cnpj)){
    $customer['doc_type'] = 2;
    $customer['document'] = $cnpj;
    if ($params['clientdetails']['companyname']){
        $customer['name'] = $params['clientdetails']['companyname'];
    }
    elseif (!$params['clientdetails']['companyname']){
        $customer['name'] = $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];
    }
}
elseif ($cpf and !$cnpj){
    $customer['doc_type'] = 1;
    $customer['document'] = $cpf;
    $customer['name'] = $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];
}
if (!$cpf and !$cnpj){
    $customer['doc_type'] = NULL;
    $customer['document'] = NULL;
}
if ($params['sandbox']){
    $api_mode = 'sandbox';
    $token = $params['sandbox_token'];
    $public_token = $params['sandbox_public_token'];
    $charge_url = 'https://sandbox.boletobancario.com/boletofacil/integration/api/v1/issue-charge';
    $toKenrApearysikOpal = 'D6534FBF56FDAE78FABEA6D423DF7966331F142A711DAD4E183087A60F586BD128D4433E56E52671';
}
elseif (!$params['sandbox']){
    $api_mode = 'live';
    $token = $params['token'];
    $public_token = $params['public_token'];
    $charge_url = 'https://www.boletobancario.com/boletofacil/integration/api/v1/issue-charge';
    $toKenrApearysikOpal = 'DE1836BFE5AD353FE74E38F767A3F280ED4A6A443C22895B31D90FA148C9A73EC1E6346B29319A98';
}
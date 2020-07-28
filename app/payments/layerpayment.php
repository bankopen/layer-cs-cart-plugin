<?php

use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }
if ( !defined('AREA') ) { die('Access denied'); }

$pmode = (!empty($pmode)) ? $pmode : (!empty($_REQUEST['dispatch']) ? $_REQUEST['dispatch'] : '');

	const BASE_URL_SANDBOX = "https://sandbox-icp-api.bankopen.co/api";
    const BASE_URL_UAT = "https://icp-api.bankopen.co/api";
	
	$payment_mode='';
	$apikey='';
	$secretkey='';


if (!empty($_REQUEST['modelayerpayment']) || $pmode == 'checkout.place_order' || $pmode == 'repay') {
	if(!isset($processor_data) && isset($_REQUEST['order_id']))
	{
		$payment_id = db_get_field("SELECT payment_id FROM ?:orders WHERE order_id = ?i", $_REQUEST['order_id']);
        $processor_data = fn_get_payment_method_data($payment_id);
		$processor_data['processor_script'] = 'layerpayment.php';
	}
	$payment_mode=$processor_data['processor_params']['layerpayment_mode'];
	$apikey=$processor_data['processor_params']['layerpayment_apikey'];	
	$secretkey=$processor_data['processor_params']['layerpayment_secretkey'];	
}

if (!empty($_REQUEST['modelayerpayment']) && defined('PAYMENT_NOTIFICATION')) {
  //Postback received with payment details  
  $order_id="";
  $payment_id="";
  $responseMsg="";
  $status = "N";
  
  if(isset($_REQUEST['order_id'])) $order_id=$_REQUEST['order_id'];  
  if (isset($_REQUEST['layer_payment_id']) || !empty($_REQUEST['layer_payment_id'])) {	
	
	$pdata = array(
        'layer_pay_token_id'    => $_REQUEST['layer_pay_token_id'],
        'layer_order_amount'    => $_REQUEST['layer_order_amount'],
        'woo_order_id'     		=> $_REQUEST['woo_order_id'],
        );
		
	try {
		if(verify_hash($pdata,$_REQUEST['hash'],$apikey,$secretkey)){
			$order_info = fn_get_order_info($order_id, true);

            if(!empty($order_info) && !empty($_REQUEST['layer_payment_id'])){

				$payment_data = get_payment_details($_REQUEST['layer_payment_id'],$apikey,$secretkey,$payment_mode);
				
                if(isset($payment_data['error'])){
					$responseMsg .=' '.$payment_data['error'];										
                }

                if(isset($payment_data['id']) && !empty($payment_data)){
                    if($payment_data['payment_token']['id'] != $pdata['layer_pay_token_id']){
                        $responseMsg .=" Layer: received layer_pay_token_id and collected layer_pay_token_id doesnt match";						
                    }

                    if($pdata['layer_order_amount'] != $payment_data['amount'] || $order_info['total'] !=$payment_data['amount'] ){
						$responseMsg .=" Layer: received amount and collected amount doesnt match";						
                    }
                    switch ($payment_data['status']){
                        case 'authorized':
						case 'captured': 
							$payment_id = $_REQUEST['layer_payment_id'];
							$status = 'P';		
                        break;
                        case 'failed':								    
                        case 'cancelled':                                    									
							$payment_id = $_REQUEST['layer_payment_id'];
							$status = 'F';
						break;
                        default:                                    
                        exit;
                        break;
                    }
                } else {
                    $responseMsg .=" invalid payment data received E98";					                            
                }
			} else {
				$responseMsg .=" Layer: Payment cancelled / Failed...";
            }
        } else {			
            $responseMsg .= "hash validation failed";
        }
    } catch (Throwable $exception){
		$responseMsg = "Layer: an error occurred " . $exception->getMessage();		
    }
  }
  
  $pp_response = array();
 
  if($order_id != '' && $_REQUEST['layer_order_amount'] != "" && $status == 'P'){
    $pp_response['order_status'] = $status;
    $pp_response['reason_text'] = '';
    $pp_response['transaction_id'] = $payment_id;
    if ($order_info['status'] == 'N') {
      fn_change_order_status($order_id, $status, '', false);
    }
    fn_update_order_payment_info($order_id, $pp_response);
    fn_order_placement_routines('route', $order_id, false);
  }
  elseif($status == 'F')
  {
    $pp_response['order_status'] = $status;
    $pp_response['reason_text'] =  $responseMsg;
    $pp_response['transaction_id'] = @$payment_id;
    if ($order_info['status'] == 'N') {
      fn_change_order_status($order_id, $status, '', false);
    }
    fn_finish_payment($order_id, $pp_response, false);
    fn_order_placement_routines('route', $order_id, false);
  }
  else {
	 fn_order_placement_routines('route', $order_id, false); 
  }
exit;
} 


if ($pmode == 'checkout.place_order' || $pmode == 'repay') 
{
  //post data  
	if($layerpayment_mode=='live') {
		$remote_script = 'https://payments.open.money/layer';
	}
	else
	{
		$remote_script = 'https://sandbox-payments.open.money/layer';					
	}
	
	$layer_payment_token_data = create_payment_token([
        'amount' => $order_info['total'],
        'currency' => $order_info['secondary_currency'],
        'name'  => $order_info['s_firstname'].' '.$order_info['s_lastname'],
        'email_id' => $order_info['email'],
        'contact_number' => $order_info['s_phone']                
        ],$apikey,$secretkey,$payment_mode);
			
	$return_url = fn_url("payment_notification.return", AREA, 'current');  	// rest in tpl
	$return_url .= '&payment=layerpayment&modelayerpayment=1&order_id='.$order_id;
	
	$error="";
	$payment_token_data = "";
	if(empty($error) && isset($layer_payment_token_data['error'])){
		$error = 'E55 Payment error. ' . $layer_payment_token_data['error'];          
	}
	if(empty($error) && (!isset($layer_payment_token_data["id"]) || empty($layer_payment_token_data["id"]))){				
		$error = 'Payment error. ' . 'Layer token ID cannot be empty';        
	}   
   
	if(empty($error))
		$payment_token_data = get_payment_token($layer_payment_token_data["id"],$apikey,$secretkey,$payment_mode);
    
	if(empty($error) && empty($payment_token_data))
		$error = 'Layer token data is empty...';
	
	if(empty($error) && isset($payment_token_data['error'])){
        $error = 'E56 Payment error. ' . $payment_token_data['error'];            
    }

    if(empty($error) && $payment_token_data['status'] == "paid"){
        $error = "Layer: this order has already been paid";            
    }

    if(empty($error) && $payment_token_data['amount'] != $order_info['total']){
        $error = "Layer: an amount mismatch occurred";
    }
	
	$cart = & Tygh::$app['session']['cart'];
	if(empty($error) && !empty($payment_token_data)){
		$hash = create_hash(array(
			'layer_pay_token_id'    => $payment_token_data['id'],
			'layer_order_amount'    => $payment_token_data['amount'],
			'woo_order_id'    => $order_id,
			),$apikey,$secretkey);
		Tygh::$app['view']->assign('cart',$cart);		
		Tygh::$app['view']->assign('error',$error);
		Tygh::$app['view']->assign('currency',$order_info['secondary_currency']);
		Tygh::$app['view']->assign('remote_script',$remote_script);
		Tygh::$app['view']->assign('woo_order_id',$order_id);
		Tygh::$app['view']->assign('payment_token_id',$payment_token_data['id']);	
		Tygh::$app['view']->assign('payment_token_amount',$order_info['total']);
		Tygh::$app['view']->assign('return_url',$return_url);
		Tygh::$app['view']->assign('hash',$hash);
		Tygh::$app['view']->assign('apikey',$apikey);
	}
	else {
		Tygh::$app['view']->assign('cart',$cart);		
		Tygh::$app['view']->assign('error',$error);
	}

    Tygh::$app['view']->display('views/orders/components/payments/layerpayment.tpl');


exit;

	
}

	function create_hash($data,$apikey,$secretkey){
		ksort($data);
		$hash_string = $apikey;
		foreach ($data as $key=>$value){
			$hash_string .= '|'.$value;
		}
		return hash_hmac("sha256",$hash_string,$secretkey);
	}
	
	function verify_hash($data,$rec_hash,$apikey,$secretkey){
		$gen_hash = create_hash($data,$apikey,$secretkey);
		if($gen_hash === $rec_hash){
			return true;
		}
		return false;
	}
	
	function create_payment_token($data,$apikey,$secretkey,$payment_mode){
		
        try {
            $pay_token_request_data = array(
                'amount'   			=> (isset($data['amount']))? $data['amount'] : NULL,
                'currency' 			=> (isset($data['currency']))? $data['currency'] : NULL,
                'name'     			=> (isset($data['name']))? $data['name'] : NULL,
                'email_id' 			=> (isset($data['email_id']))? $data['email_id'] : NULL,
                'contact_number' 	=> (isset($data['contact_number']))? $data['contact_number'] : NULL,
                'mtx'    			=> (isset($data['mtx']))? $data['mtx'] : NULL,
                'udf'    			=> (isset($data['udf']))? $data['udf'] : NULL,
            );

            $pay_token_data = http_post($pay_token_request_data,"payment_token",$apikey,$secretkey,$payment_mode);

            return $pay_token_data;
        } catch (Exception $e){			
            return [
                'error' => $e->getMessage()
            ];

        } catch (Throwable $e){
			
			return [
                'error' => $e->getMessage()
            ];
        }
    }

    function get_payment_token($payment_token_id,$apikey,$secretkey,$payment_mode){

        if(empty($payment_token_id)){

            throw new Exception("payment_token_id cannot be empty");
        }

        try {

            return http_get("payment_token/".$payment_token_id,$apikey,$secretkey,$payment_mode);

        } catch (Exception $e){

            return [
                'error' => $e->getMessage()
            ];

        } catch (Throwable $e){

            return [
                'error' => $e->getMessage()
            ];
        }

    }

    function get_payment_details($payment_id,$apikey,$secretkey,$payment_mode){
		
        if(empty($payment_id)){

            throw new Exception("payment_id cannot be empty");
        }

        try {
			
            return http_get("payment/".$payment_id,$apikey,$secretkey,$payment_mode);

        } catch (Exception $e){
			
            return [
                'error' => $e->getMessage()
            ];

        } catch (Throwable $e){

            return [
                'error' => $e->getMessage()
            ];
        }

    }


    function build_auth($body,$method,$apikey,$secretkey){

        $time_stamp = trim(time());
        unset($body['udf']);

        if(empty($body)){

            $token_string = $time_stamp.strtoupper($method);

        } else {            
            $token_string = $time_stamp.strtoupper($method).json_encode($body);
        }

        $token = trim(hash_hmac("sha256",$token_string,$secretkey));

        return array(                       
            'Content-Type: application/json',                                 
            'Authorization: Bearer '.$apikey.':'.$secretkey,
            'X-O-Timestamp: '.$time_stamp
        );

    }


    function http_post($data,$route,$apikey,$secretkey,$payment_mode){

        foreach (@$data as $key=>$value){

            if(empty($data[$key])){

                unset($data[$key]);
            }
        }

        if($payment_mode == 'test'){
            $url = BASE_URL_SANDBOX."/".$route;
        } else {
            $url = BASE_URL_UAT."/".$route;
        }
		
        $header = build_auth($data,"post",$apikey,$secretkey);
		try
        {
            $curl = curl_init();
		    curl_setopt($curl, CURLOPT_URL, $url);
		    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		    curl_setopt($curl, CURLOPT_SSLVERSION, 6);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS,10);
		    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		    curl_setopt($curl, CURLOPT_ENCODING, '');		
		    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_HEX_APOS|JSON_HEX_QUOT ));
            
		    $response = curl_exec($curl);
            $curlerr = curl_error($curl);
            
            if($curlerr != '')
            {
                return [
                    "error" => "Http Post failed",
                    "error_data" => $curlerr,
                ];
            }
			
            return json_decode($response,true);
        }
        catch(Exception $e)
        {
            return [
                "error" => "Http Post failed",
                "error_data" => $e->getMessage(),
            ];
        }           
        
    }

    function http_get($route,$apikey,$secretkey,$payment_mode){

        if($payment_mode == 'test'){
			$url = BASE_URL_SANDBOX."/".$route;
        } else {			
            $url = BASE_URL_UAT."/".$route;
		}

        $header = build_auth($data = [],"get",$apikey,$secretkey);
		
        try
        {           
            $curl = curl_init();
		    curl_setopt($curl, CURLOPT_URL, $url);
		    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		    curl_setopt($curl, CURLOPT_SSLVERSION, 6);
		    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		    curl_setopt($curl, CURLOPT_ENCODING, '');		
		    curl_setopt($curl, CURLOPT_TIMEOUT, 60);		   
            $response = curl_exec($curl);
            $curlerr = curl_error($curl);
			
            if($curlerr != '')
            {
                return [
                    "error" => "Http Get failed",
                    "error_data" => $curlerr,
                ];
            }
            return json_decode($response,true);
        }
        catch(Exception $e)
        {
            return [
                "error" => "Http Get failed",
                "error_data" => $e->getMessage(),
            ];
        }
    }
?>

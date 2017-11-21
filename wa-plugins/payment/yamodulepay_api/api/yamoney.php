<?php

Class YandexMoney
{
	public $test;
	public $org_mode;
	public $shopid;
	public $password;
	public $password2;
	const MONEY_URL = "https://money.yandex.ru";
    const SP_MONEY_URL = "https://sp-money.yandex.ru";

	public function __construct(){
		
	}

	public static function log_save($logtext)
	{
		$real_log_file = './ya_logs/'.date('Y-m-d').'.log';
		$h = fopen($real_log_file , 'ab');
		fwrite($h, date('Y-m-d H:i:s ') . '[' . addslashes($_SERVER['REMOTE_ADDR']) . '] ' . $logtext . "\n");
		fclose($h);
	}

	public function getEndpointUrl()
    {
        if ($this->test) {
            return 'https://demomoney.yandex.ru/eshop.xml';
        } else {
            return 'https://money.yandex.ru/eshop.xml';
        }
    }

	public function checkSign($callbackParams){
		$string = $callbackParams['action'].';'.$callbackParams['orderSumAmount'].';'.$callbackParams['orderSumCurrencyPaycash'].';'.$callbackParams['orderSumBankPaycash'].';'.$callbackParams['shopId'].';'.$callbackParams['invoiceId'].';'.$callbackParams['customerNumber'].';'.$this->password;
		$md5 = strtoupper(md5($string));
		$this->log_save('kassa: sign '.($callbackParams['md5']==$md5).' '.$callbackParams['md5'].' '.$md5);
		return ($callbackParams['md5']==$md5);
	}

	public function checkOrder($callbackParams, $sendCode = false, $aviso = false){ 
		
		if ($this->checkSign($callbackParams)){
			$code = 0;
		}else{
			$code = 1;
		}
		if ($sendCode){
			if ($aviso){
				$this->log_save('kassa: send message="sendAviso" performedDatetime="'.date("c").'" code="'.$code.'" invoiceId="'.$callbackParams['invoiceId'].'" shopId="'.$this->shopid.'"');
				$this->sendAviso($callbackParams, $code);	
			}else{
				$this->log_save('kassa: send message="checkOrder" performedDatetime="'.date("c").'" code="'.$code.'" invoiceId="'.$callbackParams['invoiceId'].'" shopId="'.$this->shopid.'"');
				$this->sendCode($callbackParams, $code);	
			}
			exit;
		}else{
			return $code;
		}
	}

	public function sendCode($callbackParams, $code){
		header("Content-type: text/xml; charset=utf-8");
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
			<checkOrderResponse performedDatetime="'.date("c").'" code="'.$code.'" invoiceId="'.$callbackParams['invoiceId'].'" shopId="'.$this->shopid.'"/>';
		die($xml);
	}

	public function sendAviso($callbackParams, $code){
		header("Content-type: text/xml; charset=utf-8");
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
			<paymentAvisoResponse performedDatetime="'.date("c").'" code="'.$code.'" invoiceId="'.$callbackParams['invoiceId'].'" shopId="'.$this->shopid.'"/>';
		die($xml);
	}

	public function individualCheck($callbackParams){
		$string = $callbackParams['notification_type'].'&'.$callbackParams['operation_id'].'&'.$callbackParams['amount'].'&'.$callbackParams['currency'].'&'.$callbackParams['datetime'].'&'.$callbackParams['sender'].'&'.$callbackParams['codepro'].'&'.$this->password2.'&'.$callbackParams['label'];
		$check = (sha1($string) == $callbackParams['sha1_hash']);
		if (!$check){
			header('HTTP/1.0 401 Unauthorized');
			return false;
		}

		return true;	
	}

	public function getAccessToken($client_id, $code, $redirect_uri, $client_secret = null)
	{
        $full_url = self::SP_MONEY_URL . "/oauth/token";
        $result = $this->SendCurl($full_url, array(), array(
            "code" => $code,
            "client_id" => $client_id,
            "grant_type" => "authorization_code",
            "redirect_uri" => $redirect_uri,
            "client_secret" => $client_secret
        ));

        return json_decode($result->body);
    }

	public static function buildObtainTokenUrl($client_id, $redirect_uri, $scope)
	{
        $params = sprintf(
            "client_id=%s&response_type=%s&redirect_uri=%s&scope=%s",
            $client_id, "code", $redirect_uri, implode(" ", $scope)
		);

        return sprintf("%s/oauth/authorize?%s", self::SP_MONEY_URL, $params);
    }

	public function descriptionError($error)
	{
		$error_array = array(
			'invalid_request' => 'Your request is missing required parameters or settings are incorrect or invalid values',
			'invalid_scope' => 'The scope parameter is missing or has an invalid value or a logical contradiction',
			'unauthorized_client' => 'Invalid parameter client_id, or the application does not have the right to request authorization (such as its client_id blocked Yandex.Money)',
			'access_denied' => 'Has declined a request authorization application',
			'invalid_grant' => 'The issue access_token denied. Issued a temporary token is not Google search or expired, or on the temporary token is issued access_token (second request authorization token with the same time token)',
			'illegal_params' => 'Required payment options are not available or have invalid values.',
			'illegal_param_label' => 'Invalid parameter value label',
			'phone_unknown' => 'A phone number is not associated with a user account or payee',
			'payment_refused' => 'Магазин отказал в приеме платежа (например, пользователь пытался заплатить за товар, которого нет в магазине)',
			'limit_exceeded' => 'Exceeded one of the limits on operations: on the amount of the transaction for authorization token issued; transaction amount for the period of time for the token issued by the authorization; Yandeks.Deneg restrictions for different types of operations.',
			'authorization_reject' => 'In payment authorization is denied. Possible reasons are: transaction with the current parameters is not available to the user; person does not accept the Agreement on the use of the service "shops".',
			'contract_not_found' => 'None exhibited a contract with a given request_id',
			'not_enough_funds' => 'Insufficient funds in the account of the payer. Need to recharge and carry out a new delivery',
			'not-enough-funds' => 'Insufficient funds in the account of the payer. Need to recharge and carry out a new delivery',
			'money_source_not_available' => 'The requested method of payment (money_source) is not available for this payment',
			'illegal_param_csc' => 'tsutstvuet or an invalid parameter value cs',
			'payment_refused' => 'Shop for whatever reason, refused to accept payment.'
		);
		if(array_key_exists($error,$error_array))
			$return = $error_array[$error];
		else
			$return = $error;
		return $return;
	}
	
	public function SendCurl($url, $headers, $params){
		$curl = curl_init($url);
		if(isset($headers['Authorization'])){
			$token = $headers['Authorization'];
			$headers = array();
			$headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
			$headers[] = 'Authorization: '.$token;
		}
		$params = http_build_query($params);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_USERAGENT, 'yamolib-php');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);   
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 80);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        $rbody = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $rcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		// self::de(curl_getinfo($curl, CURLINFO_HEADER_OUT), false);
        curl_close($curl);
		$result = new stdClass();
		$result->status_code = $rcode;
		$result->body = $rbody;
		return $result;
	}

	public function sendRequest($url, $options = array(), $access_token = null)
	{
        $full_url= self::MONEY_URL . $url;
        if($access_token != null) {
            $headers = array(
                "Authorization" => sprintf("Bearer %s", $access_token),
            );
        } 
        else {
            $headers = array();
        }
        $result = $this->SendCurl($full_url, $headers, $options);
        return json_decode($result->body);
    }
}
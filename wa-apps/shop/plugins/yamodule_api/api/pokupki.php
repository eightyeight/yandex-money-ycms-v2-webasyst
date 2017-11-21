<?php

Class YaApiPokupki
{
    public $app_id;
    public $url;
    public $number;
    public $login;
    public $app_pw;
    public $ya_token;

    public function makeData()
    {
        $sm = new waAppSettingsModel();
        $data = $sm->get('shop.yamodule_api');

        $this->app_id = $data['ya_pokupki_appid'];
        $this->url = $data['ya_pokupki_url'];
        $this->number = $data['ya_pokupki_campaign'];
        $this->login = $data['ya_pokupki_login'];
        $this->ya_token = $data['ya_pokupki_token'];
    }

    public function getOrders()
    {
        return $data = $this->SendResponse('/campaigns/'.$this->number.'/orders', array(), array(), 'GET');
    }

    public function getOutlets()
    {
        $data = $this->SendResponse('/campaigns/'.$this->number.'/outlets', array(), array(), 'GET');
        $array = array('outlets' => array());
        foreach($data->outlets as $o)
            $array['outlets'][] = array('id' => (int)$o->shopOutletId);
        $return = array(
            'json' => $array,
            'array' => $data->outlets
        );

        return $return;
    }

    public function getOrder($id)
    {
        $data = $this->SendResponse('/campaigns/'.$this->number.'/orders/'.$id, array(), array(), 'GET');
        return $data;
    }

    public function sendOrder($state, $id)
    {
        $params = array(
            'order' => array(
                'status' => $state,
            )
        );

        if($state == 'CANCELLED')
            $params['order']['substatus'] = 'SHOP_FAILED';

        return $data = $this->SendResponse('/campaigns/'.$this->number.'/orders/'.$id.'/status', array(), $params, 'PUT');
    }

    public static function log_save($logtext)
    {
        $logtext = json_encode($logtext);
        $real_log_file = './ya_logs/'.date('Y-m-d').'.log';
        $h = fopen($real_log_file , 'ab');
        fwrite($h, date('Y-m-d H:i:s ') . '[' . addslashes($_SERVER['REMOTE_ADDR']) . '] ' . $logtext . "\n");
        fclose($h);
    }

    public function SendResponse($to, $headers, $params, $type)
    {
        $response = $this->post($this->url.$to.'.json?oauth_token='.$this->ya_token.'&oauth_client_id='.$this->app_id.'&oauth_login='.$this->login, $headers, $params, $type);
        $data = json_decode($response->body);
        if(isset($data->error))
            $this->log_save($response->body);
        if($response->status_code == 200)
            return $data;
        else
            $this->log_save($response);
    }

    public static function post($url, $headers, $params, $type){
        $curlOpt = array(
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLINFO_HEADER_OUT => 1,
        );

        switch (strtoupper($type)){
            case 'DELETE':
                $curlOpt[CURLOPT_CUSTOMREQUEST] = "DELETE";
            case 'GET':
                if (!empty($params))
                    $url .= (strpos($url, '?')===false ? '?' : '&') . http_build_query($params);
            break;
            case 'PUT':
                $headers[] = 'Content-Type: application/json;';
                $body = json_encode($params);
                $fp = tmpfile();
                fwrite($fp, $body, strlen($body));
                fseek($fp, 0);
                $curlOpt[CURLOPT_PUT] = true;
                $curlOpt[CURLOPT_INFILE] = $fp;
                $curlOpt[CURLOPT_INFILESIZE] = strlen($body);
            break;
        }

        $curlOpt[CURLOPT_HTTPHEADER] = $headers;
        $curl = curl_init($url);
        curl_setopt_array($curl, $curlOpt);
        $rbody = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $rcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $result = new stdClass();
        $result->status_code = $rcode;
        $result->body = $rbody;
        $result->error = $error;
        // $this->log_save(json_encode($result));
        // waSystem::dieMod($result);
        return $result;
    }
}
<?php

Class MwsApi
{
    public $CertPem;
    public $PkeyPem;
    public $shopId;
    public $demo = false;
    public $txt_request;
    public $txt_respond;
    public $txt_error;
    public $sum = 0;

    public function addReturn($data){
        $model = new waModel();
        $model->query("INSERT INTO `shop_mws_return` (`".implode("`,`",array_keys($data))."`)
            VALUES ('".implode("','",array_values($data))."')");
    }

    public function getSuccessReturns($inv){
        $data = array();
        $model = new waModel();
        $order_query = $model->query("SELECT * FROM `shop_mws_return` o WHERE o.invoice_id = '".$inv."' ORDER BY `date` DESC")->fetchAll();
        $sum = 0;
        if (count($order_query)) {
            $returns = array();
            foreach ($order_query as $oq) {
                if ($oq['status'] == 0) {
                 $returns[] = $oq;
                }
            }
            if ($returns)
                foreach ($returns as $k => $item)
                    $sum += $item['amount'];

            $this->sum = $sum;
            return $returns;
        }

        return false;
    }

    public static function upload(){
        $json = array();
        if (!empty($_FILES['file']['name'])) {
            if (substr($_FILES['file']['name'], -4) != '.cer') {
                $json['error'] = 'Загружаемый файл имеет недопустимое расширение. Сертификат должен быть с расширением .cer';
            }
            if ($_FILES['file']['error'] != UPLOAD_ERR_OK) {
                $json['error'] = $_FILES['file']['error'];
            }
            if (filesize($_FILES['file']['tmp_name'])>2048) {
                $json['error'] = 'Файл не должен превышать 2048 байт';
            }
        } else {
            $json['error'] = 'Ошибка загрузки файла!';
        }
        if (!isset($json['error'])){
            $sm = new waAppSettingsModel();

            $cert = file_get_contents($_FILES['file']['tmp_name']);
            $sm->set('shop.yamodule_api' , 'yamodule_mws_cert', $cert);
        }

        return $json;
    }

    public function request($oper, $param, $sign = true, $is_xml = true, $fields=array()){
        $prepare = $this->getDefaultArray($oper, $fields, $level);
        $data = array_merge ($prepare, $param);
        $xml = $this->sendRequest($oper, $data, $sign, $is_xml);
        $this->txt_respond = $xml;
        $info = $this->parseXML($xml, $fields, $level);
        return (array) $info;
    }

    public static function outputCsr(){
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=csr_for_yamoney.csr');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        $sm = new waAppSettingsModel();
        $data = $sm->get('shop.yamodule_api');
        echo $data['yamodule_mws_csr'];
    }

    public static function generateCsr(){
        $pkey="";
        $csr="";
        $sm = new waAppSettingsModel();
        $data = $sm->get('shop.yamodule_api');
        $sid = isset($data['ya_kassa_shopid']) ? $data['ya_kassa_shopid'] : '';
        $sign = self::genCsrPKey(
        array(
            "countryName" => "RU",
            "stateOrProvinceName" => "Russia",
            "localityName" => "Moscow",
            "commonName" => "/business/webasyst/yacms-".$sid,
        ),	$pkey, $csr);

        $sm->set('shop.yamodule_api' , 'yamodule_mws_pkey', $pkey);
        $sm->set('shop.yamodule_api' , 'yamodule_mws_csr', $csr);
        $sm->set('shop.yamodule_api' , 'yamodule_mws_csr_sign', $sign);
        $sm->set('shop.yamodule_api' , 'yamodule_mws_cert', '');
    }

    public static function genCsrPKey($dn, &$privKey, &$csr_export){
        $config = array(
             "digest_alg" => "sha1",
             "private_key_bits" => 2048,
             "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );
        $dn_full = array_merge(array(
             "countryName" => "RU",
             "stateOrProvinceName" => "Russia",
             "localityName" => ".",
             "organizationalUnitName" => "."
        ), $dn);
        $res = openssl_pkey_new($config);
        $csr_origin = openssl_csr_new($dn_full, $res);
        $csr_full = "";
        openssl_pkey_export($res, $privKey);
        openssl_csr_export($csr_origin, $csr_export);

        openssl_csr_export($csr_origin, $csr_full, false);
        preg_match( '"Signature Algorithm\: (.*)-----BEGIN"ims', $csr_full, $sign);
        $sign = str_replace("\t", "", $sign);
        if ($sign) {
            $sign = $sign[1];
            $a = explode("\n", $sign);
            unset($a[0]);
            $sign = str_replace("         ", "", trim(join("\n", $a)));
        }
        return $sign;
    }

    public function getErr($id){
        $error = array(
        '0' => 'Техническая ошибка или возврат запрещен для данного способа оплаты',
        '10' => 'Ошибка синтаксического разбора XML документа. ',
        '50' => 'Невозможно открыть криптопакет PKCS#7, ошибка целостности данных криптопакета',
        '51' => 'АСП не подтверждена (данные цифровой подписи не совпадают с передаваемым документом)',
        '53' => 'Запрос подписан сертификатом, который неизвестен Яндекс.Деньгам',
        '55' => 'Истек срок действия сертификата магазина',
        '110' => 'У магазина нет прав на выполнение операции с запрошенными параметрами',
        '111' => 'Неверное значение параметра requestDT',
        '112' => 'Неверное значение параметра invoiceId',
        '113' => 'Неверное значение параметра shopId',
        '114' => 'Неверное значение параметра orderNumber',
        '115' => 'Неверное значение параметра clientOrderId',
        '117' => 'Неверное значение параметра status',
        '118' => 'Неверное значение параметра from',
        '119' => 'Неверное значение параметра till',
        '120' => 'Неверное значение параметра orderId',
        '200' => 'Неверное значение параметра outputFormat',
        '201' => 'Неверное значение параметра csvDelimiter',
        '202' => 'Неверное значение параметра orderCreatedDatetimeGreaterOrEqual',
        '203' => 'Неверное значение параметра orderCreatedDatetimeLessOrEqual',
        '204' => 'Неверное значение параметра paid',
        '205' => 'Неверное значение параметраpaymentDatetimeGreaterOrEqual',
        '206' => 'Неверное значение параметраpaymentDatetimeLessOrEqual',
        '207' => 'Неверное значение параметраoutputFields',
        '208' => 'В запросе указан пустой диапазон времени создания заказа',
        '209' => 'В запросе указан слишком большой диапазон времени создания заказа',
        '210' => 'В запросе указан пустой диапазон времени оплаты заказа',
        '211' => 'В запросе указан слишком большой диапазон времени оплаты заказа',
        '212' => 'Логическое противоречие между диапазоном времени оплаты и флагом «только оплаченные»',
        '213' => 'Не указано ни одно условие для ограничения выборки',
        '214' => 'В запросе по номеру заказа (orderNumber) не задан идентификатор магазина (shopId)',
        '215' => 'В запросе по номеру транзакции (invoiceId) не задан идентификатор магазина (shopId)',
        '216' => 'Результат содержит слишком много элементов',
        '217' => 'Неверное значение параметра partial',
        '402' => 'Неверное значение суммы',
        '403' => 'Неверное значение валюты',
        '404' => 'Неверное значение причины возврата',
        '405' => 'Неуникальный номер операции',
        '410' => 'Заказ не оплачен. Возврат невозможен',
        '411' => 'Неуспешный статус доставки уведомления о переводе',
        '412' => 'Валюта перевода отличается от заданной в запросе',
        '413' => 'Сумма возврата, заданная в запросе, превышает сумму перевода',
        '414' => 'Перевод возвращен ранее',
        '415' => 'Заказ с указанным номером транзакции (invoiceId) отсутствует',
        '416' => 'Недостаточно средств для проведения операции',
        '417' => 'Счет плательщика закрыт. Возврат средств на него невозможен.',
        '418' => 'Счет плательщика заблокирован. Возврат средств на него невозможен.',
        '419' => 'Сумма остатка после возврата части перевода меньше 1 рубля',
        '424' => 'Запрещен возврат части суммы для данного способа оплаты',
        '601' => 'Запрещено повторять платежи с банковских карт в пользу магазина',
        '602' => 'Повтор данного платежа запрещен',
        '603' => 'Для данной операции обязателен orderNumber',
        '604' => 'Неверное значение параметра cvv',
        '606' => 'Операция по данной карте запрещена',
        '607' => 'Превышен лимит. Невозможно выполнить операцию по карте',
        '608' => 'Недостаточно средств для проведения операции по карте',
        '609' => 'Техническая ошибка. Невозможно выполнить операцию по карте',
        '611' => 'Истек срок действия банковской карты',
        '612' => 'Операция по данной карте запрещена',
        '1000' => 'Техническая ошибка'
        );

        if (!isset($error[$id]))
            return $id;

        return $error[$id];
    }

    private function getDefaultArray($command, &$fields, &$level){
        $defArray=array();
        $defArray['shopId'] = $this->shopId;
        $defArray['requestDT'] = date('c');

        switch ($command){
            case "listOrders";
            case "listReturns";
                $fields = array('orderNumber','invoiceId','orderSumAmount', 'paymentType');
                $defArray['outputFields'] = implode(';',$fields);
                $level = true;
                break;
            case 'returnPayment':
                $defArray['currency'] = $this->getCurrencyCode();
                $defArray['clientOrderId'] = $this->getClientOrderId();
                $fields = array('status','error','techMessage','clientOrderId');
                $level = false;
                break;
        }
        return $defArray;
    }

    private function getClientOrderId(){
        return '010'.microtime(true);
    }

    private function getCurrencyCode(){
        return ($this->demo) ? '10643' : '643';
    }

    private function getUrlMws($command){
        $demo = ($this->demo)?'-demo':'';
        $port = ($this->demo)?':8083':'';
        $url_server="https://penelope$demo.yamoney.ru$port/webservice/mws/api/".$command;
        return $url_server;
    }

    private function sendRequest($url, $data, $crypt = true, $xml = true){
        $data = ($xml)?$this->createXml($data, $url):$data;
        $this->txt_request = $data;
        $send_data = ($crypt)? $this->sign_pkcs7($data):http_build_query($data);
        return $this->post($url, $send_data);
    }

    private function post($url, $xml){
      $ch = curl_init($this->getUrlMws($url));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_ENCODING, "");
      curl_setopt($ch, CURLOPT_USERAGENT, "Y.CMS Client");  // useragent
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
      curl_setopt($ch, CURLOPT_TIMEOUT, 20);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSLCERT, $this->rwTmpFile($this->CertPem));
      curl_setopt($ch, CURLOPT_SSLKEY, $this->rwTmpFile($this->PkeyPem));
      $content = curl_exec($ch);
      $this->txt_error = curl_error($ch).
          "<br> http-code: ".curl_getinfo($ch, CURLINFO_HTTP_CODE).
          "<br> body: <pre>".$content."</pre>";
      curl_close($ch);
      return $content;
    }

    private function parseXML($xml, $attr=array(),$level=true){
        $answer=array();
        $doc = new DOMDocument();
        @$doc->loadXML($xml);
        if (empty($doc->firstChild)) return false;
        $order_xml=($level)?$doc->firstChild->firstChild:$doc->firstChild;
        foreach ($attr as $name) if (method_exists($order_xml,'hasAttribute') && $order_xml->hasAttribute($name)) $answer[$name]=$order_xml->getAttributeNode($name)->value; else $answer[$name]='';
        return $answer;
    }

    private function createXml($array, $operation){
         $domDocument = new DOMDocument('1.0', 'UTF-8');
         $domElement = $domDocument->createElement($operation."Request");

        if ($operation == 'returnPayment') {
            $receiptContainer = $domDocument->createElement("receipt");
            $itemsContainer = $domDocument->createElement("items");

            $emailAttribute = $domDocument->createAttribute('email');
            $emailAttribute->value = $_POST['email'];

            $receiptContainer->appendChild($emailAttribute);
            $receiptContainer->appendChild($itemsContainer);

            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if ($item['quantity'] < 1) {
                        continue;
                    }

                    $itemContainer = $domDocument->createElement("item");
                    $priceContainer = $domDocument->createElement('price');

                    $qtyAttribute = $domDocument->createAttribute('quantity');
                    $qtyAttribute->value = (int)$item['quantity'];

                    $taxAttribute = $domDocument->createAttribute('tax');
                    $taxAttribute->value = (int)$item['tax'];

                    $textAttribute = $domDocument->createAttribute('text');
                    $textAttribute->value = $item['text'];

                    $amountAttribute = $domDocument->createAttribute('amount');
                    $amountAttribute->value = number_format($item['price']['amount'], 2, '.', '');

                    $currencyAttribute = $domDocument->createAttribute('currency');
                    $currencyAttribute->value = $item['price']['currency'];

                    $priceContainer->appendChild($amountAttribute);
                    $priceContainer->appendChild($currencyAttribute);

                    $itemContainer->appendChild($qtyAttribute);
                    $itemContainer->appendChild($taxAttribute);
                    $itemContainer->appendChild($textAttribute);
                    $itemContainer->appendChild($priceContainer);

                    $itemsContainer->appendChild($itemContainer);
                }
            }

            $domElement->appendChild($receiptContainer);
        }

         foreach ($array as $name=>$val){
            $domAttribute = $domDocument->createAttribute($name);
            $domAttribute->value = $val;
            $domElement->appendChild($domAttribute);
            $domDocument->appendChild($domElement);
         }
         return (string) $domDocument->saveXML();
    }

    private function sign_pkcs7($xml){
        $dataFile = $this->rwTmpFile($xml);
        $signedFile = $this->rwTmpFile();
        if (openssl_pkcs7_sign ($dataFile , $signedFile ,$this->CertPem, $this->PkeyPem, array(), PKCS7_NOCHAIN+PKCS7_NOCERTS)){
            $signedData = explode("\n\n", file_get_contents($signedFile));
            return "-----BEGIN PKCS7-----\n".$signedData[1]."\n-----END PKCS7-----";
        }
    }
    private function rwTmpFile($data=null){
        $temp_file = tempnam(sys_get_temp_dir(), 'YaMWS');
        if ($data!==null) file_put_contents ($temp_file, $data);
        return $temp_file;
    }
}
<?php

use YaMoney\Client\YandexMoneyApi;
use YaMoney\Request\Refunds\CreateRefundRequest;
use YaMoney\Request\Refunds\CreateRefundRequestSerializer;

class shopYamodule_apiPlugin extends shopPlugin
{
    public function sendStatistics()
    {
        global $wa;
        $headers   = array();
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $sm        = new waAppSettingsModel();
        $data      = $sm->get('shop.yamodule_api');
        $data_shop = $sm->get('webasyst');
        $array     = array(
            'url'      => wa()->getUrl(true),
            'cms'      => 'shop-script5',
            'version'  => wa()->getVersion('webasyst'),
            'ver_mod'  => $this->info['version'],
            'email'    => $data_shop['email'],
            'shopid'   => $data['ya_kassa_shopid'],
            'settings' => array(
                'kassa'   => $data['ya_kassa_active'],
                'p2p'     => $data['ya_p2p_active'],
                'metrika' => $data['ya_metrika_active'],
            ),
        );

        $array_crypt = base64_encode(serialize($array));

        $url     = 'https://statcms.yamoney.ru/v2/';
        $curlOpt = array(
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLINFO_HEADER_OUT    => true,
            CURLOPT_POST           => true,
        );

        $curlOpt[CURLOPT_HTTPHEADER] = $headers;
        $curlOpt[CURLOPT_POSTFIELDS] = http_build_query(array('data' => $array_crypt, 'lbl' => 1));

        $curl = curl_init($url);
        curl_setopt_array($curl, $curlOpt);
        $rbody = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $rcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $json = json_decode($rbody);
        if ($rcode == 200 && isset($json->new_version)) {
            return $json->new_version;
        } else {
            return false;
        }
    }

    public function saveSettings($settings = array())
    {
        require_once dirname(__FILE__).'/../api/metrika.php';

        $sm   = new waAppSettingsModel();
        $data = $sm->get('shop.yamodule_api');

        if (waRequest::request('ya_kassa_active')) {
            $sm->set('shop.yamodule_api', 'ya_p2p_active', false);
            $sm->set('shop.yamodule_api', 'ya_billing_active', false);
            $_POST['ya_p2p_active']     = false;
            $_POST['ya_billing_active'] = false;
        } elseif (waRequest::request('ya_p2p_active')) {
            $sm->set('shop.yamodule_api', 'ya_billing_active', false);
            $sm->set('shop.yamodule_api', 'ya_kassa_active', false);
            $_POST['ya_billing_active'] = false;
            $_POST['ya_kassa_active']   = false;
        } elseif (waRequest::request('ya_billing_active')) {
            $sm->set('shop.yamodule_api', 'ya_p2p_active', false);
            $sm->set('shop.yamodule_api', 'ya_kassa_active', false);
            $_POST['ya_p2p_active']   = false;
            $_POST['ya_kassa_active'] = false;
        }

        if (waRequest::request('mode') == 'make_return') {
            $config     = waConfig::getAll();
            $pluginPath = $config['wa_path_plugins'];
            require_once $pluginPath.DIRECTORY_SEPARATOR.'payment'.DIRECTORY_SEPARATOR.'yamodulepay_api'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
            $appsPath = $config['wa_path_apps'];
            require_once $appsPath.DIRECTORY_SEPARATOR.'shop'.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'yamodule_api'.DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'orderReceiptModel.php';
            require_once $appsPath.DIRECTORY_SEPARATOR.'shop'.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'yamodule_api'.DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'orderRefundModel.php';


            $orderId            = waRequest::post('id_order');
            $orderReceiptsModel = new orderReceiptModel();
            $order_model        = new shopOrderModel();
            $transactionModel   = new waTransactionModel();
            $order              = $order_model->getById($orderId);
            $orderReceipt       = $orderReceiptsModel->getByOrderId((int)$order['id']);
            $receipt            = $orderReceipt['receipt'];
            $returnAmount       = waRequest::post('return_sum');
            $transaction        = $this->getTransactionByOrder($transactionModel, (int)$order['id']);
            $paymentId          = $transaction['native_id'];
            $cause              = waRequest::post('return_cause');
            $builder            = CreateRefundRequest::builder()->setPaymentId($paymentId)
                                                     ->setComment($cause)
                                                     ->setAmount($returnAmount);
            if ($receipt) {
                $receiptData = json_decode($receipt, true);
                if (isset($receiptData['phone'])) {
                    $builder->setReceiptPhone($receiptData['phone']);
                }
                if (isset($receiptData['email'])) {
                    $builder->setReceiptEmail($receiptData['email']);
                }
                if (isset($receiptData['tax_system_code'])) {
                    $builder->setTaxSystemCode($receiptData['tax_system_code']);
                }

                foreach ($receiptData['items'] as $item) {
                    $builder->addReceiptItem(
                        $item['description'],
                        $item['amount']['value'],
                        $item['quantity'],
                        $item['vat_code']
                    );
                }
            }
            $refundRequest  = $builder->build();
            $serializer     = new CreateRefundRequestSerializer();
            $serializedData = $serializer->serialize($refundRequest);

            $app          = new waAppSettingsModel();
            $settings     = $app->get('shop.yamodule_api');
            $shopId       = $settings['ya_kassa_shopid'];
            $shopPassword = $settings['ya_kassa_pw'];
            $apiClient    = new YandexMoneyApi();
            $apiClient->setAuth($shopId, $shopPassword);
            $apiClient->setLogger(
                function ($level, $message, $context) {
                    $this->debugLog($message);
                }
            );
            $idempotencyKey = base64_encode($orderId.'/'.microtime());
            $this->debugLog('Idempotency key: '.$idempotencyKey);

            $tries = 0;
            do {
                $response = $apiClient->createRefund($refundRequest, $idempotencyKey);
                $tries++;
                if ($tries > 3) {
                    break;
                }
            } while ($response == null);

            if ($response) {
                if ($response->status == \YaMoney\Model\RefundStatus::SUCCEEDED) {
                    $this->debugLog('Refund create success');
                    $orderRefundModel = new orderRefundModel();
                    $orderRefundModel->add(
                        array(
                            'payment_id'     => $paymentId,
                            'order_id'       => $orderId,
                            'cause'          => $cause,
                            'amount'         => $returnAmount,
                            'request'        => json_encode($serializedData),
                            'status'         => $response->status,
                            'error'          => '',
                            'refund_receipt' => $receipt,
                        )
                    );

                    return array('status' => 'success');
                } elseif ($response->status == \YaMoney\Model\RefundStatus::CANCELED) {
                    $this->debugLog('Refund create failed');
                }
            } else {
                $this->debugLog('Refund responce not created');
            }

            return array('errors' => array('Не удалось осуществить возврат.'));
        }

        $taxValues = array();
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'ya_kassa_tax_') !== false) {
                $taxValues[$k] = $v;
                continue;
            }
            if ($k == 'ya_pokupki_carrier' || $k == 'ya_pokupki_rate' || $k == 'ya_market_categories') {
                $v = serialize($v);
            }
            $sm->set('shop.yamodule_api', $k, $v);
        }

        if ($taxValues) {
            $sm->set('shop.yamodule_api', taxValues, serialize($taxValues));
        }

        $array_fields = array(
            'ya_kassa_shopid'     => _w('Не заполнен shopId'),
            'ya_kassa_pw'         => _w('Не заполнен Секретный ключ'),
            'ya_p2p_number'       => _w('Не заполнен номер кошелька'),
            'ya_p2p_appid'        => _w('Не заполнен id приложения'),
            'ya_p2p_skey'         => _w('Не заполнен секретный ключ'),
            'ya_metrika_number'   => _w('Не заполнен номер счётчика'),
            'ya_metrika_appid'    => _w('Не заполнен id приложения'),
            'ya_metrika_pwapp'    => _w('Не заполнен пароль приложения'),
            'ya_metrika_token'    => _w('Не заполнен токен. Получите его'),
            'ya_market_name'      => _w('Не заполнено имя магазина'),
            'ya_market_price'     => _w('Не заполнена цена'),
            'ya_pokupki_atoken'   => _w('Не заполнен токен. Получите его'),
            'ya_pokupki_url'      => _w('Не заполнена ссылка'),
            'ya_pokupki_appid'    => _w('Не заполнен id приложения'),
            'ya_pokupki_pwapp'    => _w('Не заполнен пароль приложения'),
            'ya_pokupki_campaign' => _w('Не заполнен номер кампании'),
            'ya_pokupki_token'    => _w('Не заполнен токен. Получите его'),
            'ya_billing_id'       => _w('Не указан ID формы'),
            'ya_billing_purpose'  => _w('Не указано назначение платежа'),
            'ya_billing_status'   => _w('Не указан статус заказа'),
        );

        $this->formValidate($sm, $array_fields);

        $all_ok = _w('Все настройки верно заполнены!');
        $arr    = array('p2p', 'kassa', 'market', 'pokupki', 'metrika', 'yabilling');
        if (waRequest::request('mode') == 'metrika') {
            $ymetrika = new YaMetrika();
            $ymetrika->initData($data['ya_metrika_token'], $data['ya_metrika_number']);
            $ymetrika->client_id     = $data['ya_metrika_appid'];
            $ymetrika->client_secret = $data['ya_metrika_pwapp'];
            $ymetrika->processCounter();
            $this->errors['metrika'] = array_merge($this->errors['metrika'], $_SESSION['metrika_status']);
        }
        foreach ($arr as $a) {
            if (!isset($this->errors[$a]) || !count($this->errors[$a])) {
                $this->errors[$a][] = $this->success_alert($all_ok);
            }
        }


        return array('errors' => $this->errors);
    }

    public function errors_alert($text)
    {
        $html = '<div class="alert alert-danger">
                <i class="fa fa-exclamation-circle"></i> '.$text.'
            </div>';

        return $html;
    }

    public function success_alert($text)
    {
        $html = ' <div class="alert alert-success">
                    <i class="fa fa-check-circle"></i> '.$text.'
                    </div>';

        return $html;
    }

    public function info_alert($text)
    {
        $html = ' <div class="alert alert-info">
                     '.$text.'
                  </div>';

        return $html;
    }

    public static function settingsPaymentOptions($type)
    {
        $tp = array(
            \YaMoney\Model\PaymentMethodType::YANDEX_MONEY   => 'Яндекс.Деньги',
            \YaMoney\Model\PaymentMethodType::BANK_CARD      => 'Банковские карты — Visa, Mastercard и Maestro, «Мир»',
            \YaMoney\Model\PaymentMethodType::CASH           => 'Наличные',
            \YaMoney\Model\PaymentMethodType::MOBILE_BALANCE => 'Оплата со счета мобильного телефона',
            \YaMoney\Model\PaymentMethodType::WEBMONEY       => 'WebMoney',
            \YaMoney\Model\PaymentMethodType::SBERBANK       => 'Сбербанк Онлайн',
            \YaMoney\Model\PaymentMethodType::ALFABANK       => 'Альфа-Клик',
            \YaMoney\Model\PaymentMethodType::QIWI           => ' QIWI Wallet',
        );

        return isset($tp[$type]) ? $tp[$type] : $type;
    }

    public function yaOrderShip($data)
    {
        require_once dirname(__FILE__).'/../api/pokupki.php';
        $dbm      = new waModel();
        $order    = $dbm->query(
            "SELECT * FROM `shop_pokupki_orders` WHERE id_order = ".(int)$data['order_id']
        )->fetchRow();
        $order_id = isset($order[1]) ? $order[1] : 0;
        if ($order_id) {
            $pokupki = new YaPokupki();
            $pokupki->makeData();
            $status = $pokupki->sendOrder('DELIVERY', $order_id);
        }
    }

    public function yaOrderRefund($data)
    {
        return $this->yaOrderDel($data);
    }

    public function yaOrderDel($data)
    {
        require_once dirname(__FILE__).'/../api/pokupki.php';
        $dbm      = new waModel();
        $order    = $dbm->query(
            "SELECT * FROM `shop_pokupki_orders` WHERE id_order = ".(int)$data['order_id']
        )->fetchRow();
        $order_id = isset($order[1]) ? $order[1] : 0;
        if ($order_id) {
            $pokupki = new YaApiPokupki();
            $pokupki->makeData();
            $status = $pokupki->sendOrder('CANCELLED', $order_id);
        }
    }

    public function yaOrderProcess($data)
    {
        require_once dirname(__FILE__).'/../api/pokupki.php';
        $dbm      = new waModel();
        $order    = $dbm->query(
            "SELECT * FROM `shop_pokupki_orders` WHERE id_order = ".(int)$data['order_id']
        )->fetchRow();
        $order_id = isset($order[1]) ? $order[1] : 0;
        if ($order_id) {
            $pokupki = new YaPokupki();
            $pokupki->makeData();
            $status = $pokupki->sendOrder('PROCESSING', $order_id);
        }
    }

    public static function log_save($logtext)
    {
        $real_log_file = './ya_logs/pokupki_'.date('Y-m-d').'.log';
        $h             = fopen($real_log_file, 'ab');
        fwrite($h, date('Y-m-d H:i:s ').'['.addslashes($_SERVER['REMOTE_ADDR']).'] '.$logtext."\n");
        fclose($h);
    }

    private function extendItems($order, $items)
    {
        $order         = (object)$order;
        $product_model = new shopProductModel();
        $discount      = $order->discount;
        foreach ($items as & $item) {
            $data             = $product_model->getById($item['product_id']);
            $item['tax_id']   = ifset($data['tax_id']);
            $item['currency'] = $order->currency;
            if (!empty($item['total_discount'])) {
                $discount      -= $item['total_discount'];
                $item['total'] -= $item['total_discount'];
                $item['price'] -= $item['total_discount'] / $item['quantity'];
            }
        }

        unset($item);

        $discount_rate = $order->total ? ($order->discount / ($order->total + $order->discount - $order->tax - $order->shipping)) : 0;

        $taxes_params = array(
            'billing'       => $order->billing_address,
            'shipping'      => $order->shipping_address,
            'discount_rate' => $discount_rate,
        );
        shopTaxes::apply($items, $taxes_params, $order->currency);

        if ($discount) {
            $k = 1 - $discount_rate;

            foreach ($items as & $item) {
                if ($item['tax_included']) {
                    $item['tax'] = round($k * $item['tax'], 4);
                }

                $item['price'] = round($k * $item['price'], 4);
                $item['total'] = round($k * $item['total'], 4);
            }

            unset($item);
        }

        return $items;
    }

    public function frontendFoot()
    {
        if ($this->getSettings('ya_metrika_code') && $this->getSettings('ya_metrika_active')) {
            $html = '<script type="text/javascript" src="'.wa()->getAppStaticUrl().'plugins/yamodule_api/js/front.js"></script>';
            $html .= $this->getSettings('ya_metrika_code');

            return $html;
        }
    }

    public function frontendSucc()
    {
        $order_id = wa()->getStorage()->get('shop/order_id');
        if ($this->getSettings('ya_metrika_active') && $order_id) {
            $order_model       = new shopOrderModel();
            $currency_model    = new shopCurrencyModel();
            $order             = $order_model->getById($order_id);
            $currency          = $currency_model->getById($order['currency']);
            $order_items_model = new shopOrderItemsModel();
            $items             = $order_items_model->getByField('order_id', $order_id, true);

            $html = '<script type="text/javascript">
            $(document).ready(function(){
                    var yaParams_'.$order['id'].' = {
                        order_id: "'.$order['id'].'",
                        order_price: '.$order['total'].', 
                        currency: "'.($order['currency'] == 'RUB' ? 'RUR' : $order['currency']).'",
                        exchange_rate: '.$currency['rate'].',
                        goods:[';
            foreach ($items as $item) {
                $html .= '{id: '.$item['product_id'].', name: "'.$item['name'].'", price: '.$item['price'].', quantity: '.$item['quantity'].'},';
            }
            $html .= ']
                    };
                    
                    console.log(yaParams_'.$order['id'].');
                    metrikaReach("metrikaOrder", yaParams_'.$order['id'].');
            });
                    </script>';

            return $html;
        }
    }

    /**
     * @param $sm
     * @param $array_fields
     *
     * @return mixed
     */
    protected function formValidate($sm, $array_fields)
    {
        $this->errors  = array();
        $update_status = $this->sendStatistics();
        if ($update_status != false) {
            $this->errors['update'][] = '<div class="alert alert-danger">У вас неактуальная версия модуля. Вы можете <a target="_blank" href="https://github.com/yandex-money/yandex-money-cms-shopscript5/releases">загрузить и установить</a> новую ('.$update_status.')</div>';
        }

        $this->errors['metrika'] = array();
        $data                    = $sm->get('shop.yamodule_api');
        $keys                    = array_keys($array_fields);
        foreach ($keys as $key) {
            if (empty($data[$key])) {
                $d                     = explode('_', $key);
                $this->errors[$d[1]][] = $this->errors_alert($array_fields[$key]);
            }
        }

        if (!empty($data['ya_kassa_shopid']) && !preg_match('/^\d+$/i', $data['ya_kassa_shopid'])) {
            $this->errors['kassa'][] = $this->errors_alert(
                _w(
                    'Такого shopId нет. Пожалуйста, скопируйте параметр в <a
                                    href="https://money.yandex.ru/joinups">личном кабинете Яндекс.Кассы</a>  (наверху любой страницы)'
                )
            );
        }

        if (!empty($data['ya_kassa_pw']) && !preg_match('/^test_.*|live_.*$/i', $data['ya_kassa_pw'])) {
            $this->errors['kassa'][] = $this->errors_alert(
                _w(
                    'Такого секретного ключа нет. Если вы уверены, что скопировали ключ правильно, значит, он по какой-то причине не работает. Выпустите и активируйте ключ заново — <a
                                    href="https://money.yandex.ru/joinups">в личном кабинете Яндекс.Кассы</a>'
                )
            );
        }

        if ($this->isTestMode($data)) {
            $this->errors['kassa'][] = $this->info_alert(
                ' Вы включили тестовый режим приема платежей. Проверьте, как проходит оплата. <a
                    href="https://kassa.yandex.ru/">Подробнее</a>'
            );
        }
    }

    /**
     * @param $settings
     *
     * @return bool
     */
    protected function isTestMode($settings)
    {
        $shopPassword = $settings['ya_kassa_pw'];
        $prefix       = substr($shopPassword, 0, 4);

        return $prefix == "test";
    }

    /**
     * @param $transactionModel
     * @param $orderId
     *
     * @return mixed
     */
    protected function getTransactionByOrder($transactionModel, $orderId)
    {
        $transactions = $transactionModel->getByFields(array('order_id' => $orderId));
        if ($transactions) {
            $transactionData = array_shift($transactions);
        }

        return $transactionData;
    }

    public function kassaOrderReturn()
    {
        $config     = waConfig::getAll();
        $appsPath   = $config['wa_path_apps'];
        $pluginPath = $config['wa_path_plugins'];
        require_once $appsPath.DIRECTORY_SEPARATOR.'shop'.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'yamodule_api'.DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'orderReceiptModel.php';
        require_once $appsPath.DIRECTORY_SEPARATOR.'shop'.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'yamodule_api'.DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'orderRefundModel.php';
        require_once $pluginPath.DIRECTORY_SEPARATOR.'payment'.DIRECTORY_SEPARATOR.'yamodulepay_api'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

        $view    = wa()->getView();
        $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        $order_model        = new shopOrderModel();
        $transactionModel   = new waTransactionModel();
        $orderReceiptsModel = new orderReceiptModel();
        $orderRefundModel   = new orderRefundModel();
        $order              = $order_model->getById($orderId);
        $state              = $order['state_id'];
        $orderReceipt       = $orderReceiptsModel->getByOrderId((int)$order['id']);
        $transaction        = $this->getTransactionByOrder($transactionModel, (int)$order['id']);
        $paymentId          = $transaction['native_id'];
        $app                = new waAppSettingsModel();
        $settings           = $app->get('shop.yamodule_api');
        $shopId             = $settings['ya_kassa_shopid'];
        $shopPassword       = $settings['ya_kassa_pw'];
        $apiClient          = new YandexMoneyApi();
        $apiClient->setAuth($shopId, $shopPassword);
        $apiClient->setLogger(
            function ($level, $message, $context) {
                $this->debugLog($message);
            }
        );
        try {
            $result = $apiClient->getPaymentInfo($paymentId);
        } catch (Exception $e) {
            $this->debugLog($e->getMessage());
        }

        $paymentMethod      = $result->getPaymentMethod();
        $paymentMethodType  = $paymentMethod->getType();
        $paymentMethodTitle = $this->settingsPaymentOptions($paymentMethodType);

        $receipt = $orderReceipt['receipt'];
        if ($receipt) {
            $receiptData = json_decode($receipt, true);
            $items       = $receiptData['items'];
            $actualTotal = 0;

            foreach ($items as $item) {
                $actualTotal += $item['amount']['value'] * $item['quantity'];
            }
            if (empty($items)) {
                $errors[] = 'Нет товаров для отправки в Яндекс.Касса';
            }
        } else {
            $items     = array();
            $email     = '';
            $taxValues = array();
        }

        $refunds = $orderRefundModel->getByOrderId($orderId);
        $returnTotal = 0;
        if ($refunds) {
            foreach ($refunds as $refund) {
                $returnTotal += (float)$refund['amount'];
            }
        }
        $showReturnTab = !in_array($order['state_id'], array('new', 'processing'));
        $view->assign(
            array(
                'show_return_tab'     => $showReturnTab,
                'return_total'        => $returnTotal,
                'return_sum'          => $order['total'],
                'invoiceId'           => $paymentId,
                'return_items'        => $refunds,
                'payment_method'      => $paymentMethodTitle,
                'return_errors'       => $errors,
                'total'               => $order['total'],
                'id_order'            => $orderId,
                'test'                => 1,
                'pym'                 => $paymentId,
                'state'               => $state,
                'products'            => $items,
                'orderTotal'          => $order['total'],
                'taxTotal'            => $order['tax'],
                'ya_kassa_send_check' => 1,
            )
        );

        $html = '';

        $html['info_section'] = $view->fetch($this->path.'/templates/actions/settings/tabs_return.html');

        return $html;
    }

    private function debugLog($message)
    {
        $this->log('yamodulepayApi', $message);
    }

    private function log($module_id, $data)
    {
        static $id;
        if (empty($id)) {
            $id = uniqid();
        }
        $rec       = '#'.$id."\n";
        $module_id = strtolower($module_id);
        if (!preg_match('@^[a-z][a-z0-9]+$@', $module_id)) {
            $rec       .= 'Invalid module_id: '.$module_id."\n";
            $module_id = 'general';
        }
        $filename = 'payment/'.$module_id.'Payment.log';
        $rec      .= "data:\n";
        if (!is_string($data)) {
            $data = var_export($data, true);
        }
        $rec .= "$data\n";
        waLog::log($rec, $filename);
    }
}
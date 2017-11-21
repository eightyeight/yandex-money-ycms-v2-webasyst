<?php

class shopYamodule_apiPluginFrontendActions extends waActions
{
    public function clear_cart($code)
    {
        $model = new shopCartItemsModel();
        $model->deleteByField('code', $code);
        wa()->getStorage()->remove('shop/cart');
    }

    public function cartinfoAction()
    {
        $data = waRequest::post();
        $sku_model = new shopProductSkusModel();
        $product_model = new shopProductModel();
        if (!isset($data['product_id']) && isset($data['sku_id'])) {
            $sku = $sku_model->getById($data['sku_id']);
            $product = $product_model->getById($sku['product_id']);
        } else {
            $product = $product_model->getById($data['product_id']);
            if (isset($data['sku_id'])) {
                $sku = $sku_model->getById($data['sku_id']);
            } else {
                if (isset($data['features'])) {
                    $product_features_model = new shopProductFeaturesModel();
                    $sku_id = $product_features_model->getSkuByFeatures($product['id'], $data['features']);
                    if ($sku_id) {
                        $sku = $sku_model->getById($sku_id);
                    } else {
                        $sku = null;
                    }
                } else {
                    $sku = $sku_model->getById($product['sku_id']);
                    if (!$sku['available']) {
                        $sku = $sku_model->getByField(array('product_id' => $product['id'], 'available' => 1));
                    }
                }
            }
        }

        $quantity = waRequest::post('quantity', 1);
        $name = $product['name'].($sku['name'] ? ' ('.$sku['name'].')' : '');
        $array = array(
            'data' => date('Y-m-d H:i:s'),
            'name' => $name,
            'price' => ($sku['price'] ? $sku['price'] : $product['price']),
            'action' => 'add',
            'quantity' => $quantity
        );

        exit(json_encode($array));
    }

    public function pcartAction()
    {
        $this->getResponse()->addHeader('Content-type', 'application/json; charset=utf-8');
        $this->getResponse()->sendHeaders();
        $sm = new waAppSettingsModel();
        $settings = $sm->get('shop.yamodule_api');
        $sign = $settings['ya_pokupki_atoken'];
        $key = waRequest::request('auth-token');
        if (strtoupper($sign) != strtoupper($key))
        {
            header('HTTP/1.1 403 Forbidden');
            header('Status: 403 Forbidden');
            echo 'Wrong token';
            exit;
        }
        else
        {
            $json = file_get_contents("php://input");
            if (!$json)
            {
                header('HTTP/1.1 403 Forbidden');
                header('Status: 403 Forbidden');
                echo 'No data posted';
                exit;
            }
            else
            {
                $data = json_decode($json);
                $payments = array();
                $carriers = array();
                $items = array();
                $sku_model = new shopProductSkusModel();
                $product_model = new shopProductModel();
                $code = waRequest::cookie('shop_cart');
                if (!$code) {
                    $code = md5(uniqid(time(), true));
                    wa()->getResponse()->setCookie('shop_cart', $code, time() + 30 * 86400, null, '', false, true);
                }
                else
                    $this->clear_cart($code);

                $cart = new shopCart($code);
                foreach ($data->cart->items as $item)
                {
                    $add = true;
                    $id_array = explode('c', $item->offerId);
                    $id_product = $id_array[0];
                    if (count($id_array) > 1 && isset($id_array[1]))
                    {
                        $sku = $sku_model->getSku($id_array[1]);
                        if (!empty($sku) && $sku['available'] && !empty($sku['count']) && $sku['count'] <= $item->count && $sku['count'] <= 0)
                        {
                            $add = false;
                            continue;
                        }
                        else
                        {
                            $total = $sku['price'];
                            $shop_count = !empty($sku['count']) ? $sku['count'] : 99999;
                        }
                    }
                    else
                    {
                        $product = $product_model->getById($id_product);
                        $total = $product['price'];
                        $shop_count = !empty($product['count']) ? $product['count'] : 99999;
                    }

                    if ($add)
                    {
                        $id_sku = isset($sku['id']) ? $sku['id'] : '';
                        $count = min($shop_count, (int)$item->count);
                        $items[] = array(
                            'feedId' => $item->feedId,
                            'offerId' => $item->offerId,
                            'price' => (float)$total,
                            'count' => (int)$count,
                            'delivery' => true,
                        );

                        $data_cart = array(
                            'create_datetime' => date('Y-m-d H:i:s'),
                            'product_id' => $id_product,
                            'sku_id' => $id_sku,
                            'quantity' => $count,
                            'type' => 'product'
                        );

                        $cart->addItem($data_cart);
                    }
                }

                if (count($items))
                {
                    $plugin_model = new shopPluginModel();
                    $shiping = new shopCheckoutShipping();
                    $methods = $plugin_model->listPlugins('shipping');
                    $rate = $shiping->getItems();

                    if (count($methods))
                    {
                        $k = 0;
                        $settings['ya_pokupki_carrier'] = unserialize($settings['ya_pokupki_carrier']);
                        $settings['ya_pokupki_rate'] = unserialize($settings['ya_pokupki_rate']);
                        foreach ($methods as $result)
                        {
                            if (!$result['status'])
                                continue;

                            $type = isset($settings['ya_pokupki_carrier'][$result['id']]) ? $settings['ya_pokupki_carrier'][$result['id']] : 'POST';
                            $rate = isset($settings['ya_pokupki_rate'][$result['id']]) ? $settings['ya_pokupki_rate'][$result['id']] : 0;

                            $carriers[$k] = array(
                                'id' => $result['id'],
                                'serviceName' => $result['name'],
                                'type' => $type,
                                'price' => (int)$rate,
                                'dates' => array(
                                    'fromDate' => date('d-m-Y'),
                                    'toDate' => date('d-m-Y'),
                                ),
                            );

                            if($type == 'PICKUP')
                            {
                                require_once dirname(__FILE__).'/../../../api/pokupki.php';
                                $pclass = new YaPokupki();
                                $pclass->app_id = $settings['ya_pokupki_appid'];
                                $pclass->url = $settings['ya_pokupki_url'];
                                $pclass->number = $settings['ya_pokupki_campaign'];
                                $pclass->login = $settings['ya_pokupki_login'];
                                $pclass->app_pw = $settings['ya_pokupki_pwapp'];
                                $pclass->ya_token = $settings['ya_pokupki_token'];
                                $outlets = $pclass->getOutlets();
                                $carriers[$k] = array_merge($carriers[$k], $outlets['json']);
                            }

                            $k++;
                        }
                    }

                    if ($settings['ya_pokupki_yandex'])
                    $payments[] = 'YANDEX';

                    if ($settings['ya_pokupki_sprepaid'])
                        $payments[] = 'SHOP_PREPAID';

                    if ($settings['ya_pokupki_cash'])
                        $payments[] = 'CASH_ON_DELIVERY';

                    if ($settings['ya_pokupki_card'])
                        $payments[] = 'CARD_ON_DELIVERY';
                }

                $array = array(
                    'cart' => array(
                        'items' => $items,
                        'deliveryOptions' => $carriers,
                        'paymentMethods' => $payments
                    )
                );

                $this->clear_cart($code);
                exit(json_encode($array));
            }
        }
    }

    public function porderAction()
    {
        $this->getResponse()->addHeader('Content-type', 'application/json; charset=utf-8');
        $this->getResponse()->sendHeaders();
        $type = waRequest::param('type');
        if ($type == 'status' || $type == 'accept')
        {
            $this->getResponse()->addHeader('Content-type', 'application/json; charset=utf-8');
            $this->getResponse()->sendHeaders();
            $sm = new waAppSettingsModel();
            $settings = $sm->get('shop.yamodule_api');
            $sign = $settings['ya_pokupki_atoken'];
            $key = waRequest::request('auth-token');
            if (strtoupper($sign) != strtoupper($key))
            {
                header('HTTP/1.1 403 Forbidden');
                header('Status: 403 Forbidden');
                echo 'Wrong token';
                exit;
            }
            else
            {
                $json = file_get_contents("php://input");
                $this->log_save($json);
                if (!$json)
                {
                    header('HTTP/1.1 403 Forbidden');
                    header('Status: 403 Forbidden');
                    echo 'No data posted';
                    exit;
                }
                else
                {
                    $data = json_decode($json);

                    require_once dirname(__FILE__).'/../../../api/shopPokupki.model.php';
                    $model = new shopPokupkiModel();

                    if ($type == 'status')
                    {
                        $order = $model->getById((int)$data->order->id);
                        $order_id = $order[0];

                        $workflow = new shopWorkflow();
                        switch ($data->order->status)
                        {
                            case 'CANCELLED':
                                $substatus = $data->order->substatus;
                                $data_order = $workflow->getActionById('delete')->run($order_id);
                                break;
                            case 'PROCESSING':
                                $buyer = isset($data->order->buyer) ? $data->order->buyer : '';
                                $contact = new waContactEmailsModel();
                                $customer = new shopCustomerModel();
                                $user = new waContact();
                                $ord = new shopOrderModel();
                                $contact_id = $contact->getContactIdByEmail($buyer->email);
                                if ($contact_id)
                                {
                                    $data_order = $workflow->getActionById('pay')->run($order_id);
                                    $customer->updateFromNewOrder($contact_id, $order_id);
                                    $ord->updateById($order_id, array('contact_id' => $contact_id));
                                }
                                else
                                {
                                    $user['firstname'] = $buyer->firstName;
                                    $user['lastname'] = $buyer->lastName;
                                    $user['email'] = $buyer->email;
                                    $user['create_datetime'] = date('Y-m-d H:i:s');
                                    $user['create_app_id'] = 'shop';
                                    $user['password'] = base64_decode($buyer->id);
                                    $errors_c = $user->save();
                                    $contact_id = $user->getId();
                                    if (!$errors_c)
                                    {
                                        $data_order = $workflow->getActionById('pay')->run($order_id);
                                        $customer->updateFromNewOrder($contact_id, $order_id);
                                        $ord->updateById($order_id, array('contact_id' => $contact_id));
                                    }
                                    else
                                        $this->log_save('Ошибка добавления пользователя '.serialize($buyer));
                                }
                                break;
                            case 'UNPAID':
                                $data_order = $workflow->getActionById('process')->run($order_id);
                                break;
                        }

                        exit();
                    }

                    if ($type == 'accept')
                    {
                        $code = waRequest::cookie('shop_cart');
                        if (!$code) {
                            $code = md5(uniqid(time(), true));
                            wa()->getResponse()->setCookie('shop_cart', $code, time() + 30 * 86400, null, '', false, true);
                        }
                        else
                            $this->clear_cart($code);

                        $cart = new shopCart($code);
                        foreach ($data->order->items as $item)
                        {
                            $id_array = explode('c', $item->offerId);
                            $id_product = $id_array[0];
                            $id_sku = isset($id_array[1]) ? $id_array[1] : false;
                            if (!$id_sku)
                            {
                                $sku_model = new shopProductSkusModel();
                                $array_sku = $sku_model->getDataByProductId($id_product);
                                $data_sku = array_shift($array_sku);
                                $id_sku = $data_sku['id'];
                            }
                            $data_cart = array(
                                'create_datetime' => date('Y-m-d H:i:s'),
                                'product_id' => $id_product,
                                'sku_id' => $id_sku,
                                'quantity' => (int)$item->count,
                                'type' => 'product'
                            );

                            $cart->addItem($data_cart);
                        }

                        $message = isset($data->order->notes) ? $data->order->notes : null;
                        $delivery = isset($data->order->delivery->address) ? $data->order->delivery->address : new stdClass();
                        $street = isset($delivery->street) ? ' Улица: '.$delivery->street : 'Самовывоз';
                        $subway = isset($delivery->subway) ? ' Метро: '.$delivery->subway : '';
                        $block = isset($delivery->block) ? ' Корпус/Строение: '.$delivery->block : '';
                        $floor = isset($delivery->floor) ? ' Этаж: '.$delivery->floor : '';
                        $house = isset($delivery->house) ? ' Дом: '.$delivery->house : '';
                        $city = isset($delivery->city) ? $delivery->city : '';
                        $postcode = isset($delivery->postcode) ? $delivery->postcode : '';
                        $address1 = $street.$subway.$block.$floor.$house;

                        $items = $cart->items(false);

                        $order = array();
                        foreach ($items as &$item) {
                            unset($item['id']);
                            unset($item['parent_id']);
                        }

                        unset($item);
                        $order = array(
                            'contact' => $settings['ya_plugin_contact'],
                            'items'   => $items,
                            'total'   => $cart->total(false)
                        );

                        $order['discount'] = shopDiscounts::apply($order);
                        $order['params']['ip'] = waRequest::getIp();
                        $order['params']['user_agent'] = waRequest::getUserAgent();
                        $order['comment'] = $message;
                        $routing_url = wa()->getRouting()->getRootUrl();
                        $order['params']['storefront'] = wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : '');

                        if ($data->order->delivery->id)
                        {
                             $order['params']['shipping_id'] = $data->order->delivery->id;
                             $order['params']['shipping_plugin'] = $data->order->delivery->serviceName;
                             $order['params']['shipping_name'] = $data->order->delivery->serviceName;
                             $order['params']['shipping_rate_id'] = 'delivery';
                             $order['shipping'] = $data->order->delivery->price;
                        }
                        else
                            $order['shipping'] = 0;

                        $order['params']['shipping_address.country'] = 'rus';
                        $order['params']['shipping_address.city'] = $city;
                        $order['params']['shipping_address.street'] = $address1;
                        $order['params']['shipping_address.zip'] = $postcode;
                        $checkout_data = $this->getStorage()->get('shop/checkout');

                        $workflow = new shopWorkflow();
                        if($order_id = $workflow->getActionById('create')->run($order))
                        {
                            // if ($data->order->status == 'RESERVED')
                            // {
                                // $data_order = $workflow->getActionById('process')->run($order_id);
                            // }

                            $vti = array(
                                'id_order' => (int)$order_id,
                                'id_market_order' => (int)$data->order->id,
                                'ptype' => $data->order->paymentType,
                                'pmethod' => $data->order->paymentMethod,
                                'home' => isset($data->order->delivery->address->house) ? $data->order->delivery->address->house : 0,
                                'outlet' => isset($data->order->delivery->outlet->id) ? $data->order->delivery->outlet->id : '',
                                'currency' => $data->order->currency
                            );

                            $adddb = $model->add($vti);
                            $array = array(
                                'order' => array(
                                    'accepted' => true,
                                    'id' => (string)$order_id,
                                )
                            );
                        }
                        else
                        {
                            $array = array(
                                'order' => array(
                                    'accepted' => false,
                                    'reason' => 'OUT_OF_DATE'
                                )
                            );
                        }

                        exit(json_encode($array));
                    }
                }
            }
        }
    }

    public function marketAction()
    {
        require_once dirname(__FILE__).'/../../../api/market.php';
        $plugin = wa()->getPlugin('yamodule_api');
        $sm = new waAppSettingsModel();
        $settings = $sm->get('shop.yamodule_api');
        $settings['ya_market_categories'] = unserialize($settings['ya_market_categories']);
        $market = new YaMarket();
        $market->from_charset = 'utf-8';
        $market->homeprice = $settings['ya_market_price'];
        $market->simple = $settings['ya_market_simpleyml'];

        //---------------Main settings------------------------//
        $this->getResponse()->addHeader('Content-type', 'application/xml; charset=windows-1251');
        $this->getResponse()->sendHeaders();
        $config = wa('shop')->getConfig();
        $size = $config->getImageSize('big');
        $def_currency = $config->getCurrency();
        $url = preg_replace('@^https@', 'http', wa()->getRouteUrl('shop/frontend', array(), true));
        $version = wa()->getVersion('shop');
        $phone = $config->getGeneralSettings('phone');
        $root = trim(wa()->getRootUrl(true), '/');
        $market->set_shop($settings['ya_market_name'], $config->getGeneralSettings('name'), $url, $version, $phone);

        //---------------Currencies------------------------//
        $price_currency = isset($settings['ya_market_currency']) ? $settings['ya_market_currency'] : 'RUB';
        $allowed = array('RUR', 'RUB', 'UAH', 'USD', 'BYR', 'KZT', 'EUR');
        $currency_model = new shopCurrencyModel();
        if ($settings['ya_market_currencies'])
        {
            $currencies = $currency_model->getCurrencies();
            foreach ($currencies as $currency)
                if (in_array($currency['code'], $allowed))
                    $market->add_currency($currency['code'], $currency['rate']);
        }
        else
        {
            $currency = $currency_model->getById($def_currency);
            if (in_array($currency['code'], $allowed))
                $market->add_currency($currency['code'], $currency['rate']);
        }

        //-----------------Categories----------------------//
        $cat_model = new shopCategoryModel();
        $categories = $cat_model->getFullTree();
        foreach ($categories as $category)
        {
            if ($category['type'] == shopCategoryModel::TYPE_STATIC)
                $market->add_category($category['name'], $category['id'], $category['parent_id']);
        }

        //------------------Products----------------------//
        $product_model = new shopProductModel();
        $product_images_model = new shopProductImagesModel();
        $collection = new shopProductsCollection('', array( 'frontend' => true));
        $products = $collection->getProducts();
        if ($settings['ya_market_comb'])
        {
            $sku_model = new shopProductSkusModel();
            $skus = $sku_model->getDataByProductId(array_keys($products));
            foreach ($skus as $sku_id => $sku) {
                if (isset($products[$sku['product_id']]))
                {
                    if (!isset($products[$sku['product_id']]['skus']))
                        $products[$sku['product_id']]['skus'] = array();
                    $products[$sku['product_id']]['skus'][$sku_id] = $sku;
                }
            }
        }

        $data = array();
        foreach ($products as $product)
        {
            if (!$settings['ya_market_selected'])
            {
                if (!in_array($product['category_id'], $settings['ya_market_categories']))
                    continue;
            }

            if ($product['price'] >= 0.5 &&	$product['category_id'])
            {
                if ($settings['ya_market_available'] && (!$product['status']))
                    continue;

                $available = false;
                if ($settings['ya_market_set_available'] == 1)
                    $available = true;
                elseif ($settings['ya_market_set_available'] == 2)
                {
                    if ($product['count'] > 0 && !is_null($product['count']))
                        $available = true;
                }
                elseif ($settings['ya_market_set_available'] == 3)
                {
                    $available = true;
                    if ($product['count'] == 0 && !is_null($product['count']))
                        return;
                }
                elseif ($settings['ya_market_set_available'] == 4)
                    $available = false;

                $data = array();
                $data['id'] = $product['id'];
                $data['url'] = $root.$product['frontend_url'];
                $data['price'] = number_format(shop_currency($product['price'], $product['currency'], $price_currency, false), 2, '.', '');
                $data['description'] = $product['description'] ? $product['description'] : $product['summary'];
                $data['categoryId'] = $product['category_id'];
                $data['delivery'] = $settings['ya_market_delivery'];
                $data['pickup'] = $settings['ya_market_pickup'];
                $data['store'] = $settings['ya_market_store'];
                $data['currencyId'] = $def_currency;
                $data['picture'] = array();
                $data['param'] = array();
                $images = $product_images_model->getImages($product['id'], $size, 'id', false);
                foreach($images as $image)
                    $data['picture'][] = $root.shopImage::getUrl($image, $size);

                if ($settings['ya_market_simpleyml'])
                {
                    $data['name'] = $product['name'];
                    $market->add_offer($product['id'], $data, $available);
                }
                else
                {
                    $data['model'] = $product['name'];
                    // $data['vendor'] = $product['name'];
                    if ($settings['ya_market_comb'] && count($product['skus']) > 1)
                    {
                        foreach ($product['skus'] as $sku)
                        {
                            $available_sku = false;
                            if ($settings['ya_market_set_available'] == 1)
                                $available_sku = true;
                            elseif ($settings['ya_market_set_available'] == 2)
                            {
                                if ($sku['count'] > 0 || is_null($sku['count']))
                                    $available_sku = true;
                            }
                            elseif ($settings['ya_market_set_available'] == 3)
                            {
                                $available_sku = true;
                                if ($sku['count'] == 0 && !is_null($sku['count']))
                                    continue;
                            }
                            elseif ($settings['ya_market_set_available'] == 4)
                                $available_sku = false;

                            $sku_data = array();
                            $sku_data = $data;
                            $sku_data['id'] .= 'c'.$sku['id'];
                            $sku_data['model'] .= ' '.$sku['name'];
                            $sku_data['price'] = number_format(shop_currency($sku['price'], $product['currency'], $price_currency, false), 2, '.', '');
                            $sku_data['url'] .= '#'.$sku['id'];
                            $param = $this->get_param($product['id'], $sku['id'], $settings['ya_market_vendor'], $settings['ya_market_fea']);
                            $sku_data['param'] = $param['param'];
                            $sku_data['vendor'] = $param['vendor'];
                            $sku_data['group_id'] = $product['id'];

                            $market->add_offer($sku_data['id'], $sku_data, $available_sku);
                        }
                    }
                    else
                    {
                        if ($settings['ya_market_fea'])
                        {
                            $sku_one = array_shift($product['skus']);
                            $param = $this->get_param($product['id'], $sku_one['id'], $settings['ya_market_vendor'], $settings['ya_market_fea']);
                            $data['param'] = $param['param'];
                            $data['vendor'] = $param['vendor'];
                        }

                        $market->add_offer($product['id'], $data, $available);
                    }
                }
            }
        }

        exit($market->get_xml());
    }

    public static function get_param($id_product, $sku_id, $code)
    {
        $pfeatures_model = new shopProductFeaturesModel();
        $feature_model = new shopFeatureModel();

        $param = array();
        $vendor = '-';
        $sku_features = $pfeatures_model->getValues($id_product, $sku_id);
        if (isset($sku_features[$code]))
        {
            $fd = $feature_model->getByCode($code);
            if (is_object($sku_features[$code]))
                $vendor = $sku_features[$code]->value;
            else
                $vendor = $sku_features[$code];
        }

        if (count($sku_features))
        {
            foreach ($sku_features as $k => $sf)
            {
                $f_data = $feature_model->getByCode($k);
                $pname = $f_data['name'];

                if (is_object($sf))
                    $pvalue = $sf->value;
                else
                    $pvalue = $sf;

                $param[] = array(
                    'name' => $pname,
                    'value' => $pvalue
                );
            }
        }

        return array('param' => $param, 'vendor' => $vendor);
    }

    public static function log_save($logtext)
    {
        $real_log_file = './ya_logs/pokupki_'.date('Y-m-d').'.log';
        $h = fopen($real_log_file , 'ab');
        fwrite($h, date('Y-m-d H:i:s ') . '[' . addslashes($_SERVER['REMOTE_ADDR']) . '] ' . $logtext . "\n");
        fclose($h);
    }
}
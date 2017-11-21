<?php

Class Yamarket
{
    var $from_charset = 'windows-1251';
    var $shop = array('name' => '', 'company' => '', 'url' => '', 'platform' => 'ya_shopscript5', 'version' => '', 'phone' => '');
    var $currencies = array();
    var $categories = array();
    var $offers = array();
    var $simple = false;
    public $homeprice;

    function yml($from_charset = 'windows-1251')
    {
        $this->from_charset = trim(strtolower($from_charset));
    }


    function convert_array_to_tag($arr)
    {
        $s = '';
        foreach($arr as $tag => $val)
        {
            if($tag == 'weight' && (int)$val == 0)
                continue;

            if($tag == 'picture')
            {
                foreach ($val as $v){
                    $s .= '<'.$tag.'>'.$v.'</'.$tag.'>';
                    $s .= "\r\n";
                }
            }
            elseif($tag == 'param')
            {
                foreach ($val as $v){
                    $s .= '<param name="'.$this->prepare_field($v['name']).'">'.$this->prepare_field($v['value']).'</param>';
                    $s .= "\r\n";
                }
            }
            else
            {
                $s .= '<'.$tag.'>'.$val.'</'.$tag.'>';
                $s .= "\r\n";
            }
        }

        return $s;
    }

    function convert_array_to_attr($arr, $tagname, $tagvalue = '')
    {
        $s = '<'.$tagname.' ';
        foreach($arr as $attrname=>$attrval)
            $s .= $attrname . '="'.$attrval.'" ';

        $s .= ($tagvalue!='') ? '>'.$tagvalue.'</'.$tagname.'>' : '/>';
        $s .= "\r\n";
        return $s;
    }

    function prepare_field($s)
    {
        $from = array('"', '&', '>', '<', '\'');
        $to = array('&quot;', '&amp;', '&gt;', '&lt;', '&apos;');
        $s = str_replace($from, $to, $s);
        $s=preg_replace('!<[^>]*?>!', ' ', $s);
        if ($this->from_charset!='windows-1251') $s = iconv($this->from_charset, 'windows-1251', $s);
        $s = preg_replace('#[\x00-\x08\x0B-\x0C\x0E-\x1F]+#is', ' ', $s);
        return trim($s);
    }

    function set_shop($name, $company, $url, $phone, $version)
    {
        $this->shop['name'] = $this->prepare_field($name);
        $this->shop['name'] = substr($this->shop['name'], 0, 20);
        $this->shop['company'] = $this->prepare_field($company);
        $this->shop['url'] = $this->prepare_field($url);
        $this->shop['phone'] = $this->prepare_field($phone);
        $this->shop['version'] = $this->prepare_field($version);
    }

    function add_currency($id, $rate = 'CBRF', $plus = 0)
    {
        $rate = strtoupper($rate);
        $plus = str_replace(',', '.', $plus);
        if ($rate=='CBRF' && $plus>0)
            $this->currencies[] = array('id'=>$this->prepare_field(strtoupper($id)), 'rate'=>'CBRF', 'plus'=>(float)$plus);
        else
        {
            $rate = str_replace(',', '.', $rate);
            $this->currencies[] = array('id'=>$this->prepare_field(strtoupper($id)), 'rate'=>(float)$rate);
        }
        return true;
    }

    function add_category($name, $id, $parent_id = -1)
    {
        if ((int)$id<1||trim($name)=='') return false;
        if ((int)$parent_id>0)
            $this->categories[] = array('id'=>(int)$id, 'parentId'=>(int)$parent_id, 'name'=>$this->prepare_field($name));
        else
            $this->categories[] = array('id'=>(int)$id, 'name'=>$this->prepare_field($name));
        return true;
    }

    function add_offer($id, $data, $available = true)
    {
        $allowed = array('url', 'price', 'currencyId', 'categoryId', 'picture', 'store', 'pickup', 'delivery',
            'name', 'vendor', 'vendorCode', 'model', 'group_id', 'description', 'sales_notes', 'downloadable', 'weight',
            'dimensions', 'param', 'sales_notes', 'country_of_origin');
        $param = array();
        $data['model'] = $data['id'].'_tovar';
        // $data['vendor'] = '-';
        if(isset($data['param']))
            $param = $data['param'];
        foreach($data as $k => $v)
        {
            if (!in_array($k, $allowed)) unset($data[$k]);
            if($k != 'picture' && $k != 'param' && $k != 'rec' )
                $data[$k] = strip_tags($this->prepare_field($v));
        }
        $tmp = $data;
        $data = array();
        foreach($allowed as $key)
            if (isset($tmp[$key]) && !empty($tmp[$key]))
                $data[$key] = $tmp[$key]; # ������� ����� ��� �.�������!!!

        $out = array('id' => $id, 'data' => $data, 'available' => ($available) ? 'true' : 'false');
        if(!$this->simple)
            $out['type'] = 'vendor.model';
        $this->offers[] = $out;
    }

    function get_xml_header()
    {
        return '<?xml version="1.0" encoding="windows-1251"?><!DOCTYPE yml_catalog SYSTEM "shops.dtd"><yml_catalog date="'.date('Y-m-d H:i').'">';
    }

    function get_xml_shop()
    {
        $s = '<shop>' . "\r\n";
        $s .= $this->convert_array_to_tag($this->shop);
        $s .= '<currencies>' . "\r\n";
        foreach($this->currencies as $currency)
            $s .= $this->convert_array_to_attr($currency, 'currency');

        $s .= '</currencies>' . "\r\n";
        $s .= '<categories>' . "\r\n";
        foreach($this->categories as $category)
        {
            $category_name = $category['name'];
            unset($category['name']);
            $s .= $this->convert_array_to_attr($category, 'category', $category_name);
        }
        $s .= '</categories>' . "\r\n";
        if($this->homeprice)
            $s .= '<local_delivery_cost>'.$this->homeprice.'</local_delivery_cost>' . "\r\n";

        $s .= '<offers>' . "\r\n";
        foreach($this->offers as $offer)
        {
            $data = $offer['data'];
            unset($offer['data']);
            $s .= $this->convert_array_to_attr($offer, 'offer', $this->convert_array_to_tag($data));
        }
        $s .= '</offers>' . "\r\n";
        $s .= '</shop>';
        return $s;
    }

    function get_xml_footer()
    {
        return '</yml_catalog>';
    }

    function get_xml()
    {
        $xml = $this->get_xml_header();
        $xml .= $this->get_xml_shop();
        $xml .= $this->get_xml_footer();
        return $xml;
    }
}
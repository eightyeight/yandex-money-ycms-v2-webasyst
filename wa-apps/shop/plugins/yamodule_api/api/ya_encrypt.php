<?php

class YA_Encrypt {

    private $key = '';
    private $cipher = MCRYPT_RIJNDAEL_256;
    private $cipher_mode = MCRYPT_MODE_CBC;

    public function __construct()
    {
        if(!function_exists('mcrypt_encrypt'))
        {
            throw new Exception('mcrypt library not installed.');
        }
    }

    public function setKey($key)
    {
        $this->key = hash('sha256', $key, TRUE);;
    }

    public function encrypt($data)
    {
        $data = serialize($data);
        $init_size = mcrypt_get_iv_size($this->cipher, $this->cipher_mode);
        $init_vect = mcrypt_create_iv($init_size, MCRYPT_RAND);
        $str = $this->randomString(strlen($this->key)).$init_vect.mcrypt_encrypt($this->cipher, $this->key, $data, $this->cipher_mode, $init_vect);
        return base64_encode($str);
    }

    public function decrypt($data)
    {
        $data = base64_decode($data);
        $data = substr($data, strlen($this->key));
        $init_size = mcrypt_get_iv_size($this->cipher, $this->cipher_mode);
        $init_vect = substr($data, 0, $init_size);
        $data = substr($data, $init_size);
        $str = mcrypt_decrypt($this->cipher, $this->key, $data, $this->cipher_mode, $init_vect);
        return unserialize($str);
    }

    private function randomString($len)
    {
        $str = '';
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $pool_len = strlen($pool);
        for ($i = 0; $i < $len; $i++) {
            $str .= substr($pool, mt_rand(0, $pool_len - 1), 1);
        }
        return $str;
    }
}
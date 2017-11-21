<?php

class shopPokupkiModel extends waModel
{
    protected $table = 'shop_pokupki_orders';
    protected $id = 'id_order';

    public function getAll($key = null, $normalize = false)
    {
        $data = $this->query("SELECT * FROM `{$this->table}` ORDER BY `sort`")->fetchAll($key, $normalize);
        
        return $data;
    }

    public function getById($id)
    {
        $data = $this->query("SELECT * FROM `{$this->table}` WHERE id_market_order = ".(int)$id)->fetchRow();
        
        return $data;
    }

    public function add($data)
    {
        $result = $this->insert($data);
        return $result;
    }
}
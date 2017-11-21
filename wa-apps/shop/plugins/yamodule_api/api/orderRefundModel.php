<?php


class orderRefundModel extends waModel
{
    protected $table = 'shop_ym_order_refund';
    protected $id = 'id';

    public function getAll($key = null, $normalize = false)
    {
        $data = $this->query("SELECT * FROM `{$this->table}` ORDER BY `sort`")->fetchAll($key, $normalize);

        return $data;
    }

    public function getById($id)
    {
        $data = $this->query("SELECT * FROM `{$this->table}` WHERE id = ".(int)$id)->fetchRow();

        return $data;
    }

    public function getByOrderId($id, $key = null, $normalize = false)
    {
        $data = $this->query("SELECT * FROM `{$this->table}` WHERE order_id = ".(int)$id)->fetchAll($key, $normalize);

        return $data;
    }

    public function add($data)
    {
        $result = $this->insert($data);

        return $result;
    }

    public function updateReceipt($refundId, $receipt)
    {
        $data = $this->query(
            "UPDATE `{$this->table}` SET receipt =".$receipt." WHERE order_id = ".(int)$refundId
        )->fetchAssoc();

        return $data;
    }
}
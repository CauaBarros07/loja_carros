<?php

use Adianti\Database\TRecord;


class Venda extends TRecord
{
    const TABLENAME = 'sales';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial';

    public function __construct($id = NULL)
    {
        parent::__construct($id);
        parent::addAttribute('car_id');
        parent::addAttribute('sale_date');
        parent::addAttribute('sale_value');
    }


    public function get_carro()
    {
        if (!empty($this->car_id)) {
            return Carro::find($this->car_id);
        }
        return null;
    }
}
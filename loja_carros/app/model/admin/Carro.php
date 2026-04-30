<?php

use Adianti\Database\TRecord;


class Carro extends TRecord
{
    const TABLENAME = 'cars'; 
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial'; 

    public function __construct($id = NULL)
    {
        parent::__construct($id);
        parent::addAttribute('brand');
        parent::addAttribute('model');
        parent::addAttribute('year');
        parent::addAttribute('price');
        parent::addAttribute('status');
        parent::addAttribute('image');
    }

  
    public function get_venda()
    {
        return Venda::where('car_id', '=', $this->id)->first();
    }
}

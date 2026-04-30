<?php

use Adianti\Database\TRecord;

class Marca extends TRecord
{
    const TABLENAME  = 'brands'; 
    const PRIMARYKEY = 'id';     
    const IDPOLICY   = 'serial'; 

    public function __construct($id = NULL)
    {
        parent::__construct($id);
        parent::addAttribute('name'); 
    }
}
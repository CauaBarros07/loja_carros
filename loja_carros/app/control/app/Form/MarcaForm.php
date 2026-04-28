<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Widget\Form\TEntry;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;

class MarcaForm extends TPage
{
    protected $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_marca');
        $this->form->setFormTitle('Cadastrar Nova Marca');

        $name = new TEntry('name');
        $name->setSize('100%');

        $this->form->addFields(['Nome da Marca'], [$name]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        
        parent::add($this->form);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample'); // Nome da sua conexão
            $data = $this->form->getData();
            
            $brand = new Marca;
            $brand->fromArray((array) $data);
            $brand->store();

            TTransaction::close();
            new TMessage('info', 'Marca cadastrada com sucesso!');
            $this->form->clear();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
    // No BrandForm.php
public function onEdit($param)
{
    try {
        if (isset($param['id'])) {
            TTransaction::open('sample');
            $brand = new Marca($param['id']);
            $this->form->setData($brand);
            TTransaction::close();
        }
    } catch (Exception $e) {
        new TMessage('error', $e->getMessage());
    }
}
}
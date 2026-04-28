<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Validator\TRequiredValidator;
use Adianti\Validator\TEmailValidator;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Container\TVBox;
use Adianti\Database\TTransaction;

class ClienteForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_cliente');
        $this->form->setFormTitle('Cadastro de Cliente');

        
        $id       = new TEntry('id');
        $nome     = new TEntry('nome');
        $cpf      = new TEntry('cpf');
        $email    = new TEntry('email');
        $telefone = new TEntry('telefone');

        
        $id->setEditable(false);
        $id->setSize('100%');
        $nome->setSize('100%');
        $cpf->setSize('100%');
        $email->setSize('100%');
        $telefone->setSize('100%');

        
        $nome->addValidation('Nome', new TRequiredValidator);
        $email->addValidation('Email', new TEmailValidator);

        
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Nome')], [$nome]);
        $this->form->addFields([new TLabel('CPF')], [$cpf], [new TLabel('Email')], [$email]);
        $this->form->addFields([new TLabel('Telefone')], [$telefone]);

        // Botões
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addActionLink('Voltar', new TAction(['VendaList', 'onReload']), 'fa:arrow-left red');

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($this->form);
        parent::add($vbox);
    }

    public function onSave()
    {
        try {
            TTransaction::open('sample');
            $data = $this->form->getData();
            
            $cliente = new Cliente;
            $cliente->fromArray((array) $data);
            $cliente->store();

            TTransaction::close();
            new TMessage('info', 'Cliente cadastrado com sucesso!');
            
            
            //AdiantiCoreApplication::loadPage('VendaList', 'onReload');
            
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onEdit($param)
    {
        if (isset($param['id'])) {
            try {
                TTransaction::open('sample');
                $cliente = new Cliente($param['id']);
                $this->form->setData($cliente);
                TTransaction::close();
            } catch (Exception $e) {
                new TMessage('error', $e->getMessage());
            }
        }
    }
}
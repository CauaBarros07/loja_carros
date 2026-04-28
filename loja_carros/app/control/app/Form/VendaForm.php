<?php


use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Control\TApplication;
use Adianti\Database\TCriteria;
use Adianti\Database\TTransaction;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Wrapper\TDBCombo; 
use Adianti\Validator\TRequiredValidator;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Container\TVBox;

class VendaForm extends TPage
{
    private $form;

    function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_venda');
        $this->form->setFormTitle('Registrar Venda');
        
        // 1. Criamos o critério de filtragem
        $criteria = new TCriteria;
        // Filtra apenas carros onde o status é 'Disponível' (ajuste para o nome exato que usa no seu banco)
        $criteria->add(new TFilter('status', '=', 'Disponível'));

        // 2. Passamos o $criteria como o 7º parâmetro do TDBCombo
        
        $id         = new TEntry('id');
        $car_id = new TDBCombo('car_id', 'sample', 'Carro', 'id', '{brand} - {model}', 'model', $criteria);
        $sale_date  = new TDate('sale_date');
        $sale_value = new TNumeric('sale_value', 2, ',', '.', true);
        $cliente_nome = new TEntry('cliente_nome');

        
        $id->setEditable(FALSE);
        $sale_date->setMask('dd/mm/yyyy');
        $sale_date->setValue(date('d/m/Y'));
        
        $id->setSize('100%');
        $car_id->setSize('100%');
        $sale_date->setSize('100%');
        $sale_value->setSize('100%');
        $cliente_nome->setSize('100%');

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Carro')], [$car_id]);
        $this->form->addFields([new TLabel('Data da Venda')], [$sale_date], [new TLabel('Valor da Venda')], [$sale_value]);
        $this->form->addFields([new TLabel('Nome do Cliente')], [$cliente_nome]);

        
        $car_id->addValidation('Carro', new TRequiredValidator);
        $sale_value->addValidation('Valor', new TRequiredValidator);

        $this->form->addAction('Confirmar Venda', new TAction([$this, 'onSave']), 'fa:check-circle green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($this->form);
        parent::add($vbox);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            $this->form->validate();
            $data = $this->form->getData();

             
            $carro = new Carro($data->car_id);
            if ($carro->status == 'Vendido' && empty($data->id)) {
                throw new Exception('Este carro já foi vendido!');
            }

            
            $venda = new Venda;
            $venda->fromArray((array) $data);
            $venda->sale_date = TDate::date2us($data->sale_date);
            $venda->store();

            
            $carro->status = 'Vendido';
            $carro->store();

            TTransaction::close();
            new TMessage('info', 'Venda registrada e status do carro atualizado!');
            \TApplication::loadPage('VendaList', 'onReload');
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
                $venda = new Venda($param['id']);
                
                // CRIA UM CRITÉRIO QUE ACEITA O CARRO JÁ VENDIDO DESTA VENDA ESPECÍFICA
                $criteria = new TCriteria;
                $criteria->add(new TFilter('status', '=', 'Disponível'), TExpression::OR_OPERATOR);
                $criteria->add(new TFilter('id', '=', $venda->car_id), TExpression::OR_OPERATOR);
                $this->form->getField('car_id')->setCriteria($criteria);

                $venda->sale_date = TDate::date2br($venda->sale_date);
                $this->form->setData($venda);
                TTransaction::close();
            } catch (Exception $e) {
                new TMessage('error', $e->getMessage());
            }
        }
    }

    public function onClear() { $this->form->clear(); }
}
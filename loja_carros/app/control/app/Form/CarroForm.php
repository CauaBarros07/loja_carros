<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Database\TTransaction;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TLabel;
use Adianti\Validator\TRequiredValidator;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Form\TFile;

class CarroForm extends TPage
{
    private $form;

    function __construct()
    {
        parent::__construct();
    
        

        $this->form = new BootstrapFormBuilder('form_carro');
        $this->form->setFormTitle('Cadastro de Carro');

        $id     = new TEntry('id');
        $brand = new  TCombo('brand');
        $model  = new TEntry('model');
        $year   = new TEntry('year');
        $price  = new TNumeric('price', 2, ',', '.', true);
        $image  = new TFile('image');

        try {
            TTransaction::open('sample');


            $all_brands = Marca::all();
            $items = [];

            foreach ($all_brands as $obj_brand) {

                $items[$obj_brand->name] = $obj_brand->name;
            }

            $brand->addItems($items);
            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
        $brand->enableSearch();

        $image->enableFileHandling();
       

        $id->setEditable(FALSE);

        $id->setSize('100%');
        $brand->setSize('100%');
        $model->setSize('100%');
        $year->setSize('100%');
        $price->setSize('100%');



        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Marca')], [$brand], [new TLabel('Modelo')], [$model]);
        $this->form->addFields([new TLabel('Ano')], [$year], [new TLabel('Preço')], [$price]);
        $this->form->addFields([new TLabel('Imagem')], [$image]);


        $brand->addValidation('Marca', new TRequiredValidator);
        $model->addValidation('Modelo', new TRequiredValidator);
        $price->addValidation('Preço', new TRequiredValidator);


        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink('Voltar', new TAction(['CarroList', 'onReload']), 'fa:arrow-left orange');

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($this->form);
        parent::add($vbox);
    }

    public function onEdit($param)
    {
        if (isset($param['id'])) {
            try {
                TTransaction::open('sample');
                $carro = new Carro($param['id']);
                $this->form->setData($carro);
                TTransaction::close();
            } catch (Exception $e) {
                new TMessage('error', $e->getMessage());
            }
        }
    }

    public function onSave()
{
    try {
        TTransaction::open('sample');
        $data = $this->form->getData();
        
        $object = new Carro;
        $object->fromArray((array) $data);

        
        if (!empty($data->image)) {
            
            $image_data = json_decode(urldecode($data->image), true);
            
            
            if (isset($image_data['fileName'])) {
                
                $image_name = basename($image_data['fileName']);
                
                $source_file = 'tmp/' . $image_name;
                $target_dir  = 'app/images/cars';
                $target_file = $target_dir . '/' . $image_name;

                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                if (file_exists($source_file)) {
                    rename($source_file, $target_file);
                }

                $object->image = $image_name;
            }
        }

        $object->store();
        
        
        $data->image = $object->image;
        $this->form->setData($data);
        
        TTransaction::close();
        new TMessage('info', 'Carro salvo com sucesso!');
    } catch (Exception $e) {
        new TMessage('error', $e->getMessage());
        TTransaction::rollback();
    }
}

    public function onClear()
    {
        $this->form->clear();
    }
}

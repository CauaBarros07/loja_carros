<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Control\TApplication;
use Adianti\Database\TTransaction;   // O CORRETO É DATABASE
use Adianti\Database\TRepository;    // ADICIONE ESTE TAMBÉM
use Adianti\Database\TCriteria;      // ADICIONE ESTE TAMBÉM
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;

class CarroList extends TPage
{
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';

        
        $id     = new TDataGridColumn('id',     'ID',     'center', '10%');
        $marca  = new TDataGridColumn('brand',  'Marca',  'left',   '20%');
        $modelo = new TDataGridColumn('model',  'Modelo', 'left',   '30%');
        $ano    = new TDataGridColumn('year',   'Ano',    'center', '10%');
        $preco  = new TDataGridColumn('price',  'Preço',  'right',  '15%');
        $status = new TDataGridColumn('status', 'Status', 'center', '15%');

        $preco->setTransformer( function($value) {
            return 'R$ ' . number_format($value, 2, ',', '.');
        });

        $this->datagrid->addColumn($id);
        $this->datagrid->addColumn($marca);
        $this->datagrid->addColumn($modelo);
        $this->datagrid->addColumn($ano);
        $this->datagrid->addColumn($preco);
        $this->datagrid->addColumn($status);

        
        $action_edit = new TDataGridAction(['CarroForm', 'onEdit'], ['id' => '{id}']);
        $action_del  = new TDataGridAction([$this, 'onDelete'],     ['id' => '{id}']);

        $this->datagrid->addAction($action_edit, 'Editar', 'fa:edit blue');
        $this->datagrid->addAction($action_del,  'Excluir', 'far:trash-alt red');

        $this->datagrid->createModel();

        $panel = new TPanelGroup('Listagem de Carros');
        $panel->addHeaderActionLink('Novo Carro', new TAction(['CarroForm', 'onEdit']), 'fa:plus green');
        $panel->add($this->datagrid)->style = 'overflow-x:auto';

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($panel);
        parent::add($vbox);
    }

    public function onReload()
    {
        TTransaction::open('sample'); 
        $repository = new TRepository('Carro');
        $carros = $repository->load(new TCriteria);

        $this->datagrid->clear();
        if ($carros) {
            foreach ($carros as $carro) {
                $this->datagrid->addItem($carro);
            }
        }
        TTransaction::close();
    }

    public static function onDelete($param)
    {
        try {
            TTransaction::open('sample');
            $carro = new Carro($param['id']);
            $carro->delete();
            TTransaction::close();
            TApplication::loadPage('CarroList', 'onReload');
            new TMessage('info', "Carro excluído com sucesso!");
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function show() { $this->onReload(); parent::show(); }
}
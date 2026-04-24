<?php


use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Control\TApplication; 
use Adianti\Database\TTransaction;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TRequiredValidator; 
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Container\TVBox;


class VendaList extends TPage
{
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);

        $this->datagrid->style = 'width: 100%';

        $id    = new TDataGridColumn('id', 'ID', 'center', '10%');
        $marca = new TDataGridColumn('carro->brand', 'Marca', 'left', '20%');
        $modelo = new TDataGridColumn('carro->model', 'Modelo', 'left', '20%');
        $data  = new TDataGridColumn('sale_date',  'Data',  'center', '25%');
        $valor = new TDataGridColumn('sale_value', 'Valor', 'right',  '25%');


        $data->setTransformer(function ($value) {
            return (!empty($value)) ? date('d/m/Y', strtotime($value)) : '';
        });
        $valor->setTransformer(function ($value) {
            return 'R$ ' . number_format($value, 2, ',', '.');
        });

        $this->datagrid->addColumn($id);
        $this->datagrid->addColumn($marca);
        $this->datagrid->addColumn($modelo);
        $this->datagrid->addColumn($data);
        $this->datagrid->addColumn($valor);


        $action_del  = new TDataGridAction([$this, 'onDelete'],     ['id' => '{id}']);

        $this->datagrid->addAction($action_del,  'Excluir', 'far:trash-alt red');

        $this->datagrid->createModel();

        $panel = new TPanelGroup('Registro de Vendas Realizadas');
        $panel->addHeaderActionLink('Registrar Venda', new TAction(['VendaForm', 'onEdit']), 'fa:shopping-cart blue');
        $panel->add($this->datagrid)->style = 'overflow-x:auto';

        parent::add($panel);
    }

    public function onReload()
    {
        TTransaction::open('sample');
        $repository = new TRepository('Venda');
        $vendas = $repository->load(new TCriteria);

        $this->datagrid->clear();
        if ($vendas) {
            foreach ($vendas as $venda) {
                $this->datagrid->addItem($venda);
            }
        }
        TTransaction::close();
    }

    public static function onDelete($param)
    {
        // Ação de confirmação
        $action = new TAction([__CLASS__, 'Delete']);
        $action->setParameters($param);

        new TQuestion('Deseja realmente excluir a venda? O carro voltará ao status Disponível.', $action);
    }

    public static function Delete($param)
{
    try {
        TTransaction::open('sample'); 
        
        $venda = new Venda($param['id']);
        $carro = $venda->carro; 
        
        if ($carro) {
            $carro->status = 'Disponível';
            $carro->store();
        }
        
        $venda->delete();
        
        TTransaction::close();
        
        
        \TApplication::loadPage('VendaList', 'onReload');
        new TMessage('info', 'Venda excluída e status do carro atualizado!');
        
    } catch (Exception $e) {
        new TMessage('error', $e->getMessage());
        TTransaction::rollback();
    }
}

    public function show()
    {
        $this->onReload();
        parent::show();
    }
}

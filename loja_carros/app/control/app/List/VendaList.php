<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Base\TStyle;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;

class VendaList extends TPage
{
    private $form;
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_search_venda');

        $this->form->setProperty('style', 'border:none; box-shadow:none; margin:0; padding:5px;');
        $this->form->setProperty('class', 'no-line'); 

        $data_inicio = new TDate('data_inicio');
        $data_fim    = new TDate('data_fim');
        $data_inicio->setMask('dd/mm/yyyy');
        $data_fim->setMask('dd/mm/yyyy');
        $data_inicio->setSize('110px'); 
        $data_fim->setSize('110px');


        $label_ini = new TLabel('Início:');
        $label_fim = new TLabel('Fim:');


        $group_ini = new TElement('div');
        $group_ini->style = 'display: flex; align-items: center; gap: 5px; margin-right: 15px;';
        $group_ini->add($label_ini);
        $group_ini->add($data_inicio);

        $group_fim = new TElement('div');
        $group_fim->style = 'display: flex; align-items: center; gap: 5px;';
        $group_fim->add($label_fim);
        $group_fim->add($data_fim);


        $row = $this->form->addFields([$group_ini], [$group_fim]);
        $row->layout = ['col-sm-3', 'col-sm-3'];

        $btn_buscar = $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Limpar',  new TAction([$this, 'onReload']), 'fa:eraser red');


        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';

        $id     = new TDataGridColumn('id', 'ID', 'center', '10%');
        $marca  = new TDataGridColumn('carro->brand', 'Marca', 'left', '20%');
        $modelo = new TDataGridColumn('carro->model', 'Modelo', 'left', '20%');
        $data   = new TDataGridColumn('sale_date', 'Data', 'center', '25%');
        $valor  = new TDataGridColumn('sale_value', 'Valor Venda', 'right', '25%');

        $data->setTransformer(fn($v) => (!empty($v)) ? date('d/m/Y', strtotime($v)) : '');
        $valor->setTransformer(fn($v) => 'R$ ' . number_format($v, 2, ',', '.'));

        $this->datagrid->addColumn($id);
        $this->datagrid->addColumn($marca);
        $this->datagrid->addColumn($modelo);
        $this->datagrid->addColumn($data);
        $this->datagrid->addColumn($valor);

        $action_del = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);
        $this->datagrid->addAction($action_del, 'Excluir', 'far:trash-alt red');


        $this->datagrid->createModel();

        $panel = new TPanelGroup('Registro de Vendas Realizadas');

        $panel->addHeaderActionLink('Registrar Venda', new TAction(['VendaForm', 'onEdit']), 'fa:shopping-cart blue');


        $panel->add($this->form);


        $panel->add($this->datagrid)->style = 'overflow-x:auto; margin-top:0px';

        

        parent::add($panel);

        TScript::create('
            $(".tformactionwait").css("border-top", "none");
            $(".tformactionwait").css("padding-top", "0px");
            $(".tformactionwait").css("margin-top", "0px");
        ');
    }


    public function onSearch()
    {
        $data = $this->form->getData();

        $filter_ini = !empty($data->data_inicio) ? TDate::date2us($data->data_inicio) : null;
        $filter_fim = !empty($data->data_fim) ? TDate::date2us($data->data_fim) : null;

        $criteria = new TCriteria;
        if ($filter_ini && $filter_fim) {
            $criteria->add(new TFilter('sale_date', 'BETWEEN', $filter_ini, $filter_fim));
        }

        $this->onReload(['criteria' => $criteria]);
        $this->form->setData($data);
    }

    public function onReload($param = NULL)
    {
        try {
            TTransaction::open('sample');
            $repository = new TRepository('Venda');


            $criteria = (isset($param['criteria']) && $param['criteria'] instanceof TCriteria)
                ? $param['criteria']
                : new TCriteria;

            $criteria->setProperty('order', 'id');
            $criteria->setProperty('direction', 'desc');

            $vendas = $repository->load($criteria);

            $this->datagrid->clear();
            if ($vendas) {
                foreach ($vendas as $venda) {
                    $this->datagrid->addItem($venda);
                }
            }
            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public static function onDelete($param)
    {
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
            TApplication::loadPage('VendaList', 'onReload');
            new TMessage('info', 'Venda excluída e status do carro atualizado!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function show()
    {

        if (empty($_POST['data_inicio']) && empty($_POST['data_fim'])) {
            $this->onReload();
        }
        parent::show();
    }
}

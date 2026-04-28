<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Control\TApplication;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Registry\TSession;
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
use Adianti\Widget\Base\TElement;

class VendaList extends TPage
{
    private $form;
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        // 1. Configuração do Formulário (Sem título para não criar a "caixa" extra)
        $this->form = new BootstrapFormBuilder('form_search_venda');
        $this->form->setProperty('style', 'border:none; box-shadow:none; margin:0; padding:10px;');
      
        //$this->datagrid->style .= '; cursor: default;';
        
        $data_inicio = new TDate('data_inicio');
        $data_fim    = new TDate('data_fim');
        $data_inicio->setMask('dd/mm/yyyy');
        $data_fim->setMask('dd/mm/yyyy');
        $data_inicio->setSize('120px');
        $data_fim->setSize('120px');

        // Adicionando os campos em uma linha única para ficar elegante
        $row = $this->form->addFields( 
            [new TLabel('Início:')], [$data_inicio], 
            [new TLabel('Fim:')], [$data_fim] 
        );
        $row->layout = ['col-sm-2', 'col-sm-2', 'col-sm-2', 'col-sm-2'];

        // Ações do formulário
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');

        // 2. Configuração da Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';

        $this->datagrid->disableDefaultClick();

        $id      = new TDataGridColumn('id', 'ID', 'center', '10%');
        $marca   = new TDataGridColumn('carro->brand', 'Marca', 'left', '20%');
        $modelo  = new TDataGridColumn('carro->model', 'Modelo', 'left', '20%');
        $data    = new TDataGridColumn('sale_date', 'Data', 'center', '20%');
        $cliente = new TDataGridColumn('cliente_nome', 'Cliente', 'left', '15%');
        $valor   = new TDataGridColumn('sale_value', 'Valor Venda', 'right', '15%');

        $data->setTransformer(fn($v) => (!empty($v)) ? date('d/m/Y', strtotime($v)) : '');
        $valor->setTransformer(fn($v) => 'R$ ' . number_format($v, 2, ',', '.'));

        $this->datagrid->addColumn($id);
        $this->datagrid->addColumn($marca);
        $this->datagrid->addColumn($modelo);
        $this->datagrid->addColumn($data);
        $this->datagrid->addColumn($cliente);
        $this->datagrid->addColumn($valor);

        $id->setAction(new TAction([$this, 'onReload']), ['order' => 'id']);
        $data->setAction(new TAction([$this, 'onReload']), ['order' => 'sale_date']);

        $action_del = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);
        $this->datagrid->addAction($action_del, 'Excluir', 'far:trash-alt red');

        $this->datagrid->createModel();

        // 3. O PAINEL ÚNICO (Onde tudo fica dentro da mesma borda)
        $panel = new TPanelGroup('Registro de Vendas Realizadas');
        $panel->addHeaderActionLink('Registrar Venda', new TAction(['VendaForm', 'onEdit']), 'fa:shopping-cart blue');
        
        // Adiciona o formulário e a grade direto no painel principal
        $panel->add($this->form);
        $panel->add($this->datagrid)->style = 'overflow-x:auto; border-top: 1px solid #eeeeee;';

        parent::add($panel);
    }

    public function onSearch()
    {
        $data = $this->form->getData();
        TSession::setValue(__CLASS__ . '_filters', NULL);

        if (!empty($data->data_inicio) && !empty($data->data_fim)) 
        {
            $filter_ini = TDate::date2us($data->data_inicio);
            $filter_fim = TDate::date2us($data->data_fim);
            
            $filters = [new TFilter('sale_date', 'BETWEEN', $filter_ini, $filter_fim)];
            TSession::setValue(__CLASS__ . '_filters', $filters);
        }

        $this->form->setData($data);
        $this->onReload();
    }

    public function onReload($param = NULL)
    {
        try {
            TTransaction::open('sample');
            $repository = new TRepository('Venda');
            $criteria = new TCriteria;

            $filters = TSession::getValue(__CLASS__ . '_filters');
            if ($filters) {
                foreach ($filters as $filter) {
                    $criteria->add($filter);
                }
            }

            $order = isset($param['order']) ? $param['order'] : 'id';
            $direction = isset($param['direction']) ? $param['direction'] : 'desc';
            $criteria->setProperty('order', $order);
            $criteria->setProperty('direction', $direction);

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

    public function onClear()
    {
        TSession::setValue(__CLASS__ . '_filters', NULL);
        $this->form->clear();
        $this->onReload();
    }

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'Delete']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente excluir a venda?', $action);
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
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }
}
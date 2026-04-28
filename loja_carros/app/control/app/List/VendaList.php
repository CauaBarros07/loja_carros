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


        $this->form = new BootstrapFormBuilder('form_search_venda');
        $this->form->setProperty('style', 'border:none; box-shadow:none; margin:0; padding:10px;');



        $data_inicio = new TDate('data_inicio');
        $data_fim    = new TDate('data_fim');
        $data_inicio->setMask('dd/mm/yyyy');
        $data_fim->setMask('dd/mm/yyyy');
        $data_inicio->setSize('120px');
        $data_fim->setSize('120px');


        $row = $this->form->addFields(
            [new TLabel('Início:')],
            [$data_inicio],
            [new TLabel('Fim:')],
            [$data_fim]
        );
        $row->layout = ['col-sm-1', 'col-sm-4', 'col-sm-1', 'col-sm-4'];


        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');


        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';

        $this->datagrid->disableDefaultClick();

        $id      = new TDataGridColumn('id', 'ID', 'center', '10%');
        $marca   = new TDataGridColumn('carro->brand', 'Marca', 'left', '20%');
        $modelo  = new TDataGridColumn('carro->model', 'Modelo', 'left', '20%');
        $data    = new TDataGridColumn('sale_date', 'Data', 'center', '20%');
        $cliente = new TDataGridColumn('cliente->nome', 'Cliente', 'left', '15%');
        $valor   = new TDataGridColumn('sale_value', 'Valor Venda', 'right', '15%');
       

        $cliente->setTransformer(function ($value, $object, $row) {
            
            $obj_cliente = $object->get_cliente();

            if ($obj_cliente) {
                
                $container = new TElement('span');

                
                $container->add($obj_cliente->nome . ' ');

                
                $icon = new TElement('i');
                $icon->{'class'} = 'fa fa-info-circle blue';
                $icon->{'style'} = 'cursor:help; margin-left: 4px;'; 

                
                $tooltip = "CPF: {$obj_cliente->cpf} \n";
                $tooltip .= "E-mail: {$obj_cliente->email} \n";
                $tooltip .= "Tel: {$obj_cliente->telefone}";
                $icon->{'title'} = $tooltip;

                
                $container->add($icon);

                return $container;
            }

            return $value;
        });


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
        $marca->setAction(new TAction([$this, 'onReload']), ['order' => 'carro_marca']);
        $modelo->setAction(new TAction([$this, 'onReload']), ['order' => 'carro_modelo']);
        $cliente->setAction(new TAction([$this, 'onReload']), ['order' => 'cliente_nome']);
        $valor->setAction(new TAction([$this, 'onReload']), ['order' => 'sale_value']);

        $action_del = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);
        $this->datagrid->addAction($action_del, 'Excluir', 'far:trash-alt red');

        $this->datagrid->createModel();


        $panel = new TPanelGroup('Registro de Vendas Realizadas');
        $panel->addHeaderActionLink('Registrar Venda', new TAction(['VendaForm', 'onEdit']), 'fa:shopping-cart blue');
        $panel->addHeaderActionLink('Novo Cliente', new TAction(['ClienteForm', 'onEdit']), 'fa:user-plus green');


        $panel->add($this->form);
        $panel->add($this->datagrid)->style = 'overflow-x:auto; border-top: 1px solid #eeeeee;';

        parent::add($panel);
    }

    public function onSearch()
    {
        $data = $this->form->getData();
        TSession::setValue(__CLASS__ . '_filters', NULL);

        if (!empty($data->data_inicio) && !empty($data->data_fim)) {
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

            // Recupera filtros da sessão
            $filters = TSession::getValue(__CLASS__ . '_filters');
            if ($filters) {
                foreach ($filters as $filter) {
                    $criteria->add($filter);
                }
            }

            // --- INÍCIO DA CONVERSÃO DE CAMPOS ---
            $order = isset($param['order']) ? $param['order'] : 'id';
            $direction = isset($param['direction']) ? $param['direction'] : 'desc';

            // Dentro do seu onReload, no bloco de conversão que fizemos:
            if ($order == 'carro_marca') {
                $order = "(SELECT brand FROM cars WHERE cars.id = sales.car_id)";
            } elseif ($order == 'carro_modelo') {
                $order = "(SELECT model FROM cars WHERE cars.id = sales.car_id)";
            } elseif ($order == 'cliente_nome') {
                // Busca o nome na tabela 'cliente' usando o id da venda
                $order = "(SELECT nome FROM cliente WHERE cliente.id = sales.cliente_id)";
            }

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
            new TMessage('error', 'Erro ao carregar dados: ' . $e->getMessage());
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
            \Adianti\Core\AdiantiCoreApplication::loadPage('VendaList', 'onReload');
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

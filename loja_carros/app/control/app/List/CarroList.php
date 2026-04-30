<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Control\TApplication;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Base\TScript;



class CarroList extends TPage
{
    private $datagrid;
    private $pageNavigation;
    private $limit = 10;

    public function __construct()
    {
        parent::__construct();


        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->disableDefaultClick();

        $input_search = new TEntry('input_search');
        $input_search->placeholder = 'Pesquisar na listagem...';
        $input_search->setSize('100%');

        $this->datagrid->enableSearch($input_search, 'brand, model, year, status');


        $id     = new TDataGridColumn('id',     'ID',     'center', '10%');
        $marca  = new TDataGridColumn('brand',  'Marca',  'left',   '20%');
        $modelo = new TDataGridColumn('model',  'Modelo', 'left',   '30%');
        $image  = new TDataGridColumn('image',  'Foto',  'center', '10%');
        $ano    = new TDataGridColumn('year',   'Ano',    'center', '10%');
        $preco  = new TDataGridColumn('price',  'Preço',  'right',  '15%');
        $status = new TDataGridColumn('status', 'Status', 'center', '15%');

        $preco->setTransformer(function ($value) {
            return 'R$ ' . number_format($value, 2, ',', '.');
        });

        $image->setTransformer(function ($value, $object, $row) {
            $path = 'app/images/cars/' . $value;

            if (!empty($value) && file_exists($path)) {

                $html = "<img src=\'{$path}\' style=\'width:100%; border-radius:5px;\'>";


                $img = new TElement('img');
                $img->src = $path;
                $img->style = 'width:110px; height:70px; object-fit:cover; cursor:pointer';
                $img->class = 'img-thumbnail';


                $img->onclick = "bootbox.dialog({
            title: 'Visualizar Veículo',
            message: '{$html}',
            size: 'large',
            onEscape: true,
            backdrop: true
        });";

                return $img;
            }
            return "<i class='fas fa-camera' style='color:#ccc'></i>";
        });

        $this->datagrid->addColumn($id);
        $this->datagrid->addColumn($marca);
        $this->datagrid->addColumn($modelo);
        $this->datagrid->addColumn($image);
        $this->datagrid->addColumn($ano);
        $this->datagrid->addColumn($preco);
        $this->datagrid->addColumn($status);


        $id->setAction(new TAction([$this, 'onReload']),     ['order' => 'id']);
        $marca->setAction(new TAction([$this, 'onReload']),  ['order' => 'brand']);
        $modelo->setAction(new TAction([$this, 'onReload']), ['order' => 'model']);
        $ano->setAction(new TAction([$this, 'onReload']),    ['order' => 'year']);
        $preco->setAction(new TAction([$this, 'onReload']),  ['order' => 'price']);
        $status->setAction(new TAction([$this, 'onReload']), ['order' => 'status']);


        $action_edit = new TDataGridAction(['CarroForm', 'onEdit'], ['id' => '{id}']);
        $action_del  = new TDataGridAction([$this, 'onDelete'],     ['id' => '{id}']);

        // Define a regra: Exibir se o login for diferente de 'vendedor'
        $permitido = function () {
            return TSession::getValue('login') !== 'vendedor';
        };

        // Aplica a condição aos botões
        $action_edit->setDisplayCondition($permitido);
        $action_del->setDisplayCondition($permitido);

        $this->datagrid->addAction($action_edit, 'Editar', 'fa:edit blue');
        $this->datagrid->addAction($action_del,  'Excluir', 'far:trash-alt red');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        $panel = new TPanelGroup('Listagem de Carros');

        // Obtém o login atual
        $login = TSession::getValue('login');

        // SE o login NÃO FOR 'vendedor', ele mostra os botões
        if ($login !== 'vendedor') {
            $panel->addHeaderActionLink('Novo Carro', new TAction(['CarroForm', 'onEdit']), 'fa:plus green');
            $panel->addHeaderActionLink('Gerenciar Marcas', new TAction(['MarcaForm', 'onEdit']), 'fa:tags blue');
        }
        $panel->add($this->datagrid)->style = 'overflow-x:auto';
        $panel->addFooter($this->pageNavigation);

        $input_search->setSize('250px');
        $input_search->style = 'margin-left: 10px; height: 30px; padding: 2px 5px;';

        $panel->addHeaderWidget($input_search);


        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($panel);
        parent::add($vbox);
    }

    public function onReload($param = NULL)
    {
        try {
            TTransaction::open('sample');
            $repository = new TRepository('Carro');


            $criteria = new TCriteria;

            $criteria->setProperty('limit', $this->limit); // Define quantos itens por página
            $criteria->setProperties($param); // Lê a página atual e a ordenação dos parâmetros da URL


            if (isset($param['order'])) {
                $order = $param['order'];
                $direction = isset($param['direction']) ? $param['direction'] : 'asc';
                $criteria->setProperty('order', $order);
                $criteria->setProperty('direction', $direction);
            } else {

                $criteria->setProperty('order', 'id');
                $criteria->setProperty('direction', 'desc');
            }

            $carros = $repository->load($criteria);

            $this->datagrid->clear();
            if ($carros) {
                foreach ($carros as $carro) {
                    $this->datagrid->addItem($carro);
                }
            }


            $criteria->resetProperties(); // Limpa limit e offset para contar o total real
            $count = $repository->count($criteria);
            $this->pageNavigation->setCount($count); // Total de registros
            $this->pageNavigation->setProperties($param); // Mantém estado da ordenação
            $this->pageNavigation->setLimit($this->limit); // Quantos por página

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
        new TQuestion('Deseja realmente excluir o veículo?', $action);
    }


    public static function Delete($param)
    {
        try {
            TTransaction::open('sample');

            $key = $param['id'];
            $carro = new Carro($key);

            if ($carro->status == 'Vendido') {
                throw new Exception('Não é permitido excluir um carro com status "Vendido"!');
            }

            $carro->delete();

            TTransaction::close();


            \TApplication::loadPage('CarroList', 'onReload');

            new TMessage('info', 'Registro excluído com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public static function onExibirImagem($param)
    {
        $imagem = $param['imagem'] ?? '';
        $path = 'app/images/cars/' . $imagem;


        $html = "<img src='{$path}' style='width:100%; border-radius:5px;'>";


        \Adianti\Widget\Base\TScript::create("
        bootbox.dialog({
            title: 'Visualizar Veículo',
            message: '{$html}',
            size: 'large',
            onEscape: true,
            backdrop: true
        });
    ");
    }

    public function show()
    {
        $this->onReload();
        parent::show();
    }
}

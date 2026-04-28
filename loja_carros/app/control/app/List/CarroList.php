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

    public function __construct()
    {
        parent::__construct();


        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->disableDefaultClick();


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
        // Criamos o HTML da imagem expandida para o JavaScript
        $html = "<img src=\'{$path}\' style=\'width:100%; border-radius:5px;\'>";
        
        // Criamos a imagem da miniatura com o comando de abrir o modal
        $img = new TElement('img');
        $img->src = $path;
        $img->style = 'width:110px; height:70px; object-fit:cover; cursor:pointer';
        $img->class = 'img-thumbnail';
        
        // O segredo está aqui: o clique chama o Bootbox (que o Adianti já tem) direto pelo navegador
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

        // Defina as ações de ordenação para as colunas
        $id->setAction(new TAction([$this, 'onReload']),     ['order' => 'id']);
        $marca->setAction(new TAction([$this, 'onReload']),  ['order' => 'brand']);
        $modelo->setAction(new TAction([$this, 'onReload']), ['order' => 'model']);
        $ano->setAction(new TAction([$this, 'onReload']),    ['order' => 'year']);
        $preco->setAction(new TAction([$this, 'onReload']),  ['order' => 'price']);
        $status->setAction(new TAction([$this, 'onReload']), ['order' => 'status']);


        $action_edit = new TDataGridAction(['CarroForm', 'onEdit'], ['id' => '{id}']);
        $action_del  = new TDataGridAction([$this, 'onDelete'],     ['id' => '{id}']);

        $this->datagrid->addAction($action_edit, 'Editar', 'fa:edit blue');
        $this->datagrid->addAction($action_del,  'Excluir', 'far:trash-alt red');

        $this->datagrid->createModel();

        $panel = new TPanelGroup('Listagem de Carros');
        $panel->addHeaderActionLink('Novo Carro', new TAction(['CarroForm', 'onEdit']), 'fa:plus green');
        $panel->addHeaderActionLink('Gerenciar Marcas', new TAction(['MarcaForm', 'onEdit']), 'fa:tags blue');
        $panel->add($this->datagrid)->style = 'overflow-x:auto';

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

    // Criamos o HTML da imagem que será exibida
    $html = "<img src='{$path}' style='width:100%; border-radius:5px;'>";

    // Usamos o TScript para dar um comando direto ao JavaScript do navegador
    // Isso abre o Modal do Bootstrap sem precisar carregar classes de Container
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

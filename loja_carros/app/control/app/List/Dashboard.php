<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Widget\Dialog\TMessage;

class Dashboard extends TPage
{
    private $datagrid;

    function __construct()
    {
        parent::__construct();

        try {
            
            TTransaction::open('sample'); 

            $mes_atual = date('m');
            $ano_atual = date('Y');

            
            $vendas_count = Venda::where('MONTH(sale_date)', '=', $mes_atual)
                                 ->where('YEAR(sale_date)', '=', $ano_atual)
                                 ->count();
            
            $vendas_total = Venda::where('MONTH(sale_date)', '=', $mes_atual)
                                 ->where('YEAR(sale_date)', '=', $ano_atual)
                                 ->sumBy('sale_value');

            
            $ultimas_vendas = Venda::orderBy('id', 'desc')->take(10)->load();

            
            $vbox = new TVBox;
            $vbox->style = 'width: 100%';

            $div_indicadores = new TElement('div');
            $div_indicadores->class = "row";

            $indicator1 = new \TNumericIndicator;
            $indicator1->setTitle('CARROS VENDIDOS NO MÊS');
            $indicator1->setValue($vendas_count ?? 0);
            $indicator1->setIcon('shopping-cart');
            $indicator1->setColor('blue');
            $indicator1->setNumericMask(0, ',', '.');

            $indicator2 = new \TNumericIndicator;
            $indicator2->setTitle('VALOR TOTAL DE VENDAS');
            $indicator2->setValue($vendas_total ?? 0);
            $indicator2->setIcon('money-bill-wave');
            $indicator2->setColor('green');
            $indicator2->setNumericMask(2, ',', '.', 'R$ ');

            $div_indicadores->add($i1 = TElement::tag('div', $indicator1));
            $div_indicadores->add($i2 = TElement::tag('div', $indicator2));

            $i1->class = 'col-sm-6';
            $i2->class = 'col-sm-6';

            $div_indicadores->add( new GraficoBarras );

            
            $panel_list = new TPanelGroup('Últimas Vendas Realizadas');
            $panel_list->style = 'margin-top: 20px; width: 100%';

            $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
            $this->datagrid->style = 'width: 100%';

            $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '10%'));
            $this->datagrid->addColumn(new TDataGridColumn('carro->brand', 'Marca', 'left', '15%'));
            $this->datagrid->addColumn(new TDataGridColumn('carro->model', 'Modelo', 'left', '15%'));
            $this->datagrid->addColumn(new TDataGridColumn('cliente->nome', 'Cliente', 'left', '20%'));

            
            
            
            $column_date = new TDataGridColumn('sale_date', 'Data', 'center', '20%');
            $column_date->setTransformer(function($value) {
                if (!empty($value)) {
                    return date('d/m/Y', strtotime($value));
                }
                return '';
            });
            $this->datagrid->addColumn($column_date);

            
            $column_price = new TDataGridColumn('sale_value', 'Valor', 'right', '30%');
            $column_price->setTransformer(function($value){
                return 'R$ ' . number_format($value, 2, ',', '.');
            });
            $this->datagrid->addColumn($column_price);

            $this->datagrid->createModel();
            
            
            if ($ultimas_vendas) {
                $this->datagrid->addItems($ultimas_vendas);
            }
            
            $panel_list->add($this->datagrid);

            
            $vbox->add($div_indicadores);
            $vbox->add($panel_list);

            parent::add($vbox);

            
            TTransaction::close();
            
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
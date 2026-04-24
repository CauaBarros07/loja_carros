<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Chart\TBarChart;
use Adianti\Widget\Dialog\TMessage;

class GraficoBarras extends TPage
{
    public function __construct()
    {
        parent::__construct();
        
        try {
            TTransaction::open('sample');

            $vendas = Venda::where('MONTH(sale_date)', '=', date('m'))
                           ->where('YEAR(sale_date)', '=', date('Y'))
                           ->get();

            $agrupado = [];

            if ($vendas) {
                foreach ($vendas as $venda) {
                    
                    $dia = date('d/m', strtotime($venda->sale_date));
                    
                    if (isset($agrupado[$dia])) {
                        $agrupado[$dia] += (float) $venda->sale_value;
                    } else {
                        $agrupado[$dia] = (float) $venda->sale_value;
                    }
                }
            }

            ksort($agrupado);


            $dias = array_keys($agrupado);
            $valores = array_values($agrupado);

            if (empty($dias)) {
                $dias = [date('d/m')];
                $valores = [0];
            }

            TTransaction::close();

            $chart = new TBarChart('100%', 400);
            $chart->setTitle('Faturamento Diário Acumulado');
            

            $chart->setXLabels($dias); 


            $chart->addDataset('Total em Vendas', $valores, '#4285F4');

            $panel = new TPanelGroup('Desempenho da Loja');
            $panel->add($chart);
            
            $container = new TVBox;
            $container->style = 'width: 100%';
            $container->add($panel);

            parent::add($container);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            @TTransaction::rollback();
        }
    }
}
<?php



use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Control\TApplication; // Certifique-se que esta linha está EXATAMENTE assim
use Adianti\Database\TTransaction;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Wrapper\TDBCombo; 
use Adianti\Validator\TRequiredValidator;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Container\TVBox;


class CarroForm extends TPage
{
    private $form;

    function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_carro');
        $this->form->setFormTitle('Cadastro de Carro');

        // Cria os campos conforme requisitos
        $id     = new TEntry('id');
        $brand  = new TEntry('brand');
        $model  = new TEntry('model');
        $year   = new TEntry('year');
        $price  = new TNumeric('price', 2, ',', '.', true);
        $status = new TCombo('status');

        // Configurações
        $id->setEditable(FALSE);
        $id->setSize('100%');
        $brand->setSize('100%');
        $model->setSize('100%');
        $year->setSize('100%');
        $price->setSize('100%');
        $status->setSize('100%');

        $status->addItems(['Disponível' => 'Disponível', 'Vendido' => 'Vendido']);
        $status->setDefaultOption(FALSE);

        // Adiciona campos ao formulário
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Marca')], [$brand], [new TLabel('Modelo')], [$model]);
        $this->form->addFields([new TLabel('Ano')], [$year], [new TLabel('Preço')], [$price]);
        $this->form->addFields([new TLabel('Status')], [$status]);

        // Validações obrigatórias
        $brand->addValidation('Marca', new TRequiredValidator);
        $model->addValidation('Modelo', new TRequiredValidator);
        $price->addValidation('Preço', new TRequiredValidator);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');

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

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            $this->form->validate();

            $carro = new Carro;
            $data = $this->form->getData();
            $carro->fromArray((array) $data);
            $carro->store();

            TTransaction::close();
            new TMessage('info', 'Carro salvo com sucesso!');
            \TApplication::loadPage('CarroList', 'onReload');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onClear() { $this->form->clear(); }
}
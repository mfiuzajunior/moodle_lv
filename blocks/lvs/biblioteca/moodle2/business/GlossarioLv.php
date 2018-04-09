<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\business\AtividadeLv;
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\avaliacao\AvaliacaoLv;

/**
 * Avalia o desempenho no GlossárioLV
 * 
 * @category LVs
 * @package uab\ifce\lvs\moodle2\business
 * @author Fiuza Junior (mfiuzajunior@gmail.com)
 * @version SVN $Id
 * 
 */
class GlossarioLv extends AtividadeLv {

    /*
     * Configurações do GlossárioLV
     * @var \stdClass
     * @access private
     */
    private $_glossariolv;

    /**
     * Nome da tabela do banco de dados que possui a avaliação lv dos estudantes avaliados
     * @var string
     */
    private $_tabelaAvaliacao = 'lvs_glossariolv';

    /**
     * Nome da tabela do banco de dados que possui a configuração lv de todos os glossárioslvs
     * @var string
     */
    private $_tabelaConfiguracao = 'glossarylv';

    private $_tabelaNota = 'rating';
    /**
     *      Instancia Glossariolv
     *      @param int $glossariolv_id id do glossáriolv
     */
    public function __construct($glossariolv_id){
	$this->_init($glossariolv_id);
    }

    private function _init($glossariolv_id){
        global $DB;
        $this->_glossariolv = $DB->get_record( $this->_tabelaConfiguracao,
                                               array('id'=>$glossariolv_id),
                                               'id,
                                                course as cursoava,
                                                porcentagem,
                                                etapa,
                                                fator_multiplicativo,
                                                assesstimestart as inicio_periodo_avaliacao, 
                                                assesstimefinish as fim_periodo_avaliacao,
                                                exibir' );
    }

    public function contribuicao( Item $item ){
	return true;
    }

    public function getAvaliacao( Item $item ) {
	if( $item->getAvaliacao() != null ){
            return $item->getAvaliacao();
	}

	global $DB;
	$avaliacaolv = null;
/*
echo '<p>Tabela: '.$this->_tabelaNota.'</p>';
echo '<p>Atividade: '.$item->getAtividade().'</p>';
echo '<p>Componente: '.$item->getComponente().'</p>';
echo '<p>Itemid: '.$item->getItem()->id.'</p>';
*/
        $avaliacao = $DB->get_record($this->_tabelaNota, array( 'component' => 'mod_'.$item->getAtividade(),
                                                                'ratingarea' => $item->getComponente(),
                                                                'itemid' => $item->getItem()->id));
	if( $avaliacao ){
            $avaliacaolv = new AvaliacaoLv();
            $avaliacaolv->setAvaliador( $avaliacao->userid );
            $avaliacaolv->setEstudante( $item->getItem()->userid );
            $avaliacaolv->setDataCriacao( $avaliacao->timecreated );
            $avaliacaolv->setDataModificacao( $avaliacao->timemodified );
            $avaliacaolv->setItem($item);
            $avaliacaolv->setNota( $avaliacao->rating );

            $item->setAvaliacao($avaliacaolv);
	}

	return $avaliacaolv;
    }

    public function getNota( $estudante ){
	return 0.0;
    }

    public function podeAvaliar( Item $item ){
	return true;
    }

    public function podeVerNota( Item $item ){
	// TODO: Implementar função
	return true;
    }

    public function removerAvaliacao( $avaliacao ){}
    public function salvarAvaliacao( AvaliacaoLv $avaliacao ){}

}
?>

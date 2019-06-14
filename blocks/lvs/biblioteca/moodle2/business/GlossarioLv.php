<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\EscalaLikert;
use uab\ifce\lvs\business\AtividadeLv;
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\avaliacao\AvaliacaoLv;
use uab\ifce\lvs\util\ChromePhp;

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
        global $DB;
        return $DB->get_field($this->_tabelaAvaliacao, 'modulo_vetor', array('id_glossariolv'=>$this->_glossariolv->id, 'id_usuario'=>$estudante));
    }

    public function podeAvaliar( Item $item ){
        $insercoes_avaliadas = $this->_buscarEntradasAvaliadas($item->getItem()->userid);
        $comentarios_avaliados = $this->_buscarComentariosAvaliados($item->getItem()->userid);
        if( $item->getComponente() == 'entry' ) { // Se quer avaliar uma inserção:
            /*
            * Verifica se existe avaliação de inserção anterior. Se não houver, pode avaliar. Se houver avaliação, só pode avaliar de novo depois que houver avaliação de
            * ao menos um comentário.
            */
            return count($insercoes_avaliadas) == 0 ? true : count($comentarios_avaliados) > 0;
        } else if( $item->getComponente() == 'comment' ) {// Se quer avaliar um comentário:
            /*
            * Verifica se existe avaliação de comentário anterior. Se não houver, pode avaliar. Se houver avaliação, só pode avaliar de novo depois que houver avaliação de
            * ao menos uma inserção.
            */
            return count($comentarios_avaliados) == 0 ? true : count($insercoes_avaliadas) > 0;
        }

        return false;
    }

    public function podeVerNota( Item $item ){
        // TODO: Implementar função
        return true;
    }

    public function removerAvaliacao( $avaliacao ){
        global $DB;
        $item = $avaliacao->getItem();
        
        $avaliacao_atual = $DB->get_record($this->_tabelaNota, array(
                'component'  => 'mod_'.$item->getAtividade(),
                'ratingarea' => $item->getComponente(),
                'itemid'     => $item->getItem()->id
        ));
        
        if($avaliacao_atual) {
            $DB->delete_records($this->_tabelaNota, array('id'=>$avaliacao_atual->id));
        }
        
        $this->_avaliarDesempenho($avaliacao->getEstudante());
    }

    public function salvarAvaliacao( AvaliacaoLv $avaliacao ){
        global $DB;
        $avaliacao->setNota( intval($avaliacao->getNota()) );

        $nova_avaliacao = new \stdClass();
        $nova_avaliacao->contextid  = 0;
        $nova_avaliacao->scaleid    = 0;
        $nova_avaliacao->component  = 'mod_glossarylv';
        $nova_avaliacao->ratingarea = $avaliacao->getItem()->getComponente();
        $nova_avaliacao->itemid     = $avaliacao->getItem()->getItem()->id;
        $nova_avaliacao->userid     = $avaliacao->getAvaliador();
        $nova_avaliacao->rating     = $avaliacao->getNota();

        $avaliacao_atual = $DB->get_record($this->_tabelaNota, array(
                'component'  => $nova_avaliacao->component,
                'ratingarea' => $nova_avaliacao->ratingarea,
                'itemid'     => $nova_avaliacao->itemid
        ));

        if(!$avaliacao_atual) {
            $nova_avaliacao->timecreated = $nova_avaliacao->timemodified = time();
            $DB->insert_record($this->_tabelaNota, $nova_avaliacao);
        } else {
            $nova_avaliacao->id = $avaliacao_atual->id;
            $nova_avaliacao->timemodified = time();
            $DB->update_record($this->_tabelaNota, $nova_avaliacao);
        }

        $this->_avaliarDesempenho($avaliacao->getEstudante());
    }

    private function _buscarEntradasNesseGlossario( $estudante ){
        global $DB;
        return $DB->get_records('glossarylv_entries', array('glossarylvid'=>$this->_glossariolv->id,'userid'=>$estudante), 'timecreated ASC', 'id');;
    }

    private function _buscarEntradasAvaliadas( $estudante ){
        global $DB;
        $entradas_do_estudante = $this->_buscarEntradasNesseGlossario( $estudante );
        if ( !empty($entradas_do_estudante) ) {
            list($mask, $params) = $DB->get_in_or_equal(array_keys($entradas_do_estudante));
            return $DB->get_records_select($this->_tabelaNota, "component='mod_glossarylv' AND ratingarea='entry' AND itemid $mask", $params, 'itemid');
        }
    }

    private function _buscarComentariosNesseGlossario( $estudante ){
        global $DB;
        $consulta = 'select
                        comentarios.*
                     from
                        mdl_comments comentarios inner join
                        mdl_glossarylv_entries insercoes on comentarios.itemid = insercoes.id
                     where
                        insercoes.glossarylvid = :id_glossariolv and
                        comentarios.userid = :id_usuario
                     order by timecreated ASC';
        return $DB->get_records_sql($consulta, array('id_glossariolv'=>$this->_glossariolv->id, 'id_usuario'=>$estudante));
    }

    private function _buscarComentariosAvaliados( $estudante ){
        global $DB;
        $comentarios_do_estudante = $this->_buscarComentariosNesseGlossario( $estudante );
        if ( !empty($comentarios_do_estudante) ) {
            list($mask, $params) = $DB->get_in_or_equal(array_keys($comentarios_do_estudante));
            return $DB->get_records_select($this->_tabelaNota, "component='mod_glossarylv' AND ratingarea='comment' AND itemid $mask", $params, 'id');
        }
    }

    private function _comparaAvaliacoesPorDataDeCriacao( $avaliacao1, $avaliacao2 ){
        return strcmp($avaliacao1->timecreated, $avaliacao2->timecreated);
    }

    private function _ordenarAvaliacoes( $avaliacoes ){
        usort($avaliacoes, array($this,'_comparaAvaliacoesPorDataDeCriacao'));
    }

    private function _avaliarDesempenho( $estudante ){
        global $DB;

        $entradas_avaliadas = $this->_buscarEntradasAvaliadas( $estudante );
        $comentarios_avaliados = $this->_buscarComentariosAvaliados( $estudante );
        $avaliacoes = array_merge($entradas_avaliadas, $comentarios_avaliados);

        $this->_ordenarAvaliacoes($avaliacoes);
        $desempenho_atual = $DB->get_record($this->_tabelaAvaliacao, array(
                'id_curso' => $this->_glossariolv->cursoava,
                'id_glossariolv'=> $this->_glossariolv->id,
                'id_usuario' => $estudante
        ));

        if ( empty($avaliacoes) && !empty($desempenho_atual) ) {
            $DB->delete_records($this->_tabelaAvaliacao, array('id'=>$desempenho_atual->id));
            return 0;
        } else {
            list($I, $carinhas) = $this->_calcularVariacaoAngular($avaliacoes);
    
            $novo_desempenho = new \stdClass();
            $novo_desempenho->numero_carinhas_azul = $carinhas['azul'];
            $novo_desempenho->numero_carinhas_verde = $carinhas['verde'];
            $novo_desempenho->numero_carinhas_amarela = $carinhas['amarela'];
            $novo_desempenho->numero_carinhas_laranja = $carinhas['laranja'];
            $novo_desempenho->numero_carinhas_vermelha = $carinhas['vermelha'];
            $novo_desempenho->numero_carinhas_preta = $carinhas['preta'];
            $novo_desempenho->modulo_vetor = $this->calcularModuloVetor($I);

            $novo_desempenho->beta = $this->calcularBeta($novo_desempenho->modulo_vetor, $carinhas);
            
            if (empty($desempenho_atual)) {
                $novo_desempenho->id_curso = $this->_glossariolv->cursoava;
                $novo_desempenho->id_glossariolv = $this->_glossariolv->id;
                $novo_desempenho->id_usuario = $estudante;
                $DB->insert_record($this->_tabelaAvaliacao, $novo_desempenho);
            } else {
                $novo_desempenho->id = $desempenho_atual->id;
                $DB->update_record($this->_tabelaAvaliacao, $novo_desempenho);
            }
        }
        return $novo_desempenho->modulo_vetor;
    }

    /**
     *  Calcula a variação angular por meio das notas obtidas nas avaliações
     *
     *  @param array:\stdClass $avaliacoes
     *  @return array [ variacao_angular: int, carinhas: array ]
     *  @access private
     */
    private function _calcularVariacaoAngular($avaliacoes) {
        $I = 0;
        $postagem = 1;
        $m = $this->_glossariolv->fator_multiplicativo / 2;
        $carinhas = array('azul'=>0, 'verde'=>0, 'amarela'=>0, 'laranja'=>0, 'vermelha'=>0, 'preta'=>0);

        foreach ($avaliacoes as $avaliacao) {
            $coeficiente_passo = $avaliacao->rating;

            switch($coeficiente_passo) {
                case EscalaLikert::MUITO_BOM:
                    $carinhas['azul']++; break;
                case EscalaLikert::BOM:
                    $carinhas['verde']++; break;
                case EscalaLikert::REGULAR:
                    $carinhas['amarela']++; break;
                case EscalaLikert::FRACO:
                    $carinhas['laranja']++; break;
                case EscalaLikert::NAO_SATISFATORIO:
                    $carinhas['vermelha']++; break;
                case EscalaLikert::NEUTRO:
                    $carinhas['preta']++;
            }
  
            if ($coeficiente_passo != EscalaLikert::NEUTRO) {
                if ($postagem == 1 || $postagem == 2) { // Primeira Postagem ou Segunda Postagem
                    $I += ($m * $coeficiente_passo) * AtividadeLv::ALFA;
                } else {
                    $I += ($coeficiente_passo < 2) ? -AtividadeLv::ALFA : AtividadeLv::ALFA;
                } 
                $postagem++;
            }
        }

        $I = $this->limitarAoQuadrante($I);

        return array($I, $carinhas);
    }
}
?>

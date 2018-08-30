<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\business\GerenciadorAtividadesDistancia;

/**
 * 	Representa e opera sobre um conjunto de glossários lvs pertencentes ao mesmo curso
 *	
 *	@category LVs
 *	@package uab\ifce\lvs\moodle2\business
 *	@author Fiuza Junior
  *	@version SVN $Id
 */
class GlossariosLv extends GerenciadorAtividadesDistancia {

	const NOME = 'Glossário LV'; 
	
	/**
	 * 	Nome da tabela do banco de dados que possui a configuração lv de todos os glossarioslv
	 * 	@var string
	 */
	private $_tabelaConfiguracao;
	
	/**
	 * 	Nome da tabela do banco de dados que possui a avaliação lv dos estudantes avaliados
	 * 	@var string
	 */
	private $_tabelaAvaliacao;
	
	/**
	 * 	Nome da tabela do banco de dados que possui todas as notas lvs dadas
	 * 	@var string
	 */
	private $_tabelaNota;
	
	/**
	 * @param CursoLv $cursolv
	 */
	public function __construct($cursolv) {
		$this->_cursolv 			= $cursolv;
		$this->_tabelaNota 			= 'rating';
		$this->_tabelaAvaliacao 	= 'lvs_glossariolv';
		$this->_tabelaConfiguracao 	= 'glossarylv';
	}
	
	/**
	 *	Retorna o total de ausências de um estudante nos glossarioslvs do curso
	 *
	 *	@param int $estudante id do estudante
	 * 	@return int número de faltas
	 * 	@access public
	 */
	public function numeroFaltas($estudante) {
		return 0;
	}
	
	/**
	 *	Calcula a soma das porcentagens a distancia de todos os glossarioslvs do curso
	 *
	 *  @return float
	 *  @access public
	 */
	public function porcentagemDistancia() {
		return 0;
	}
	
	/**
	 * 	Verifica se há algum glossariolv do curso cuja porcentagem não tenha sido definida
	 *
	 * 	@return boolean true, caso haja, false, caso contrário
	 *  @access public
	 */
	public function porcentagemNula() {
		return 0;
	}
	
	/**
	 * 	Retorna o número de glossários lvs do curso
	 *
	 * 	@return int
	 * 	@access public
	 */
	public function quantidadeAtividades() {
		return 0;
	}
	
	/**
	 *	Retorna todos os glossárioslvs do curso
	 *	
	 * 	@return array
	 * 	@access public
	 */
	public function recuperarAtividades( ) {
		return new \stdClass();
	}

	/**
	 *	Retorna o desempenho de um estudante em cada glossariolv avaliado
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return array:\stdClass cada elemento representa uma avaliação
	 * 	@access public
	 */
	public function recuperarAvaliacoes( $estudante ) {
		return new \stdClass();
	}

	/**
	 * 	Retorna a configuração lv de um ou mais glossários lvs
	 *
	 * 	@param mixed glossarioslvs um glossariolv ou um array de gossarioslvs
	 * 	@return array
	 * 	@access public
	 */
	public function recuperarConfiguracao( $glossarioslvs ) {
		return new \stdClass();
	}
	
	/**
	 *	Retorna o desempenho geral de um estudante no conjunto de glossários lvs
	 *
	 * 	@param int $estudante id do estudante
	 * 	@access public
	 */
	public function recuperarDesempenho( $estudante ) {
		return new \stdClass();
	}
	
	/* 
	 * 	@see uab\ifce\lvs\business.GerenciadorAtividadesDistancia::recuperarDesempenhoPorAtividade()
	 */
	public function recuperarDesempenhoPorAtividade( $estudante ) {
		return new \stdClass();
	}
	
	/**
	 *	Armazena ou atualiza as configurações lvs de cada glossariolv tais como porcentagem, etapa, exibição de notas,
	 *	fator multiplicativo e período de avaliação
	 *
	 * 	@param mixed atividades
	 * 	@access public
	 */
	public function salvarConfiguracao( $glossarioslvs ) {}

	/**
	 *
	 *
	 * @param int atividade_id
	 * @return
	 * @access public
	 */
	public function removerAtividade( $atividade_id ) {
		nao_implementado(__CLASS__, __FUNCTION__, E_USER_ERROR);
	}
	
	/**
	 * 	Calcula a nota final nos glossarioslvs por meio da soma das notas de cada glossariolv. A soma das notas é ponderada por meio da porcentagem
	 * 	de cada glossariolv.
	 *
	 * 	@param array glossarioslvs avaliados
	 * 	@return float soma ponderada das notas
	 */
	private function _calcularNotaPonderada($avaliacoes) {
		return 0;
	}
	
}
?>
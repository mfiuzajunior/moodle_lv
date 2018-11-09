<?php
namespace uab\ifce\lvs\moodle2\avaliacao;

use uab\ifce\lvs\moodle2\avaliacao\Moodle2NotasLv;
use uab\ifce\lvs\forms\FormsAvaliacaoImpl;
use uab\ifce\lvs\util\Cache;
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\business\AtividadeLv;
use uab\ifce\lvs\util\ChromePhp;

class AvaliarComentarioNotasLv extends Moodle2NotasLv {
	public function podeAvaliar( Item $item ) {
		//echo print_r($item, true);
		global $USER, $cm, $context;

		$contribuicao = $this->getModulo()->contribuicao($item);

		// Verifica se usuário logado tem permissão para avaliar. A permissão checada aqui normalmente é dada a professores. Logo, professores não podem avaliar comentários.
		if (has_capability("mod/$cm->modname:rate", $context)){
			return false;
		}

		// O usuário não pode avaliar o próprio comentário. Verifica se o comentário a ser avaliado foi criado pelo usuário.
		if ($contribuicao && $item->getItem()->userid == $USER->id) {
			return false;
		}

		// O usuário só pode avaliar comentários feitos a sua entrada de glossário.
		if($contribuicao && $item->getItem()->userid == $USER->id){

		}

		// Não pode avaliar comentários feitos por um professor/tutor
		if( $this->comentarioDeProfessor( $context, $item->getItem()->userid ) ){
			return false;
		}

		return true;
	}

	private function comentarioDeProfessor( $context, $userid ){
		if ($roles = get_user_roles($context, $userid)) {
			foreach ($roles as $role) {
				if( $role->shortname == 'editingteacher' ){
					return true;
				}
			}
		}
	}

	/* 
	 * @see uab\ifce\lvs\avaliacao.NotasLv::podeVerNota()
	 */
	public function podeVerNota( Item $item ) {
		global $cm, $context, $USER;
		$contribuicao = $this->getModulo()->contribuicao($item);

		if(!isset($context)) 
			$context = \context_module::instance($cm->id);

		if(!has_capability("mod/$cm->modname:viewrating", $context))
			return false;

		// verifica se o item pertence ao usuário logado
		if ($contribuicao && $item->getItem()->userid != $USER->id)
			return false;

		// Verifica se usuário logado tem permissão para avaliar. A permissão checada aqui normalmente é dada a professores. Logo, professores não podem ver as notas dos comentários.
		if (has_capability("mod/$cm->modname:rate", $context)){
			return false;
		}

		return $this->getModulo()->podeVerNota($item);
	}
}
?>

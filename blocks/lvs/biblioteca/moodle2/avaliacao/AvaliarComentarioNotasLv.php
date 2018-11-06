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

		// Verifica se o item a ser avaliado foi criado pelo usuário que deseja avaliá-lo
		if ($contribuicao && $item->getItem()->userid == $USER->id) {
			return false;
		}

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
}
?>

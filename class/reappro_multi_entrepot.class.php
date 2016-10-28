<?php

class TReapproMultiEntrepot extends TObjetStd {
	
	static $TStatus=array(
							0=>'Non ventilé'
							,1=>'Ventilé'
						);
	
	function __construct($db) {
		
		$this->db = $db;
		
		$this->set_table(MAIN_DB_PREFIX.'reappro_multi_entrepot');
		
		$this->add_champs('fk_entrepot_a_reappro,fk_statut','type=entier;index;');
		$this->add_champs('TEntrepotSource,TFormulaire','type=text;');
		$this->_init_vars();
		$this->start();
		
	}
	
}

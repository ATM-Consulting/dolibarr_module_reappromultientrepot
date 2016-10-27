<?php

require './config.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
dol_include_once('/reappromultientrepot/lib/reappromultientrepot.lib.php');

$action = GETPOST('action');

/**
 * Actions
 */

llxHeader();

$form = new FormProduct($db);

switch($action) {
	
	case 'list':
		_liste_reappro();
		break;
	
	case 'new':
		_fiche('new');
		break;
	
	default :
		_fiche();
	
}

llxFooter();
 
/**
 * Functions
 */

function _fiche($mode='view') {
	
	global $langs, $form;
	
	$head = reappromultientrepotPrepareHead();
	dol_fiche_head($head, 'Module104993Name', $langs->trans("Module104993Name"), 0, 'Module104993Name');
	
	print '<form name="Calcul" method="POST" action="" />';
	
	print '<table class="border" width="100%">';
	
	// Entrepôt à réapprovisionner
	print '<tr>';
	print '<td>';
	print $langs->trans('reappromultientrepotTo');
	print '</td>';
	print '<td>';
	print $form->selectWarehouses();
	print '</td>';
	print '</tr>';
	
	
	// Entrepôt sources
	print '<tr>';
	print '<td>';
	print $langs->trans('reappromultientrepotFrom');
	print '</td>';
	print '<td>';
	
	print '</td>';
	print '</tr>';
	
	print '</table>';
	
	print '</form>';
	
}

function _liste_reappro() {
	
	
	
}

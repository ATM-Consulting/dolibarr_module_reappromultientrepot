<?php

require './config.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
dol_include_once('/reappromultientrepot/lib/reappromultientrepot.lib.php');

$action = GETPOST('action');
$fk_entrepot_a_reappro = GETPOST('fk_entrepot_a_reappro');
$TEntrepotSource = GETPOST('TEntrepotSource');

/**
 * Actions
 */

llxHeader();

$form = new Form($db);
$formProduct = new FormProduct($db);

switch($action) {
	
	case 'list':
		_liste_reappro();
		break;
	
	case 'new':
		_fiche('new');
		break;
	
	case 'calcul':
		$TProductsToReappro = _get_products_to_reappro($fk_entrepot_a_reappro, $TEntrepotSource);
		_fiche('new');
		_fiche_calcul($TProductsToReappro);
		break;
	
	default :
		_fiche();
	
}

llxFooter();
 
/**
 * Functions
 */

function _fiche($mode='view') {
	
	global $db, $langs, $form, $formProduct;
	
	$head = reappromultientrepotPrepareHead();
	dol_fiche_head($head, 'Module104993Name', $langs->trans("Module104993Name"), 0, 'Module104993Name');
	
	$e = new Entrepot($db);
	
	print '<form name="Calcul" method="POST" action="?id='.GETPOST('id').'" />';
	
	print '<table class="border" width="100%">';
	
	// Entrepôt à réapprovisionner
	print '<tr>';
	print '<td>';
	print $langs->trans('reappromultientrepotTo');
	print '</td>';
	print '<td>';
	print $formProduct->selectWarehouses(GETPOST('fk_entrepot_a_reappro'), 'fk_entrepot_a_reappro', '', 1);
	print '</td>';
	print '</tr>';
	
	
	// Entrepôt sources
	print '<tr>';
	print '<td>';
	print $langs->trans('reappromultientrepotFrom');
	print '</td>';
	print '<td>';
	print $form->multiselectarray('TEntrepotSource', $e->list_array(), GETPOST('TEntrepotSource'), 0, 0, '', 0, 250);
	print '</td>';
	print '</tr>';
	
	print '</table>';
	
	print '<br /><div class="center">';
	print '<input type="hidden" name="action" value="calcul" />';
	print '<input type="SUBMIT" class="button" name="btFormCalcul" value="'.$langs->trans('reappromultientrepotCalcul').'" />';
	print '</div>';
	
	print '</form>';
	
}

function _fiche_calcul(&$TProductsToReappro) {
	
	global $db, $langs, $bc;
	
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Product').'</td>';
	print '<td>'.$langs->trans('Warehouse').'</td>';
	print '<td>'.$langs->trans('Stock').'</td>';
	print '<td>'.$langs->trans('StockLimit').'</td>';
	print '<td>'.$langs->trans('DesiredStock').'</td>';
	print '<td>'.$langs->trans('Qté à tranférer').'</td>';
	print '<td>'.$langs->trans('Entrepôt source').'</td>';
	print '</tr>';
	
	foreach($TProductsToReappro as $fk_product=>$TData) {
		
		$p = new Product($db);
		$p->fetch($fk_product);		
		$first = true;
		
		foreach($TData as $TInfosProd) {
			
			//print '<tr '.$bc[$var].'>';
			print '<tr>';
			$e = new Entrepot($db);
			$e->fetch($TInfosProd['fk_entrepot']);
			if($first) print '<td>'.$p->getNomUrl(1).'</td>';
			else print '<td></td>';
			print '<td>'.$e->getNomUrl(1).'</td>';
			print '<td>'.$TInfosProd['reel'].'</td>';
			print '<td>'.$TInfosProd['seuil_stock_alerte'].'</td>';
			print '<td>'.$TInfosProd['desiredstock'].'</td>';
			print '<td>'.$TInfosProd['qty_to_reappro'].'</td>';
			print '<td>ent_source</td>';
			print '</tr>';
			
			$first = false;
			
		}
		
		$var=!$var;
		
	}
	
	print '</table>';
	
}

function _get_products_to_reappro($id_entrepot_to_reappro, $TEntrepotSource) {
	
	global $db;
	
	$entrepot_to_reappro = new Entrepot($db);
	$entrepot_to_reappro->fetch($id_entrepot_to_reappro);
	
	$TChildWarehouses = array($entrepot_to_reappro->id);
	$entrepot_to_reappro->get_children_warehouses($entrepot_to_reappro->id, $TChildWarehouses);
	
	// Pour chacun de ces entrepôts, on va vérifier les produits qui ont une limite définie : on récupère tous les produits à réapprovisionner
	$sql = 'SELECT pse.fk_product, pse.fk_entrepot, ps.reel
				   , pse.seuil_stock_alerte, pse.desiredstock
				   , ABS(IFNULL(ps.reel, 0) - pse.seuil_stock_alerte) as qty_to_reappro
			FROM '.MAIN_DB_PREFIX.'product_stock_entrepot pse
			LEFT JOIN '.MAIN_DB_PREFIX.'product_stock ps ON (pse.fk_entrepot = ps.fk_entrepot AND pse.fk_product = ps.fk_product)
			WHERE pse.fk_entrepot IN('.implode(', ', $TChildWarehouses).')
			AND (ps.reel IS NULL OR ps.reel < pse.seuil_stock_alerte)';
	$resql = $db->query($sql);
	
	$TProductsToReappro = array();
	while($res = $db->fetch_object($resql)) {
		$TProductsToReappro[$res->fk_product][] = array('fk_product'=>$res->fk_product
										  				, 'fk_entrepot'=>$res->fk_entrepot
										  				, 'seuil_stock_alerte'=>$res->seuil_stock_alerte
										  				, 'desiredstock'=>$res->desiredstock
										  				, 'reel'=>$res->reel
														, 'qty_to_reappro'=>$res->qty_to_reappro);
	}

	return $TProductsToReappro;
	
}

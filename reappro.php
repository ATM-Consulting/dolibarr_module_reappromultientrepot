<?php

require './config.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
dol_include_once('/reappromultientrepot/lib/reappromultientrepot.lib.php');
dol_include_once('/reappromultientrepot/class/reappro_multi_entrepot.class.php');

$action = GETPOST('action');
$fk_entrepot_a_reappro = GETPOST('fk_entrepot_a_reappro');
$TEntrepotSource = GETPOST('TEntrepotSource');

/**
 * Actions
 */

//pre($_REQUEST, true);

llxHeader();

$form = new Form($db);
$formProduct = new FormProduct($db);
$ATMdb = new TPDOdb;
$reappro = new TReapproMultiEntrepot;
$reappro->load($ATMdb, GETPOST('id'));

if(empty($action)) $action = 'view';

switch($action) {
	
	case 'list':
		_liste_reappro();
		break;
	
	case 'new':
		_fiche($reappro);
		break;
	
	case 'calcul':
		$TProductsToReappro = _get_products_to_reappro($fk_entrepot_a_reappro, $TEntrepotSource);
		_fiche($reappro, $action);
		_fiche_calcul($reappro, $TProductsToReappro, $TEntrepotSource, 'new');
		break;
	
	case 'save':
		$reappro->fk_entrepot_a_reappro = GETPOST('fk_entrepot_a_reappro');
		$reappro->TEntrepotSource = serialize(GETPOST('TEntrepotSource'));
		$reappro->TFormulaire = serialize(GETPOST('TFormulaire'));
		
		$btVentil = GETPOST('btVentil');
		
		if(empty($reappro->fk_statut)){ // On ne touche le reappro uniquement s'il n'est pas déjà ventilé
		
			$reappro->save($ATMdb);
			
			if(!empty($btVentil)) {
				
				//Ventilation
				_ventilation($reappro, GETPOST('TFormulaire'));
				$reappro->fk_statut = 1;
				$reappro->save($ATMdb);
				
			}
		
		}
	
	case 'view':
		_fiche($reappro, 'view');
		_fiche_calcul($reappro, unserialize($reappro->TFormulaire), unserialize($reappro->TEntrepotSource), 'view');
		_print_document_list($reappro);
		break;
	
	case 'remove_file':
		$upload_dir =	$conf->reappromultientrepot->dir_output;
		$file =	$upload_dir	. '/' .	GETPOST('file');
		$ret=dol_delete_file($file,0,0,0,$object);
		_fiche($reappro, 'view');
		_fiche_calcul($reappro, unserialize($reappro->TFormulaire), unserialize($reappro->TEntrepotSource), 'view');
		_print_document_list($reappro);
		break;
	
	case 'builddoc':
		_builddoc($reappro);
	
	default :
		_fiche($reappro, 'view');
		_fiche_calcul($reappro, unserialize($reappro->TFormulaire), unserialize($reappro->TEntrepotSource), 'view');
		_print_document_list($reappro);
		break;
	
}

llxFooter();
 
/**
 * Functions
 */

function _fiche(&$reappro, $mode='view') {
	
	global $db, $langs, $form, $formProduct;
	
	$head = reappromultientrepotPrepareHead($reappro->rowid);
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
	print $formProduct->selectWarehouses($mode === 'view'
										  ? $reappro->fk_entrepot_a_reappro
										  : GETPOST('fk_entrepot_a_reappro'), 'fk_entrepot_a_reappro', '', 1);
	print '</td>';
	print '</tr>';
	
	
	// Entrepôt sources
	print '<tr>';
	print '<td>';
	print $langs->trans('reappromultientrepotFrom');
	print '</td>';
	print '<td>';
	print $form->multiselectarray('TEntrepotSource', $e->list_array()
									, $mode === 'view'
									? unserialize($reappro->TEntrepotSource)
									: GETPOST('TEntrepotSource'), 0, 0, '', 0, 250);
	print '</td>';
	print '</tr>';
	
	if(!empty($reappro->rowid)) {
		
		print '<tr '.(!empty($reappro->fk_statut) ? 'bgcolor="#00CC00"' : '').'>';
		print '<td>';
		print $langs->trans('Status');
		print '</td>';
		print '<td>';
		print TReapproMultiEntrepot::$TStatus[$reappro->fk_statut];;
		print '</td>';
		print '</tr>';
	
	}
	print '</table>';
	
	print '<br />';
	
	if(empty($reappro->fk_statut)) {
		
		print '<div class="center">';
		print '<input type="hidden" name="action" value="calcul" />';
		print '<input type="SUBMIT" class="button" name="btCalcul" value="'.$langs->trans('reappromultientrepotCalcul').'" />';
		print '</div>';
		
	}
	print '</form>';
	
}

function _fiche_calcul(&$reappro, &$TProductsToReappro, &$TEntrepotSource, $mode='create') {
	
	global $db, $langs, $formProduct, $bc;

	print '<form name="Save" method="POST" action="?id='.GETPOST('id').'" />';
	
	print '<br />';
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
		
		$i=0;
		foreach($TData as $TInfosProd) {
			
			//print '<tr '.$bc[$var].'>';
			print '<tr>';
			$e = new Entrepot($db);
			$e->fetch($TInfosProd['fk_entrepot_to_reappro']);
			if($first) print '<td>'.$p->getNomUrl(1).'</td>';
			else print '<td></td>';
			print '<td>'.$e->getNomUrl(1).'</td>';
			print '<td>'.$TInfosProd['reel'].'</td>';
			print '<td>'.$TInfosProd['seuil_stock_alerte'].'</td>';
			print '<td>'.$TInfosProd['desiredstock'].'</td>';
			
			print '<td>';
			print '<input type="text" size="4" name="TFormulaire['.$fk_product.']['.$i.'][qty_to_reappro]" value="'.$TInfosProd['qty_to_reappro'].'" />';
			print '<input type="hidden" size="4" name="TFormulaire['.$fk_product.']['.$i.'][fk_product]" value="'.$fk_product.'" />';
			print '<input type="hidden" size="4" name="TFormulaire['.$fk_product.']['.$i.'][fk_entrepot_to_reappro]" value="'.$TInfosProd['fk_entrepot_to_reappro'].'" />';
			print '<input type="hidden" size="4" name="TFormulaire['.$fk_product.']['.$i.'][seuil_stock_alerte]" value="'.$TInfosProd['seuil_stock_alerte'].'" />';
			print '<input type="hidden" size="4" name="TFormulaire['.$fk_product.']['.$i.'][desiredstock]" value="'.$TInfosProd['desiredstock'].'" />';
			print '<input type="hidden" size="4" name="TFormulaire['.$fk_product.']['.$i.'][reel]" value="'.$TInfosProd['reel'].'" />';
			print '</td>';
			
			if($mode === 'new') $id_right_entrepot = _get_right_warehouse_to_reappro($p, $TEntrepotSource, $TInfosProd['qty_to_reappro']);
			elseif($mode === 'view') $id_right_entrepot = $TInfosProd['fk_entrepot'];
			 
			print '<td>'.$formProduct->selectWarehouses($id_right_entrepot, 'TFormulaire['.$fk_product.']['.$i.'][fk_entrepot]', '', 1).'</td>';
			print '</tr>';
			
			$first = false;
			
			$i++;
			
		}
		
		$var=!$var;
		
	}
	
	print '</table>';
	
	print '<br />';
	
	if(empty($reappro->fk_statut)) {
		
		print '<div class="center">';
		print '<input type="hidden" name="action" value="save" />';
		print '<input type="hidden" name="fk_entrepot_a_reappro" value="'.($mode === 'view' ? $reappro->fk_entrepot_a_reappro : GETPOST('fk_entrepot_a_reappro')).'" />';
		if(!empty($TEntrepotSource)) {
			foreach($TEntrepotSource as $id_ent)
				print '<input type="hidden" name="TEntrepotSource[]" value="'.$id_ent.'" />';
		}
		print '<input type="SUBMIT" class="button" name="btSave" value="'.$langs->trans('Save').'" />';
		if($mode === 'view') print ' <input type="SUBMIT" class="button" name="btVentil" value="'.$langs->trans('reappromultientrepotVentilation').'" />';
		print '</div>';
		
	}

	print '</form>';
	
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
										  				, 'fk_entrepot_to_reappro'=>$res->fk_entrepot
										  				, 'seuil_stock_alerte'=>$res->seuil_stock_alerte
										  				, 'desiredstock'=>$res->desiredstock
										  				, 'reel'=>$res->reel
														, 'qty_to_reappro'=>$res->qty_to_reappro);
	}

	return $TProductsToReappro;
	
}

function _get_right_warehouse_to_reappro(&$p, &$TEntrepotSource, $qty) {
	
	global $db;
	
	$p->load_stock();
	
	foreach($TEntrepotSource as $id_ent) {
		
		$entrepot_for_reappro = new Entrepot($db);
		$entrepot_for_reappro->fetch($id_ent);
		
		$TChildWarehouses = array($entrepot_for_reappro->id);
		$entrepot_for_reappro->get_children_warehouses($entrepot_for_reappro->id, $TChildWarehouses);
		
		// Parmis tous les entrepôts de cette famille, on vérifie si l'un d'eux a la quantité que je souhaite
		foreach($TChildWarehouses as $id_entrepot) {
			
			if($p->stock_warehouse[$id_entrepot]->real >= $qty) return $id_entrepot;
			
		}
		
	}
	
}

function _liste_reappro() {
	
	global $db, $ATMdb, $langs;
	
	$l = new TListviewTBS('tagada');
	
	$sql = 'SELECT rowid, date_cre, fk_entrepot_a_reappro, fk_statut
			FROM '.MAIN_DB_PREFIX.'reappro_multi_entrepot
			ORDER BY rowid';
	
	print $l->render($ATMdb, $sql, array(
		'type'=>array(
			'date_cre'=>'date'
		)
		,'link'=>array(
			'rowid'=>'<a href="'.dol_buildpath('/reappromultientrepot/reappro.php?id=@rowid@&action=view', 1).'" >@rowid@'
		)
		,'eval'=>array(
			'fk_entrepot_a_reappro'=>'_getNomUrl("@val@")'
			,'fk_statut'=>'TReapproMultiEntrepot::$TStatus["@val@"]'
		)
		,'liste'=>array(
			'titre'=>$langs->trans('reappromultientrepotList')
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> (int)isset($_REQUEST['fk_soc']) | (int)isset($_REQUEST['fk_product'])
			,'messageNothing'=>"Il n'y a aucun ".$langs->trans('Module104993Name')." à afficher"
			,'picto_search'=>img_picto('','search.png', '', 0)
		)
		,'title'=>array(
			'rowid'=>'Numéro'
			,'date_cre'=>'Date création'
			,'fk_entrepot_a_reappro'=>'Entrepôt à réapprovisionner'
			,'fk_statut'=>'Statut'
		)
	));
	
}

function _getNomUrl($id) {
	
	global $db;
	
	$e = New Entrepot($db);
	$e->fetch($id);
	
	return $e->getNomUrl(1);
		
}

function _ventilation(&$reappro, $TFormulaire) {
	
	global $db, $user;
	
	if(!empty($TFormulaire)) {
		
		$nb_ok=0;
		
		foreach($TFormulaire as $fk_product=>$TData) {
			$p = new Product($db);
			$p->fetch($fk_product);
			
			foreach($TData as $TInfos) {
				
				if($TInfos['fk_entrepot'] <= 0) continue;
				
				// Remove stock
				$result1=$p->correct_stock(
					$user,
					$TInfos['fk_entrepot'],
					$TInfos['qty_to_reappro'],
					1,
					'Réapprovisionnement avancé n° <a href="'.dol_buildpath('/reappromultientrepot/reappro.php?id='.$reappro->rowid, 1).'">'.$reappro->rowid.'</a>'
				);
				
				// Add stock
				$result2=$p->correct_stock(
					$user,
					$TInfos['fk_entrepot_to_reappro'],
					$TInfos['qty_to_reappro'],
					0,
					'Réapprovisionnement avancé n° <a href="'.dol_buildpath('/reappromultientrepot/reappro.php?id='.$reappro->rowid, 1).'">'.$reappro->rowid.'</a>'
				);
				
				if($result1 > 0 && $result2 > 0) $nb_ok++;
				
			}
					
		}
		
		if($nb_ok > 0) {
			setEventMessage($nb_ok.' mouvements de stock effectués');
			// TODO cloturer réappro
		}
		
	}
	
}

function _builddoc(&$reappro) {
	
	global $db, $langs;
	
	$reappro->generateDocument();

}

function _print_document_list(&$reappro) {
	
	global $db,$conf,$user; 
	
	$formfile = new FormFile($db);
	
	$filedir = $conf->reappromultientrepot->dir_output . "/" .$reappro->rowid;

	$urlsource = $_SERVER["PHP_SELF"]."?id=".$reappro->rowid;

	$genallowed=$user->rights->reappromultientrepot->lire;
	$delallowed=$user->rights->reappromultientrepot->supprimer;
	$genallowed=$delallowed=1; // TODO créer droit
	$somethingshown=$formfile->show_documents('reappromultientrepot',$reappro->rowid,$filedir,$urlsource,$genallowed,$delallowed,'',1,0,0,28,0,'','','',$soc->default_lang);
	
}

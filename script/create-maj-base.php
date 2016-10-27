<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */

if(!defined('INC_FROM_DOLIBARR')) {
	define('INC_FROM_CRON_SCRIPT', true);

	require('../config.php');

}

dol_include_once('/reappromultientrepot/class/reappro_multi_entrepot.class.php');

$PDOdb=new TPDOdb;

$o=new TReapproMultiEntrepot($PDOdb);
$o->init_db_by_vars($PDOdb);
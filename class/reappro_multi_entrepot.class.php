<?php

class TReapproMultiEntrepot extends TObjetStd {
	
	static $TStatus=array(
							0=>'Non ventilé'
							,1=>'Ventilé'
						);
	
	function __construct() {
		
		$this->set_table(MAIN_DB_PREFIX.'reappro_multi_entrepot');
		
		$this->add_champs('fk_entrepot_a_reappro,fk_statut','type=entier;index;');
		$this->add_champs('TEntrepotSource,TFormulaire','type=text;');
		$this->_init_vars();
		$this->start();
		
	}
	
	/**
	 *  Create a document onto disk according to template module.
	 *
	 *  @param	    string		$modele			Force the model to using ('' to not force)
	 *  @param		Translate	$outputlangs	object lang to use for translations
	 *  @param      int			$hidedetails    Hide details of lines
	 *  @param      int			$hidedesc       Hide description
	 *  @param      int			$hideref        Hide ref
	 *  @return     int         				0 if KO, 1 if OK
	 */
	function generateDocument($modele = 'fiche_reappro', $hidedetails=0, $hidedesc=0, $hideref=0) {
		
		global $conf,$user,$langs;

		$langs->load("sendings");

		$modelpath = "core/modules/reappromultientrepot/doc/";

		//$this->fetch_origin();

		return $this->commonGenerateDocument($modelpath, $modele, $langs, $hidedetails, $hidedesc, $hideref);
	}

	/**
	 * Common function for all objects extending CommonObject for generating documents
	 *
	 * @param 	string 		$modelspath 	Relative folder where generators are placed
	 * @param 	string 		$modele 		Generator to use. Caller must set it to obj->modelpdf or GETPOST('modelpdf') for example.
	 * @param 	Translate 	$outputlangs 	Language to use
	 * @param 	int 		$hidedetails 	1 to hide details. 0 by default
	 * @param 	int 		$hidedesc 		1 to hide product description. 0 by default
	 * @param 	int 		$hideref 		1 to hide product reference. 0 by default
	 * @return 	int 						>0 if OK, <0 if KO
	 */
	function commonGenerateDocument($modelspath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref) {
		
		global $conf, $langs;

		$srctemplatepath='';

		// Increase limit for PDF build
		$err=error_reporting();
		error_reporting(0);
		@set_time_limit(120);
		error_reporting($err);

		// If selected model is a filename template (then $modele="modelname" or "modelname:filename")
		$tmp=explode(':',$modele,2);
		if (! empty($tmp[1]))
		{
			$modele=$tmp[0];
			$srctemplatepath=$tmp[1];
		}

		// Search template files
		$file=''; $classname=''; $filefound=0;
		$dirmodels=array('/');
		if (is_array($conf->modules_parts['models'])) $dirmodels=array_merge($dirmodels,$conf->modules_parts['models']);
		foreach($dirmodels as $reldir)
		{
			foreach(array('doc','pdf') as $prefix)
			{
				$file = $prefix."_".$modele.".modules.php";

				// On verifie l'emplacement du modele
				$file=dol_buildpath($reldir.$modelspath.$file,0);
				if (file_exists($file))
				{
					$filefound=1;
					$classname=$prefix.'_'.$modele;
					break;
				}
			}
			if ($filefound) break;
		}

		// If generator was found
		if ($filefound)
		{
			require_once $file;

			$obj = new $classname($this->db);

			// If generator is ODT, we must have srctemplatepath defined, if not we set it.
			if ($obj->type == 'odt' && empty($srctemplatepath))
			{
				$varfortemplatedir=$obj->scandir;
				if ($varfortemplatedir && ! empty($conf->global->$varfortemplatedir))
				{
					$dirtoscan=$conf->global->$varfortemplatedir;

					$listoffiles=array();

					// Now we add first model found in directories scanned
	                $listofdir=explode(',',$dirtoscan);
	                foreach($listofdir as $key=>$tmpdir)
	                {
	                    $tmpdir=trim($tmpdir);
	                    $tmpdir=preg_replace('/DOL_DATA_ROOT/',DOL_DATA_ROOT,$tmpdir);
	                    if (! $tmpdir) { unset($listofdir[$key]); continue; }
	                    if (is_dir($tmpdir))
	                    {
	                        $tmpfiles=dol_dir_list($tmpdir,'files',0,'\.od(s|t)$','','name',SORT_ASC,0);
	                        if (count($tmpfiles)) $listoffiles=array_merge($listoffiles,$tmpfiles);
	                    }
	                }

	                if (count($listoffiles))
	                {
	                	foreach($listoffiles as $record)
	                    {
	                    	$srctemplatepath=$record['fullname'];
	                    	break;
	                    }
	                }
				}

				if (empty($srctemplatepath))
				{
					$this->error='ErrorGenerationAskedForOdtTemplateWithSrcFileNotDefined';
					return -1;
				}
			}

            if ($obj->type == 'odt' && ! empty($srctemplatepath))
            {
                if (! dol_is_file($srctemplatepath))
                {
                    $this->error='ErrorGenerationAskedForOdtTemplateWithSrcFileNotFound';
                    return -1;
                }
            }

			// We save charset_output to restore it because write_file can change it if needed for
			// output format that does not support UTF8.
			$sav_charset_output=$outputlangs->charset_output;
			if ($obj->write_file($this, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref) > 0)
			{
				$outputlangs->charset_output=$sav_charset_output;

				// We delete old preview
				require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
				dol_delete_preview($this);

				// Success in building document. We build meta file.
				dol_meta_create($this);

				return 1;
			}
			else
			{
				$outputlangs->charset_output=$sav_charset_output;
				dol_print_error($this->db, "Error generating document for ".__CLASS__.". Error: ".$obj->error, $obj->errors);
				return -1;
			}

		}
		else
		{
			$this->error=$langs->trans("Error")." ".$langs->trans("ErrorFileDoesNotExists",$file);
			dol_print_error('',$this->error);
			return -1;
		}
	}
	
	
}

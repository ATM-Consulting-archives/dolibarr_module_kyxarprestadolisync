<?php

if (!class_exists('TObjetStd'))
{
	/**
	 * Needed if $form->showLinkedObjectBlock() is call
	 */
	//define('INC_FROM_DOLIBARR', true);
	//require_once dirname(__FILE__).'/../config.php';
}


class TKyxarPrestaDoliSync extends TObjetStd
{
	/**
	 * To sync status
	 */
	const STATUS_TOSYNC = 0;
	/**
	 * Ok status (sync done and ok)
	 */
	const STATUS_OK = 1;
	/**
	 * Error status (sync tried and ko)
	 */
	const STATUS_ERROR = -1;
	
	public static $TStatus = array(
		self::STATUS_TOSYNC => 'Tosync'
		,self::STATUS_OK => 'Ok'
		,self::STATUS_ERROR => 'Error'
	);


	public function __construct()
	{
		global $conf,$langs,$db;
		
		$this->set_table(MAIN_DB_PREFIX.'kyxarprestadolisync');
		
		$this->add_champs('entity,fk_user_author', array('type' => 'integer', 'index' => true));
		
		$this->add_champs('element', array('type' => 'string', 'length' => 80, 'index' => true));
		$this->add_champs('fk_element, status', array('type' => 'integer'));
		$this->add_champs('sync_message', array('type' => 'string'));
		
		$this->_init_vars();
		$this->start();
		
		if (!class_exists('GenericObject')) require_once DOL_DOCUMENT_ROOT.'/core/class/genericobject.class.php';
		$this->generic = new GenericObject($db);
		$this->generic->table_element = $this->get_table();
		$this->generic->element = 'kyxarprestadolisync';
		
		$this->status = self::STATUS_DRAFT;
		$this->entity = $conf->entity;
	}

	public function save(&$PDOdb)
	{
		global $user;
		
		if (!$this->getId()) $this->fk_user_author = $user->id;
		
		$res = parent::save($PDOdb);
		
		return $res;
	}
	
	public function load(&$PDOdb, $id, $loadChild = false)
	{
		global $db;
		
		$res = parent::load($PDOdb, $id, $loadChild);
		
		$this->generic->id = $this->getId();
		
		if ($loadChild) $this->fetchObjectLinked();
		
		return $res;
	}
	
	public function delete(&$PDOdb)
	{
		$this->generic->deleteObjectLinked();
		
		parent::delete($PDOdb);
	}
	
	public function fetchObjectLinked()
	{
		$this->generic->fetchObjectLinked($this->getId());
	}
	
	public function setOk(&$PDOdb)
	{
		$this->status = self::STATUS_OK;
		
		return parent::save($PDOdb);
	}
	
	public function setError(&$PDOdb, $msg = '')
	{
		$this->status = self::STATUS_ERROR;
		$this->sync_message = $msg;
		
		return parent::save($PDOdb);
	}
	
	public function getLibStatut($mode=0)
    {
        return self::LibStatut($this->status, $mode);
    }
	
	public static function LibStatut($status, $mode)
	{
		global $langs;
		$langs->load('kyxarprestadolisync@kyxarprestadolisync');

		if ($status==self::STATUS_TOSYNC) { $statustrans='statut0'; $keytrans='KyxarPrestaDoliSyncStatusToSync'; $shortkeytrans='ToSync'; }
		if ($status==self::STATUS_OK) { $statustrans='statut1'; $keytrans='KyxarPrestaDoliSyncStatusOk'; $shortkeytrans='Ok'; }
		if ($status==self::STATUS_ERROR) { $statustrans='statut5'; $keytrans='KyxarPrestaDoliSyncStatusError'; $shortkeytrans='Error'; }

		
		if ($mode == 0) return img_picto($langs->trans($keytrans), $statustrans);
		elseif ($mode == 1) return img_picto($langs->trans($keytrans), $statustrans).' '.$langs->trans($keytrans);
		elseif ($mode == 2) return $langs->trans($keytrans).' '.img_picto($langs->trans($keytrans), $statustrans);
		elseif ($mode == 3) return img_picto($langs->trans($keytrans), $statustrans).' '.$langs->trans($shortkeytrans);
		elseif ($mode == 4) return $langs->trans($shortkeytrans).' '.img_picto($langs->trans($keytrans), $statustrans);
	}
	
	/*
	 * Ajout des info de l'objet modifié dans la table pour synchro
	 */
	public function add_object_to_sync(&$PDOdb, $object) {
		$this->element = $object->element;
		$this->fk_element = $object->id;
		$this->save($PDOdb);
	}
	
	public static function call_presta_for_sync(&$PDOdb) {
		global $conf, $db;
		
		$presta_url = $conf->global->KPDS_PRESTASHOP_URL;
		
		// Récupération des données à synchroniser
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->get_table()." ";
		$sql.= "WHERE status = 0";
		// $sql.= "LIMIT 10"; // Limiter le nombre d'objets à synchroniser en une fois ?
		
		$resql = $db->query($sql);
		
		while ($obj = $db->fetch_object($resql)) {
			$sync = new TKyxarPrestaDoliSync();
			$sync->load($PDOdb, $obj->rowid);
			
			$TData = array();
			$TData['element'] = $sync->element;
			$TData['element_id'] = $sync->fk_element;
			
			$url = $presta_url . '?' . http_build_query($TData);
			echo $url;
			/*$res = file_get_contents($url);
			
			if($res == 'OK') {
				$sync->setOk($PDOdb);
			} else {
				$sync->setError($PDOdb, $res);
			}*/
		}
	}
}
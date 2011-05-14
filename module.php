<?php
/**
 * Модуль "Менеджер файлов"
 * 
 * @version $Id$
 * @package Abricos
 * @subpackage FileManager
 * @copyright Copyright (C) 2008 Abricos All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @author Alexander Kuzmin (roosit@abricos.org)
 */

$mod = new FileManagerModule();
CMSRegistry::$instance->modules->Register($mod);

/**
 * Модуль "Менеджер файлов"
 * 
 * @package Abricos
 * @subpackage FileManager
 */
class FileManagerModule extends CMSModule {
	
	/**
	 * @var FileManager
	 */
	private $_fileManager = null;
	
	/**
	 * @var FileManagerModule
	 */
	public static $instance = null;
	
	public function __construct(){
		$this->version = "0.3.2";
		
		$this->name = "filemanager";
		$this->takelink = "filemanager";
		
		$this->permission = new FileManagerPermission($this);
		
		FileManagerModule::$instance = $this;
	}
	
	public function GetContentName(){
		$adress = $this->registry->adress;
		$cname = parent::GetContentName();
		
		if($adress->level > 2 && $adress->dir[1] == 'i'){
			$cname = 'file';
		}
		return $cname;
	}
	
	/**
	 * Получить менеджер
	 *
	 * @return FileManager
	 */
	public function GetFileManager(){
		return $this->GetManager();
	}
	
	/**
	 * Получить менеджер
	 *
	 * @return FileManager
	 */
	public function GetManager(){
		if (is_null($this->_fileManager)){
			require_once 'includes/manager.php';
			$this->_fileManager = new FileManager($this);
		}
		return $this->_fileManager;
	}
	
	public function EnableThumbSize($list){
		FileManagerQuery::EnThumbsAppend($this->registry->db, $list);
	}
}

class FileManagerAction {
	const FILES_VIEW = 10;
	const FILES_UPLOAD = 30;
	const FILES_ADMIN = 50;
}

class FileManagerPermission extends CMSPermission {
	
	public function FileManagerPermission(FileManagerModule $module){
		
		$defRoles = array(
			new CMSRole(FileManagerAction::FILES_VIEW, 1, User::UG_GUEST),
			new CMSRole(FileManagerAction::FILES_VIEW, 1, User::UG_REGISTERED),
			new CMSRole(FileManagerAction::FILES_VIEW, 1, User::UG_ADMIN),
			
			new CMSRole(FileManagerAction::FILES_UPLOAD, 1, User::UG_ADMIN),
			new CMSRole(FileManagerAction::FILES_ADMIN, 1, User::UG_ADMIN)
		);
		parent::CMSPermission($module, $defRoles);
	}
	
	public function GetRoles(){
		
		return array(
			FileManagerAction::FILES_VIEW => $this->CheckAction(FileManagerAction::FILES_VIEW),
			FileManagerAction::FILES_UPLOAD => $this->CheckAction(FileManagerAction::FILES_UPLOAD),
			FileManagerAction::FILES_ADMIN => $this->CheckAction(FileManagerAction::FILES_ADMIN) 
		);
	}
}

class FileManagerQuery {
	
	public static function EnThumbsAppend(CMSDatabase $db, $list){
		if (empty($list)){ return; }
		
		$values = array();
		foreach ($list as $size){
			array_push($values, "(".bkint($size['w']).",".bkint($size['h']).")");
		}
		$sql = "
			INSERT IGNORE INTO ".$db->prefix."fm_enthumbs 
			(`width`, `height`) VALUES
			".implode(",", $values)." 
			";
		$db->query_write($sql);
	}

}


?>
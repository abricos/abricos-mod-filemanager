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

/**
 * Модуль "Менеджер файлов"
 *
 * @package Abricos
 * @subpackage FileManager
 */
class FileManagerModule extends Ab_Module {

    /**
     * @var FileManager
     */
    private $_fileManager = null;

    /**
     * @var FileManagerModule
     */
    public static $instance = null;

    public function __construct() {
        FileManagerModule::$instance = $this;

        $this->version = "{C#VERSION}";

        $this->name = "filemanager";
        $this->takelink = "filemanager";

        $this->permission = new FileManagerPermission($this);
    }

    public function GetContentName() {
        $adress = Abricos::$adress;
        $cname = parent::GetContentName();

        if ($adress->level > 2 && $adress->dir[1] == 'i') {
            $cname = 'file';
        }

        return $cname;
    }

    /**
     * Получить менеджер
     *
     * @return FileManager
     */
    public function GetFileManager() {
        return $this->GetManager();
    }

    /**
     * Получить менеджер
     *
     * @return FileManager
     */
    public function GetManager() {
        if (is_null($this->_fileManager)) {
            require_once 'includes/manager.php';
            $this->_fileManager = new FileManager($this);
        }
        return $this->_fileManager;
    }

    public function EnableThumbSize($list) {
        FileManagerQueryModule::EnThumbsAppend(Abricos::$db, $list);
    }
}

class FileManagerAction {
    const FILES_VIEW = 10;
    const FILES_UPLOAD = 30;
    const FILES_ADMIN = 50;
}

class FileManagerPermission extends Ab_UserPermission {

    public function __construct(FileManagerModule $module) {

        $defRoles = array(
            new Ab_UserRole(FileManagerAction::FILES_VIEW, Ab_UserGroup::GUEST),
            new Ab_UserRole(FileManagerAction::FILES_VIEW, Ab_UserGroup::REGISTERED),
            new Ab_UserRole(FileManagerAction::FILES_VIEW, Ab_UserGroup::ADMIN),

            new Ab_UserRole(FileManagerAction::FILES_UPLOAD, Ab_UserGroup::ADMIN),
            new Ab_UserRole(FileManagerAction::FILES_ADMIN, Ab_UserGroup::ADMIN)
        );
        parent::__construct($module, $defRoles);
    }

    public function GetRoles() {

        return array(
            FileManagerAction::FILES_VIEW => $this->CheckAction(FileManagerAction::FILES_VIEW),
            FileManagerAction::FILES_UPLOAD => $this->CheckAction(FileManagerAction::FILES_UPLOAD),
            FileManagerAction::FILES_ADMIN => $this->CheckAction(FileManagerAction::FILES_ADMIN)
        );
    }
}

class FileManagerQueryModule {

    public static function EnThumbsAppend(Ab_Database $db, $list) {
        if (empty($list)) {
            return;
        }

        $values = array();
        foreach ($list as $size) {
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

Abricos::ModuleRegister(new FileManagerModule());

?>
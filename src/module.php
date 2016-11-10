<?php
/**
 * @package Abricos
 * @subpackage FileManager
 * @copyright 2008-2016 Alexander Kuzmin
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @author Alexander Kuzmin <roosit@abricos.org>
 */

/**
 * Модуль "Менеджер файлов"
 *
 * @package Abricos
 * @subpackage FileManager
 *
 * @method FileManager GetManager()
 */
class FileManagerModule extends Ab_Module {

    /**
     * @var FileManagerModule
     */
    public static $instance = null;

    public function __construct(){
        FileManagerModule::$instance = $this;

        $this->version = "0.3.7";

        $this->name = "filemanager";
        $this->takelink = "filemanager";

        $this->permission = new FileManagerPermission($this);
    }

    public function GetContentName(){
        $adress = Abricos::$adress;
        $cname = parent::GetContentName();

        if ($adress->level > 2 && $adress->dir[1] == 'i'){
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

    protected function GetManagerClassName(){
        return 'FileManager';
    }

    public function EnableThumbSize($list){
        FileManagerQueryModule::EnThumbsAppend(Abricos::$db, $list);
    }

    public function Bos_IsMenu(){
        return true;
    }
}

class FileManagerAction {
    const FILES_VIEW = 10;
    const FILES_UPLOAD = 30;
    const FILES_ADMIN = 50;
}

class FileManagerPermission extends Ab_UserPermission {

    public function __construct(FileManagerModule $module){

        $defRoles = array(
            new Ab_UserRole(FileManagerAction::FILES_VIEW, Ab_UserGroup::GUEST),
            new Ab_UserRole(FileManagerAction::FILES_VIEW, Ab_UserGroup::REGISTERED),
            new Ab_UserRole(FileManagerAction::FILES_VIEW, Ab_UserGroup::ADMIN),

            new Ab_UserRole(FileManagerAction::FILES_UPLOAD, Ab_UserGroup::ADMIN),
            new Ab_UserRole(FileManagerAction::FILES_ADMIN, Ab_UserGroup::ADMIN)
        );
        parent::__construct($module, $defRoles);
    }

    public function GetRoles(){

        return array(
            FileManagerAction::FILES_VIEW => $this->CheckAction(FileManagerAction::FILES_VIEW),
            FileManagerAction::FILES_UPLOAD => $this->CheckAction(FileManagerAction::FILES_UPLOAD),
            FileManagerAction::FILES_ADMIN => $this->CheckAction(FileManagerAction::FILES_ADMIN)
        );
    }
}

class FileManagerQueryModule {

    public static function EnThumbsAppend(Ab_Database $db, $list){
        if (empty($list)){
            return;
        }

        $values = array();
        foreach ($list as $size){
            $size['w'] = isset($size['w']) ? intval($size['w']) : 0;
            $size['h'] = isset($size['h']) ? intval($size['h']) : 0;
            $size['cm'] = isset($size['cm']) ? intval($size['cm']) : 0;

            array_push($values, "(".$size['w'].",".$size['h'].",".$size['cm'].")");
        }
        $sql = "
            INSERT IGNORE INTO ".$db->prefix."fm_enthumbs
            (width, height, cropMode) VALUES
            ".implode(",", $values)."
        ";
        $db->query_write($sql);
    }
}

Abricos::ModuleRegister(new FileManagerModule());

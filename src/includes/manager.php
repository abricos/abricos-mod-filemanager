<?php
/**
 * @package Abricos
 * @subpackage FileManager
 * @copyright Copyright (C) 2008 Abricos. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @author Alexander Kuzmin (roosit@abricos.org)
 */

require_once 'dbquery.php';

class FileManager extends Ab_ModuleManager {

    /**
     * @var FileManager
     */
    public static $instance;

    /**
     *
     * @var FileManagerModule
     */
    public $module = null;

    /**
     * Идентификатор последнего выгруженного файла
     *
     * @var String
     */
    public $lastUploadFileHash = '';

    private $_fileExtensionList = null;

    private $_userGroupSizeLimit = null;


    private $_checkSizeDisable = false;

    public function FileManager(FileManagerModule $module) {
        parent::__construct($module);

        FileManager::$instance = $this;
    }

    /**
     * Отключить проверку свободного места в профиле пользователя
     */
    public function CheckSizeDisable() {
        $this->_checkSizeDisable = true;
    }

    /**
     * Включить проверку свободного места в профиле пользователя
     */
    public function CheckSizeEnable() {
        $this->_checkSizeDisable = false;
    }

    public function IsAdminRole() {
        if ($this->IsRolesDisable()) {
            return true;
        }
        return $this->IsRoleEnable(FileManagerAction::FILES_ADMIN);
    }

    public function IsFileViewRole() {
        if ($this->IsRolesDisable()) {
            return true;
        }
        return $this->IsRoleEnable(FileManagerAction::FILES_VIEW);
    }

    public function IsFileUploadRole() {
        if ($this->IsRolesDisable()) {
            return true;
        }
        return $this->IsRoleEnable(FileManagerAction::FILES_UPLOAD);
    }

    public function IsAccessProfile($userid = 0) {
        if ($userid === 0) {
            $userid = Abricos::$user->id;
        }
        if ((Abricos::$user->id == $userid && $this->IsFileUploadRole())
            || $this->IsAdminRole()
        ) {
            return true;
        }
        return false;
    }

    public function DSProcess($name, $rows) {
        switch ($name) {
            case 'files':
                foreach ($rows->r as $r) {
                    if ($r->f == 'u' && $r->d->act == 'editor') {
                        $this->ImageEditorSave($r->d);
                    }
                    if ($r->f == 'd') {
                        $this->FileRemove($r->d->fh);
                    }
                }
                break;
            /*
            case 'editor':
                foreach ($rows->r as $r) {
                    if ($r->f == 'a') {
                        $this->ImageEditorChange($tsrs->p->filehash, $tsrs->p->session, $r->d);
                    }
                }
                break;
            /**/
            case 'folders':
                foreach ($rows->r as $r) {
                    if ($r->f == 'a') {
                        $this->FolderAppendFromData($r->d);
                    }
                    if ($r->f == 'd') {
                        $this->FolderRemove($r->d);
                    }
                    if ($r->f == 'u') {
                        $this->FolderChangePhrase($r->d);
                    }
                }
                break;
            case 'extensions':
                foreach ($rows->r as $r) {
                    if ($r->f == 'a') {
                        $this->FileTypeAppend($r->d);
                    }
                    if ($r->f == 'u') {
                        $this->FileTypeUpdate($r->d);
                    }
                }
                break;
            case 'usergrouplimit':
                foreach ($rows->r as $r) {
                    if ($r->f == 'a') {
                        $this->UserGroupLimitAppend($r->d);
                    }
                    if ($r->f == 'u') {
                        $this->UserGroupLimitUpdate($r->d);
                    }
                    if ($r->f == 'd') {
                        $this->UserGroupLimitRemove($r->d->id);
                    }
                }
                break;
        }
    }

    public function DSGetData($name, $rows) {
        $p = $rows->p;
        switch ($name) {
            case 'files':
                return $this->FileList($p->folderid);
            case 'folders':
                return $this->FolderList();
            case 'editor':
                return $this->EditorList($p->filehash, $p->session);
            case 'usergrouplimit':
                return $this->UserGroupLimitList();
            case 'extensions':
                return $this->GetFileExtensionList(false, true);
            case 'grouplist':
                return $this->GroupList();
        }

        return null;
    }

    public function GroupList() {
        if (!$this->IsAdminRole()) {
            return null;
        }
        return FileManagerQuery::GroupList($this->db);
    }

    public function UserGroupLimitRemove($id) {
        if (!$this->IsAdminRole()) {
            return null;
        }
        FileManagerQuery::UserGroupLimitRemove($this->db, $id);
    }

    public function UserGroupLimitAppend($d) {
        if (!$this->IsAdminRole()) {
            return null;
        }
        return FileManagerQuery::UserGroupLimitAppend($this->db, $d);
    }

    public function UserGroupLimitUpdate($d) {
        if (!$this->IsAdminRole()) {
            return null;
        }
        FileManagerQuery::UserGroupLimitUpdate($this->db, $d);
    }

    public function UserGroupLimitList() {
        if (!$this->IsAdminRole()) {
            return null;
        }
        return FileManagerQuery::UserGroupLimitList($this->db);
    }

    public function User_OptionNames() {
        if (!$this->IsFileUploadRole()) {
            return array();
        }

        return array(
            "tpl-screenshot",
            "scsTemplate",
            "scsWidth",
            "scsHeight"
        );
    }

    public function FileList($folderid) {
        return $this->FileListByUser(Abricos::$user->id, $folderid);
    }

    public function FileListByUser($userid, $folderid) {
        if (!$this->IsAccessProfile($userid)) {
            return null;
        }
        return FileManagerQuery::FileList($this->db, $userid, $folderid, FileManagerQuery::FILEATTRIBUTE_NONE);
    }

    public function FolderList() {
        return $this->FolderListByUser(Abricos::$user->id);
    }

    public function FolderListByUser($userid) {
        if (!$this->IsAccessProfile($userid)) {
            return null;
        }
        return FileManagerQuery::FolderList($this->db, $userid);
    }

    public function EditorList($filehash, $session) {
        if (!$this->IsAccessProfile()) {
            return null;
        }
        return FileManagerQuery::EditorList($this->db, $filehash, $session);
    }

    public function FileTypeUpdate($d) {
        if (!$this->IsAdminRole()) {
            return;
        }
        FileManagerQuery::FileTypeUpdate($this->db, $d);
    }

    public function FileTypeAppend($d) {
        if (!$this->IsAdminRole()) {
            return;
        }
        FileManagerQuery::FileTypeAppend($this->db, $d);
    }

    public function GetFileExtensionList($ignoreRole = false, $forDataSet = false) {
        if (!$ignoreRole) {
            if (!$this->IsFileUploadRole()) {
                return null;
            }
        }

        if (!is_null($this->_fileExtensionList)) {
            return $this->_fileExtensionList;
        }
        $list = array();

        $rows = FileManagerQuery::FileTypeList($this->db);
        if ($forDataSet) {
            return $rows;
        }
        while (($row = $this->db->fetch_array($rows))) {
            $list[$row['extension']] = $row;
        }
        $this->_fileExtensionList = $list;
        return $list;
    }

    public function GetFreeSpaceMethod() {

        if (is_null($this->_userGroupSizeLimit)) {
            $list = array();
            $rows = FileManagerQuery::UserGroupLimitList($this->db);
            while (($row = $this->db->fetch_array($rows))) {
                $list[$row['gid']] = $row;
            }
            $this->_userGroupSizeLimit = $list;
        }

        $user = Abricos::$user;

        $fullsize = FileManagerQuery::FileUsedSpace($this->db, $user->id);
        $groups = $user->GetGroupList();

        $limit = 0;
        foreach ($groups as $gp) {
            $limit = max(array(
                $limit,
                intval($this->_userGroupSizeLimit[$gp]['lmt'])
            ));
        }
        return $limit - $fullsize;
    }

    public function GetFreeSpace() {
        if (!$this->IsAccessProfile(Abricos::$user->id)) {
            return 0;
        }
        return $this->GetFreeSpaceMethod();
    }

    /**
     * Выгрузка файлов в базу данных.
     * Возвращает 0, если файл выгружен успешно, иначе номер ошибки:
     * 1 - неизвестный тип файла,
     * 2 - размер файла превышает допустимый,
     * 3 - неизвестная ошибка сервера,
     * 4 - размер картинки превышает допустимый,
     * 5 - свободное место в профили закончилось,
     * 6 - нет прав на выгрузку файла,
     * 7 - файл с таким именем уже есть в этой папке
     *
     * @param $folderid идентификатор папки
     * @param $fileinfo
     * @param $newNameIfFind Назначить новое имя, если файл с таким именем уже есть в папке
     */
    public function UploadFiles($folderid, $fileinfo, $newNameIfFind = false) {

        if (!$this->IsFileUploadRole()) {
            return 6;
        }

        $filecount = count($fileinfo['name']);

        if (empty($filecount)) {
            return 0;
        }
        $filename = trim($fileinfo['name']);
        $pathinfo = pathinfo($filename);
        $extension = strtolower($pathinfo['extension']);

        $dbFileInfo = FileManagerQuery::FileInfoByName($this->db, Abricos::$user->id, $folderid, $filename);
        if (!empty($dbFileInfo)) {
            if (!$newNameIfFind) {
                return 7;
            }
            $filename = str_replace(".".$extension, "", $filename)."_".substr(md5(time()), 1, 8).".".$extension;
        }
        $filelocation = trim($fileinfo['tmp_name']);
        $filesize = intval($fileinfo['size']);

        if (!is_uploaded_file($filelocation)) {
            return 3;
        }

        $ret = $this->UploadFile($folderid, $filelocation, $filename, $extension, $filesize);

        return $ret;
    }

    /**
     * Устаревший метод загрузки файлов. Оставлен для совместимости.
     */
    public function UploadFile($folderid, $filelocation, $filename, $extension, $filesize,
                               $atrribute = 0, $ignoreImageSize = false, $ignoreRole = false, $ignoreFreeSpace = false) {

        $uploadFile = $this->CreateUpload($filelocation, $filename, $folderid);
        $uploadFile->fileAttribute = $atrribute;
        $uploadFile->ignoreImageSize = $ignoreImageSize;
        $uploadFile->ignoreUploadRole = $ignoreRole;
        $uploadFile->ignoreFreeSpace = $ignoreFreeSpace;
        $error = $uploadFile->Upload();

        $this->lastUploadFileHash = $uploadFile->uploadFileHash;
        return $error;
    }

    /**
     * Создать объект файла для выгрузки
     *
     * @return UploadFile
     */
    public function CreateUpload($filePath, $fileName = '', $folderid = 0) {
        require_once 'uploadfile.php';
        return new UploadFile($filePath, $fileName, $folderid);
    }

    public function CreateUploadByVar($varname, $folderid = 0) {
        $fi = Abricos::CleanGPC('f', $varname, TYPE_FILE);
        $upload = $this->CreateUpload($fi['tmp_name'], $fi['name'], $folderid);
        $upload->file = $fi;
        return $upload;
    }

    public function GetFileData($p_filehash, $begin = 1, $end = 1048576) {
        if (!$this->IsFileViewRole()) {
            return;
        }

        return FileManagerQuery::FileData($this->db, $p_filehash, $begin, $end);
    }

    /**
     * Сравнить два файла: загружаемый в базу и тот что уже загружен в ней
     *
     * @param $filePath путь к физическому файлы
     * @param $fileHash идентификатор файла в базе
     */
    public function FilesCompare($filePath, $fileHash) {
        $handle = fopen($filePath, 'rb');
        if (empty($handle)) {
            return false;
        }
        $fileinfo = FileManagerQuery::FileData($this->db, $fileHash);

        $count = 1;
        while (!empty($fileinfo['filedata']) && connection_status() == 0) {

            $data = fread($handle, 1048576);

            if ($data != $fileinfo['filedata']) {
                fclose($handle);
                return false;
            }

            if (strlen($fileinfo['filedata']) == 1048576) {
                $startat = (1048576 * $count) + 1;
                $fileinfo = FileManagerQuery::FileData($this->db, $fileHash, $startat);
                $count++;
            } else {
                $fileinfo['filedata'] = '';
            }
        }
        fclose($handle);
        return true;
    }

    private function SaveTempFile($filehash, $imgname) {
        // выгрузка картинки во временный файл для его обработки
        $pinfo = pathinfo($imgname);

        $file = CWD."/cache/".(md5(TIMENOW.$imgname)).".".$pinfo['extension'];

        if (!($handle = fopen($file, 'w'))) {
            return false;
        }
        $fileinfo = FileManagerQuery::FileData($this->db, $filehash);
        $count = 1;
        while (!empty($fileinfo['filedata']) && connection_status() == 0) {
            fwrite($handle, $fileinfo['filedata']);
            if (strlen($fileinfo['filedata']) == 1048576) {
                $startat = (1048576 * $count) + 1;
                $fileinfo = FileManagerQuery::FileData($this->db, $filehash, $startat);
                $count++;
            } else {
                $fileinfo['filedata'] = '';
            }
        }
        fclose($handle);

        return $file;
    }

    public function SaveFileTo($filehash, $file) {
        if (!($handle = fopen($file, 'w'))) {
            return false;
        }
        $fileinfo = FileManagerQuery::FileData($this->db, $filehash);
        $count = 1;
        while (!empty($fileinfo['filedata']) && connection_status() == 0) {
            fwrite($handle, $fileinfo['filedata']);
            if (strlen($fileinfo['filedata']) == 1048576) {
                $startat = (1048576 * $count) + 1;
                $fileinfo = FileManagerQuery::FileData($this->db, $filehash, $startat);
                $count++;
            } else {
                $fileinfo['filedata'] = '';
            }
        }
        fclose($handle);

        return true;
    }


    public function GetUploadLib($file) {
        require_once CWD.'/modules/filemanager/lib/class.upload/class.upload.php';
        return new upload($file);
    }

    public function ImageConvert($p_filehash, $p_w, $p_h, $p_cnv) {
        if (empty($p_w) && empty($p_h) && empty($p_cnv)) {
            return $p_filehash;
        }

        $log = "Image Convert <br />";
        $log .= "Parameters: filehash=$p_filehash, w=$p_w, h=$p_h, format=$p_cnv <br />";

        if (!$this->IsFileViewRole()) {
            return $p_filehash;
        }

        // Запрос особого размера картинки
        $filehashdst = FileManagerQuery::ImagePreviewHash($this->db, $p_filehash, $p_w, $p_h, $p_cnv);

        if (!empty($filehashdst)) {
            return $filehashdst;
        }

        if (!$this->IsFileUploadRole()) {
            // доступ на изменение картинки закрыт, есть ли особые разрешения?
            if (!FileManagerQuery::EnThumbsCheck($this->db, $p_w, $p_h)) {
                return $p_filehash;
            }
        }

        $image = FileManagerQuery::ImageExist($this->db, $p_filehash);
        if (empty($image)) {
            return $p_filehash;
        }// есть ли вообще такая картинка

        $imageName = $image['filename'];

        $dir = CWD."/cache";
        $pathinfo = pathinfo($imageName);

        $file = $this->SaveTempFile($p_filehash, $imageName);

        if (empty($file)) {
            return $p_filehash;
        }

        $upload = $this->GetUploadLib($file);
        $nameadd = array();

        if (!empty($p_w) || !empty($p_h)) {
            array_push($nameadd, $p_w."x".$p_h);
            $upload->image_resize = true;

            $w = $upload->image_src_x;
            $h = $upload->image_src_y;

            if ($p_w > 0 && $w > $p_w) {
                $pr = $p_w / $w;
                $w = $w * $pr;
                $h = $h * $pr;
            }
            if ($p_h > 0 && $h > $p_h) {
                $pr = $p_h / $h;
                $w = $w * $pr;
                $h = $h * $pr;
            }
            $log .= "New width=$w, New height=$h<br />";
            $upload->image_x = $w;
            $upload->image_y = $h;
            /*
            if (empty($p_w)){
                $upload->image_ratio_x = true;
                $upload->image_y = $p_h;
            }else if (empty($p_h)){
                $upload->image_x = $p_w;
                $upload->image_ratio_y = true;
            }else{
                $upload->image_x = $p_w;
                $upload->image_y = $p_h;
                $upload->image_ratio_fill = true;
            }
            /**/
        }

        // необходимо ли конвертировать картинку
        if (!empty($p_cnv)) {
            array_push($nameadd, $p_cnv);
            $upload->image_convert = $p_cnv;
        }

        $newfilename = str_replace(".".$pathinfo['extension'], "", $pathinfo['basename']);
        $newfilename = $newfilename."_".implode("_", $nameadd);
        $upload->file_new_name_body = translateruen($newfilename);

        if ($upload->process($dir)) {
            $upload->Clean();
        }
        unlink($file);

        if (Abricos::$config['Misc']['develop_mode']) {
            $log .= $upload->log;
            @file_put_contents(CWD."/cache/#uploadlog.html", $log);
        }

        if (!file_exists($upload->file_dst_pathname)) {
            return $p_filehash;
        }

        $uploadFile = $this->CreateUpload($upload->file_dst_pathname, $newfilename.".".$pathinfo['extension'], $image['folderid']);
        $uploadFile->fileAttribute = FileManagerQuery::FILEATTRIBUTE_HIDEN;
        $uploadFile->ignoreImageSize = false;
        $uploadFile->ignoreUploadRole = true;
        $uploadFile->ignoreFreeSpace = true;
        $error = $uploadFile->Upload();

        $this->lastUploadFileHash = $uploadFile->uploadFileHash;

        if (!empty($error) || empty($this->lastUploadFileHash)) {
            return $p_filehash;
        }
        FileManagerQuery::ImagePreviewAdd($this->db, $p_filehash, $this->lastUploadFileHash, $p_w, $p_h, $p_cnv);
        unlink($upload->file_dst_pathname);

        return $this->lastUploadFileHash;
    }

    public function GetFileInfo($p_filehash) {
        if (!$this->IsFileViewRole()) {
            return;
        }
        return FileManagerQuery::FileInfo($this->db, $p_filehash);
    }

    public function CreateFolderByPathMethod($path) {
        if (empty($path)) {
            return 0;
        }
        $rows = FileManagerQuery::FolderList($this->db, Abricos::$user->id);
        $folders = array();
        while (($row = $this->db->fetch_array($rows))) {
            $folders[$row['id']] = $row;
        }
        $folderid = 0;
        $arr = explode("/", $path);
        for ($i = 0; $i < count($arr); $i++) {
            $name = translateruen($arr[$i]);

            $find = false;
            foreach ($folders as $key => $value) {
                if ($value['pid'] == $folderid && $name == $value['fn']) {
                    $folderid = $key;
                    $find = true;
                    break;
                }
            }
            if (!$find) {
                $folderid = FileManagerQuery::FolderAdd($this->db, $folderid, Abricos::$user->id, $name, $arr[$i]);
            }
        }
        return $folderid;
    }

    /**
     * Добавить папку в файловую систему.
     * Возвращает идентификатор папки. Если папка уже существует, то не добавляет ее.
     *
     * @param integer $parentFolderId идентификатор папки родителя, 0 - корень
     * @param string $folderName Имя папки
     * @param string $folderPhrase
     */
    public function FolderAppend($parentFolderId, $folderName, $folderPhrase = '') {
        if (!$this->IsFileUploadRole()) {
            return;
        }
        $userid = Abricos::$user->id;
        return FileManagerQuery::FolderAdd($this->db, $parentFolderId, $userid, $folderName, $folderPhrase);
    }

    public function FolderAppendFromData($data) {
        if (!$this->IsFileUploadRole()) {
            return;
        }

        $userid = Abricos::$user->id;
        $name = translateruen($data->ph);
        return FileManagerQuery::FolderAdd($this->db, $data->pid, $userid, $name, $data->ph);
    }

    public function FolderChangePhrase($data) {
        if (!$this->IsFileUploadRole()) {
            return;
        }

        $userid = Abricos::$user->id;
        $finfo = FileManagerQuery::FolderInfo($this->db, $data->id);

        if (!$this->IsAccessProfile($finfo['uid'])) {
            return null;
        }
        FileManagerQuery::FolderChangePhrase($this->db, $data->id, $data->ph);
    }

    public function FolderRemove($data) {
        if (!$this->IsFileUploadRole()) {
            return;
        }

        $finfo = FileManagerQuery::FolderInfo($this->db, $data->id);

        if (!$this->IsAccessProfile($finfo['uid'])) {
            return null;
        }
        FileManagerQuery::FolderRemove($this->db, $data->id);
    }

    public function FolderInfoByName($parentFolderId, $folderName) {
        if (!$this->IsFileUploadRole()) {
            return;
        }

        $userid = Abricos::$user->id;
        return FileManagerQuery::FolderInfoByName($this->db, $userid, $parentFolderId, $folderName);
    }

    public function FileRemove($filehash) {
        if (!$this->IsFileUploadRole()) {
            return;
        }

        $finfo = FileManagerQuery::FileInfo($this->db, $filehash);

        if (!$this->IsAccessProfile($finfo['uid'])) {
            return null;
        }
        FileManagerQuery::FilesDelete($this->db, array($filehash));
    }


    /**
     * Сохранение изменений картинки в редакторе
     *
     * @param $data данные по изменению
     */
    public function ImageEditorSave($data) {
        $filehash = $data->fh;
        $session = $data->session;
        // получить информацию редактируемой картинки
        $finfo = FileManagerQuery::FileInfo($this->db, $filehash);

        if (!$this->IsAccessProfile($finfo['uid'])) {
            return null;
        }
        // картинка с последними изменения в редакторе
        $lastedit = FileManagerQuery::EditorInfo($this->db, $filehash, $session);

        if (empty($lastedit)) {
            return;
        }
        $userid = Abricos::$user->id;
        FileManagerQuery::ImageEditorSave($this->db, $userid, $filehash, $lastedit, $data->copy);
    }

    public function ImageChange($filehash, $tools, $d) {
        // получить файл из БД
        $finfo = FileManagerQuery::FileInfo($this->db, $filehash);
        if (empty($finfo) || !$this->IsAccessProfile($finfo['uid'])) {
            return -1;
        }

        $file = $this->SaveTempFile($filehash, $finfo['fn']);
        $dir = CWD."/cache";
        $upload = $this->GetUploadLib($file);

        switch ($tools) {
            case 'size':
                $upload->image_resize = true;
                if (empty($d['width'])) {
                    $upload->image_ratio_x = true;
                    $upload->image_y = $d['height'];
                } else if (empty($d['height'])) {
                    $upload->image_x = $d['width'];
                    $upload->image_ratio_y = true;
                } else {
                    $upload->image_x = $d['width'];
                    $upload->image_y = $d['height'];
                }
                break;
            case 'crop':
                $right = $finfo['w'] - $d['width'] - $d['left'];
                $bottom = $finfo['h'] - $d['height'] - $d['top'];
                $upload->image_crop = $d['top']." ".$right." ".$bottom." ".$d['left'];
                break;
            default:
                return -1;
        }

        $upload->file_new_name_body = translateruen($finfo['fn']);

        $upload->process($dir);
        unlink($file);

        if (!file_exists($upload->file_dst_pathname)) {
            return -1;
        }

        $pathinfo = pathinfo($finfo['fn']);

        $result = $this->UploadFile(
            $finfo['fdid'],
            $upload->file_dst_pathname,
            $pathinfo['basename'],
            $upload->file_dst_name_ext,
            filesize($upload->file_dst_pathname),
            FileManagerQuery::FILEATTRIBUTE_TEMP
        );
        unlink($upload->file_dst_pathname);
        return $result;
    }

    /**
     * Изменение картинки
     *
     * @param $filehash идентификатор основной картинки
     * @param $session текущая сессия редактора
     * @param $data данные по изменению
     */
    public function ImageEditorChange($filehash, $session, $data) {

        // получить информацию редактируемой картинки
        $finfo = FileManagerQuery::FileInfo($this->db, $filehash);

        if (empty($finfo) || !$this->IsAccessProfile($finfo['uid'])) {
            return -1;
        }

        // картинка с последними изменения в редакторе
        $lastedit = FileManagerQuery::EditorInfo($this->db, $filehash, $session);

        $fromfilehash = $filehash;

        if (!empty($lastedit)) {
            $fromfilehash = $lastedit['fhdst'];
        }

        $d = array(
            "width" => $data->w,
            "height" => $data->h,
            "left" => $data->l,
            "top" => $data->t,
        );

        $result = $this->ImageChange($fromfilehash, $data->tools, $d);
        if ($result != 0) {
            return $fromfilehash;
        }

        $newfilehash = $this->lastUploadFileHash;
        $userid = Abricos::$user->id;
        FileManagerQuery::EditorAppend($this->db, $userid, $filehash, $newfilehash, $data->l, $data->t, $data->w, $data->h, $data->tools, $session);

        return $newfilehash;
    }
}

?>
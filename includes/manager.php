<?php
/**
 * @version $Id$
 * @package Abricos
 * @subpackage User
 * @copyright Copyright (C) 2008 Abricos. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @author Alexander Kuzmin (roosit@abricos.org)
 */

require_once 'dbquery.php';

class FileManager {
	
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
	
	/**
	 * 
	 * @var CMSDatabase
	 */
	public $db = null;
	
	public $user = null;
	
	/**
	 * Ядро
	 * @var CMSRegistry
	 */
	public $core = null;
	
	private $_rolesDisable = false;
	private $_checkSizeDisable = false;
	
	public function FileManager (FileManagerModule $module){
		$core = CMSRegistry::$instance;
		
		$this->module = $module;
		$this->core = $core;
		$this->db = $core->db;
		
		$this->user = $core->user->info;
	}
	
	/**
	 * Получить менеджер загрузки
	 *
	 * @return CMSUpload
	 */
	public function GetUpload(){
		if (!empty($this->upload)){
			return $this->upload;
		}
		require_once 'cmsupload.php';
		$this->upload = new CMSUpload($this->registry);
		return $this->upload;
	}
	
	/**
	 * Отключить проверку ролей
	 */
	public function RolesDisable(){
		$this->_rolesDisable = true;
	}

	/**
	 * Включить проверку ролей
	 */
	public function RolesEnable(){
		$this->_rolesDisable = false;
	}
	
	/**
	 * Отключить проверку свободного места в профиле пользователя
	 */
	public function CheckSizeDisable(){
		$this->_checkSizeDisable = true;
	}
	
	/**
	 * Включить проверку свободного места в профиле пользователя
	 */
	public function CheckSizeEnable(){
		$this->_checkSizeDisable = false;
	}
	
	public function IsAdminRole(){
		return $this->module->permission->CheckAction(FileManagerAction::FILES_ADMIN) > 0;
	}
	
	public function IsFileViewRole(){
		if ($this->_rolesDisable){ return true; }
		return $this->module->permission->CheckAction(FileManagerAction::FILES_VIEW) > 0;
	}
	
	public function IsFileUploadRole(){
		if ($this->_rolesDisable){ return true; }
		return $this->module->permission->CheckAction(FileManagerAction::FILES_UPLOAD) > 0;
	}
	
	public function IsAccessProfile($userid = 0){
		if ($userid == 0){
			$userid = $this->user['userid'];
		}
		if (($this->user['userid'] == $userid && $this->IsFileUploadRole())
			|| $this->IsAdminRole()){
			return true;
		}
		return false;
	}
	
	public function DSProcess($name, $rows){
		switch ($name){
			case 'files':
				foreach ($rows->r as $r){
					if ($r->f == 'u' && $r->d->act == 'editor'){ $this->ImageEditorSave($r->d); }
					if ($r->f == 'd'){ $this->FileRemove($r->d->fh); }
				}
				break;
			case 'editor':
				foreach ($rows->r as $r){
					if ($r->f == 'a'){ 
						$this->ImageEditorChange($tsrs->p->filehash, $tsrs->p->session, $r->d); 
					}
				}
				break;
			case 'folders':
				foreach ($rows->r as $r){
					if ($r->f == 'a'){ $this->FolderAppendFromData($r->d); }
					if ($r->f == 'd'){ $this->FolderRemove($r->d); }
					if ($r->f == 'u'){ $this->FolderChangePhrase($r->d); }
				}
				break;
			case 'userconfig':
				foreach ($rows->r as $r){
					if ($r->f == 'u'){ $this->UserConfigUpdate($r->d); }
					if ($r->f == 'a'){ $this->UserConfigAppend($r->d); } 
				}
				break;
			case 'extensions':
				foreach ($rows->r as $r){
					if ($r->f == 'u'){ $this->FileTypeUpdate($r->d); }
				}
				break;
			case 'usergrouplimit':
				foreach ($rows->r as $r){
					if ($r->f == 'a'){ $this->UserGroupLimitAppend($r->d); }
					if ($r->f == 'u'){ $this->UserGroupLimitUpdate($r->d); }
					if ($r->f == 'd'){ $this->UserGroupLimitRemove($r->d->id); }
				}
				break;
		}
	}
	
	public function DSGetData($name, $rows){
		$p = $rows->p;
		switch ($name){
			case 'files':
				return $this->FileList($p->folderid); 
			case 'folders':
				return $this->FolderList(); 
			case 'editor':
				return $this->EditorList($p->filehash, $p->session); 
			case 'userconfig':
				return $this->UserConfigList(); 
			case 'usergrouplimit':
				return $this->UserGroupLimitList(); 
			case 'extensions':
				return $this->GetFileExtensionList(false, true); 
			case 'grouplist':
				return $this->GroupList();
		}
		
		return null;
	}
	
	public function GroupList(){
		if (!$this->IsAdminRole()){ return null;}
		return FileManagerQueryExt::GroupList($this->db);
	}
	
	public function UserGroupLimitRemove($id){
		if (!$this->IsAdminRole()){ return null;}
		FileManagerQueryExt::UserGroupLimitRemove($this->db, $id);
	}
	
	public function UserGroupLimitAppend($d){
		if (!$this->IsAdminRole()){ return null;}
		return FileManagerQueryExt::UserGroupLimitAppend($this->db, $d);
	}
	
	public function UserGroupLimitUpdate($d){
		if (!$this->IsAdminRole()){ return null;}
		FileManagerQueryExt::UserGroupLimitUpdate($this->db, $d);
	}
	
	public function UserGroupLimitList(){
		if (!$this->IsAdminRole()){ return null;}
		return FileManagerQueryExt::UserGroupLimitList($this->db);
	}
	
	private function UserConfigCheckVarName($name){
		if (!$this->IsFileUploadRole()){ return false; }
		switch($name){
			case "tpl-screenshot":
				return true;
		}
		return false;
	}
	
	public function UserConfigList(){
		if (!$this->IsFileUploadRole()){ return null; }
		
		return $this->core->user->GetManager()->UserConfigList($this->user['userid'], 'filemanager');
	}

	public function UserConfigAppend($d){
		if (!$this->UserConfigCheckVarName($d->nm)){ return; }
		
		$this->core->user->GetManager()->UserConfigAppend($this->user['userid'], 'filemanager', $d->nm, $d->vl);
	}
	
	public function UserConfigUpdate($d){
		if (!$this->UserConfigCheckVarName($d->nm)){
			return;
		}
		
		$this->core->user->GetManager()->UserConfigUpdate($this->user['userid'], $d->id, $d->vl);
	}

	public function FileList($folderid){
		return $this->FileListByUser($this->user['userid'], $folderid);
	}
	
	public function FileListByUser($userid, $folderid){
		if (!$this->IsAccessProfile($userid)){
			return null;
		}
		return CMSQFileManager::FileList($this->db, $userid, $folderid, CMSQFileManager::FILEATTRIBUTE_NONE);
	}
	
	public function FolderList(){
		return $this->FolderListByUser($this->user['userid']);
	}
	
	public function FolderListByUser($userid){
		if (!$this->IsAccessProfile($userid)){
			return null;
		}
		return CMSQFileManager::FolderList($this->db, $userid); 
	}
	
	public function EditorList($filehash, $session){
		if (!$this->IsAccessProfile()){
			return null;
		}
		return CMSQFileManager::EditorList($this->db, $filehash, $session);
	}
	
	public function FileTypeUpdate($d){
		if (!$this->IsAdminRole()) { return; }
		FileManagerQueryExt::FileTypeUpdate($this->db, $d);
	}
	
	public function GetFileExtensionList($ignoreRole = false, $forDataSet = false){
		if (!$ignoreRole){
			if (!$this->IsFileUploadRole()){ return null; }
		}
		
		if (!is_null($this->_fileExtensionList)){
			return $this->_fileExtensionList;
		}
		$list = array();
		
		$rows = FileManagerQueryExt::FileTypeList($this->db);
		if ($forDataSet){ 
			return $rows; 
		}
		while (($row = $this->db->fetch_array($rows))){	
			$list[$row['extension']] = $row; 
		}
		$this->_fileExtensionList = $list;
		return $list;
	}
	
	public function GetFreeSpace(){
		return $this->GetFreeSpaceByUser($this->user['userid']);
	}
	
	public function GetFreeSpaceByUser($userid){
		if (!$this->IsAccessProfile($userid)){
			return 0;
		}
		
		if (is_null($this->_userGroupSizeLimit)){
			$list = array();
			$rows = FileManagerQueryExt::UserGroupLimitList($this->db);
			while (($row = $this->db->fetch_array($rows))){	
				$list[$row['gid']] = $row; 
			}
			$this->_userGroupSizeLimit = $list;
		}
		
		$fullsize = CMSQFileManager::FileUsedSpace($this->db, $userid);
		
		if ($userid != $this->user['userid']){
			// $user = CMSQUser::UserById($this->db, $userid);
			// TODO: необходимо разработать
			die('error');
		}else{
			$user = $this->user;
		}
		$limit = 0;
		foreach ($user['group'] as $gp){
			$limit = max(array($limit, intval($this->_userGroupSizeLimit[$gp]['lmt'])));
		}
		return $limit-$fullsize;
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
	public function UploadFiles($folderid, $fileinfo, $newNameIfFind = false){
		
		if (!$this->IsFileUploadRole()){
			return 6;
		}
		
		$filecount = count ($fileinfo['name']);

		if (empty($filecount)){ 
			return 0; 
		}
		
		$filename = trim($fileinfo['name']);
		$pathinfo = pathinfo($filename);
		$extension = strtolower($pathinfo['extension']);
		
		$dbFileInfo = CMSQFileManager::FileInfoByName($this->db, $this->user['userid'], $folderid, $filename); 
		if (!empty($dbFileInfo)) {
			if (!$newNameIfFind){
				return 7;
			}
			$filename = str_replace(".".$extension, "", $filename)."_".substr(md5(time()), 1, 8).".".$extension; 
		}
		$filelocation = trim($fileinfo['tmp_name']);
		$filesize = intval($fileinfo['size']);
		
		if (!is_uploaded_file($filelocation)){ 
			return 3; 
		}
		$ret = $this->UploadFile($folderid, $filelocation, $filename, $extension, $filesize);

		return $ret;
	}
	
	public function UploadFile($folderid, $filelocation, $filename, $extension, $filesize, $atrribute = 0, 
			$ignoreImageSize = false, $ignoreRole = false, $ignoreFreeSpace = false){
		
		if (!$ignoreRole){
			if (!$this->IsFileUploadRole()){
				return 6;
			}
		}
		$userid = $this->user['userid'];
		
		$extensions = $this->GetFileExtensionList($ignoreRole);
		
		$filetype = $extensions[$extension];
		
		if (empty($filetype)){ // ошибка: нет такого типа файла в разрешенных 
			return 1; 
		}
		if ($userid > 0 && !$ignoreFreeSpace){ 
			if ($filesize > $filetype['maxsize']){// ошибка: размер файла превышает допустимый 
				return 2; 
			} 
			// подсчет свободного места в профиле юзера
			$freespace = $this->GetFreeSpace();
			if ($freespace < $filesize){ // ошибка: превышена квота 
				return 5; 
			}
		}
		
		// если картинка, проверка на допустимый размер
		$upload = $this->GetUploadLib($filelocation);
		
		$imgwidth = 0;
		$imgheight = 0;
		if ($upload->file_is_image ){
			if (!$ignoreImageSize){
				if ($filetype['maxwidth']>0 && $upload->image_src_x > $filetype['maxwidth']){
					return 4; // ошибка: размер картинки превышает допустимый
				}
				if ($filetype['maxheight']>0 && $upload->image_src_y > $filetype['maxheight']){
					return 4; // ошибка: размер картинки превышает допустимый
				}
			}
			$imgwidth = $upload->image_src_x;
			$imgheight = $upload->image_src_y;
		}
		$isimage = $upload->file_is_image ? 1 : 0;
		
		if (empty($filetype['mimetype'])){
			#TODO: в этом месте возникает ошибка если попытаться загрузить файл bmp большого размера
			CMSQFileManager::FileTypeUpdateMime($this->registry->db, $filetype['filetypeid'], $upload->file_src_mime);
			$filetype['mimetype'] = $upload->file_src_mime;
		}
		
		if (!($filedata = @file_get_contents($filelocation))) { // ошибка: в чтении файла 
			return 3; 
		} 
		$filehash = CMSQFileManager::FileUpload(
			$this->db, $userid, $folderid, 
			$filename, $filedata, $filesize, $extension, 
			$isimage, $imgwidth, $imgheight, $atrribute
		);
		
		if (empty($filehash)){ // TODO: доработать механизм обработки ошибок
			return 3;
		}
		if (empty($filehash)){ // временно отключено
			$this->db->ClearError();
			// файл не залез в оперативку, значит скидываю его в ФС
			
			$filehash = CMSQFileManager::FileUpload(
				$this->db, $userid, $folderid, $filename, '', $filesize, $extension, 
				$isimage, $imgwidth, $imgheight, $atrribute
			);
						
			if (empty($filehash)){
				return 3;
			}
			
			$fsFullPath = CMSQFileManager::FSPathCreate($this->db, $filehash);
			$dirPath = dirname($fsFullPath);
			mkdir($dirPath, 0777, true);
			
			rename($filelocation, $fsFullPath);
			
			echo($fsFullPath);
		}
		
		$this->lastUploadFileHash = $filehash;
		
		return 0;
	}
	
	private function SaveTempFile($filehash, $imgname){
		// выгрузка картинки во временный файл для его обработки
		$pinfo = pathinfo($imgname);
		
		$file = CWD."/cache/".(md5(TIMENOW.$imgname)).".".$pinfo['extension'];
				
		if (!($handle = fopen($file, 'w'))){ return false; }
		$fileinfo = CMSQFileManager::FileData($this->db, $filehash);
		$count = 1;
		while (!empty($fileinfo['filedata']) && connection_status() == 0) {
			fwrite($handle, $fileinfo['filedata']);
			if (strlen($fileinfo['filedata']) == 2097152) {
				$startat = (2097152 * $count) + 1;
				$fileinfo = CMSQFileManager::FileData($this->db, $filehash, $startat);
				$count++;
			} else {
				$fileinfo['filedata'] = '';
			}
		}
		fclose($handle);
		
		return $file;
	}
	
	public function GetUploadLib($file){
		require_once CWD.'/modules/filemanager/lib/class.upload/class.upload.php';
		return new upload($file);
	}
	
	public function ImageConvert($p_filehash, $p_w, $p_h, $p_cnv){
		
		if (empty($p_w) && empty($p_h) && empty($p_cnv)){ return $p_filehash; }

		if (!$this->IsFileViewRole()){
			return $p_filehash;
		}
		
		// Запрос особого размера картинки
		$filehashdst = CMSQFileManager::ImagePreviewHash($this->db, $p_filehash, $p_w, $p_h, $p_cnv);
		
		if (!empty($filehashdst)){ return $filehashdst; }
		
		if (!$this->IsFileUploadRole()){
			// доступ на изменение картинки закрыт, есть ли особые разрешения?
			if (!CMSQFileManager::EnThumbsCheck($this->db, $p_w, $p_h)){
				return $p_filehash;
			}
		}
		
		$image = CMSQFileManager::ImageExist($this->db, $p_filehash);
		if (empty($image)){ return $p_filehash; }// есть ли вообще такая картинка

		$imageName = $image['filename'];
		
		$dir = CWD."/cache";
		$pathinfo = pathinfo($imageName);
		
		$file = $this->SaveTempFile($p_filehash, $imageName);
		if (empty($file)){ return $p_filehash; }
		
		$upload = $this->GetUploadLib($file);
		$nameadd = array();
		
		if (!empty($p_w) || !empty($p_h)){
			array_push($nameadd, $p_w."x".$p_h);
			$upload->image_resize = true;
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
		}
					
		// необходимо ли конвертировать картинку
		if (!empty($p_cnv)){
			array_push($nameadd, $p_cnv);
			$upload->image_convert = $p_cnv;
		}
		
		$newfilename = str_replace(".".$pathinfo['extension'], "", $pathinfo['basename']);
		$newfilename = $newfilename."_".implode("_", $nameadd);
		$upload->file_new_name_body = translateruen($newfilename);
		
		$upload->process($dir);
		
		unlink($file);

		if (!file_exists($upload->file_dst_pathname)){ return $p_filehash; }
		
		$error = $this->UploadFile(
			$image['folderid'],
			$upload->file_dst_pathname, 
			$newfilename.".".$pathinfo['extension'],
			$upload->file_dst_name_ext, 
			filesize($upload->file_dst_pathname), 
			CMSQFileManager::FILEATTRIBUTE_HIDEN, false, true, true
		);

		if (!empty($error) || empty($this->lastUploadFileHash)){
			return $p_filehash;
		}
		CMSQFileManager::ImagePreviewAdd($this->db, $p_filehash, $this->lastUploadFileHash, $p_w, $p_h, $p_cnv);
		unlink($upload->file_dst_pathname);
		
		return $this->lastUploadFileHash;
	}
	
	public function GetFileInfo($p_filehash){
		if (!$this->IsFileViewRole()){
			return ;
		}
		return CMSQFileManager::FileInfo($this->db, $p_filehash);
	}
	
	public function GetFileData($p_filehash, $begin = 1, $end = 2097152){
		if (!$this->IsFileViewRole()){
			return ;
		}
		
		return CMSQFileManager::FileData($this->db, $p_filehash, $begin, $end);
	}
	
	/**
	 * Добавить папку в файловую систему. 
	 * Возвращает идентификатор папки. Если папка уже существует, то не добавляет ее.
	 * 
	 * @param integer $parentFolderId идентификатор папки родителя, 0 - корень
	 * @param string $folderName Имя папки
	 * @param string $folderPhrase
	 */
	public function FolderAppend($parentFolderId, $folderName, $folderPhrase = ''){
		if (!$this->IsFileUploadRole()){ return; }
		$userid = $this->user['userid'];
		return CMSQFileManager::FolderAdd($this->db, $parentFolderId, $userid, $folderName, $folderPhrase);
	}
	
	public function FolderAppendFromData($data){
		if (!$this->IsFileUploadRole()){ return; }

		$userid = $this->user['userid'];
		$name = translateruen($data->ph);
		return CMSQFileManager::FolderAdd($this->db, $data->pid, $userid, $name, $data->ph);
	}
	
	public function FolderChangePhrase($data){
		if (!$this->IsFileUploadRole()){ return; }
		
		$userid = $this->user['userid'];
		$finfo = CMSQFileManager::FolderInfo($this->db, $data->id);
		
		if (!$this->IsAccessProfile($finfo['uid'])){
			return null;
		}
		CMSQFileManager::FolderChangePhrase($this->db, $data->id, $data->ph);
	}

	public function FolderRemove($data){
		if (!$this->IsFileUploadRole()){ return; }
		
		$finfo = CMSQFileManager::FolderInfo($this->db, $data->id);
		
		if (!$this->IsAccessProfile($finfo['uid'])){
			return null;
		}
		CMSQFileManager::FolderRemove($this->db, $data->id);
	}
	
	public function FolderInfoByName($parentFolderId, $folderName){
		if (!$this->IsFileUploadRole()){ return; }
		
		$userid = $this->user['userid'];
		return CMSQFileManager::FolderInfoByName($this->db, $userid, $parentFolderId, $folderName);
	}
	
	public function FileRemove($filehash){
		if (!$this->IsFileUploadRole()){ return; }
		
		$finfo = CMSQFileManager::FileInfo($this->db, $filehash);
		
		if (!$this->IsAccessProfile($finfo['uid'])){
			return null;
		}
		CMSQFileManager::FilesDelete($this->db, array($filehash));
	}
	
	
	/**
	 * Сохранение изменений картинки в редакторе
	 * 
	 * @param $data данные по изменению
	 */
	public function ImageEditorSave($data){
		$filehash = $data->fh;
		$session = $data->session;
		// получить информацию редактируемой картинки
		$finfo = CMSQFileManager::FileInfo($this->db, $filehash);
		
		if (!$this->IsAccessProfile($finfo['uid'])){
			return null;
		}
		// картинка с последними изменения в редакторе
		$lastedit = CMSQFileManager::EditorInfo($this->db, $filehash, $session);
		
		if (empty($lastedit)){ 
			return; 
		}
		$userid = $this->user['userid'];
		CMSQFileManager::ImageEditorSave($this->db, $userid, $filehash, $lastedit, $data->copy);
	}	

	public function ImageChange($filehash, $tools, $d){
		// получить файл из БД
		$finfo = CMSQFileManager::FileInfo($this->db, $filehash);
		if (empty($finfo) || !$this->IsAccessProfile($finfo['uid'])){
			return -1;
		}
		
		$file = $this->SaveTempFile($filehash, $finfo['fn']);
		$dir = CWD."/cache";
		$upload = $this->GetUploadLib($file);
		
		switch($tools){
			case 'size':
				$upload->image_resize = true;
				if (empty($d['width'])){
					$upload->image_ratio_x = true;
					$upload->image_y = $d['height'];
				}else if (empty($d['height'])){
					$upload->image_x = $d['width'];
					$upload->image_ratio_y = true;
				}else{
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

		if (!file_exists($upload->file_dst_pathname)){ 
			return -1; 
		}

		$pathinfo = pathinfo($finfo['fn']);
		
		$result = $this->UploadFile(
			$finfo['fdid'],
			$upload->file_dst_pathname,
			$pathinfo['basename'], 
			$upload->file_dst_name_ext, 
			filesize($upload->file_dst_pathname), 
			CMSQFileManager::FILEATTRIBUTE_TEMP
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
	public function ImageEditorChange($filehash, $session, $data){
		
		// получить информацию редактируемой картинки
		$finfo = CMSQFileManager::FileInfo($this->db, $filehash);
		
		if (empty($finfo) || !$this->IsAccessProfile($finfo['uid'])){
			return -1;
		}
		
		// картинка с последними изменения в редакторе
		$lastedit = CMSQFileManager::EditorInfo($this->db, $filehash, $session);

		$fromfilehash = $filehash;
		
		if (!empty($lastedit)){
			$fromfilehash = $lastedit['fhdst'];
		}
		
		$d = array(
			"width" => $data->w, "height" => $data->h,
			"left" => $data->l, "top" => $data->t,
		);
		
		$result = $this->ImageChange($fromfilehash, $data->tools, $d);
		if ($result != 0){ return $fromfilehash; }
		
		$newfilehash = $this->lastUploadFileHash;
		$userid = $this->user['userid'];
		CMSQFileManager::EditorAppend($this->db, $userid, $filehash, $newfilehash, $data->l, $data->t, $data->w, $data->h, $data->tools, $session);
		
		return $newfilehash;
	}
}

?>
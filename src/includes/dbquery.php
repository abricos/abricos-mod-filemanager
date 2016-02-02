<?php
/**
 * @package Abricos
 * @subpackage FileManager
 * @copyright 2008-2016 Alexander Kuzmin
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @author Alexander Kuzmin <roosit@abricos.org>
 */

/**
 * Class FileManagerQuery
 */
class FileManagerQuery {

    /**
     * Атрибут файла: стандартный
     */
    const FILEATTRIBUTE_NONE = 0;
    /**
     * Атрибут файла: скрытый
     */
    const FILEATTRIBUTE_HIDEN = 1;
    /**
     * Атрибут файла: временный
     */
    const FILEATTRIBUTE_TEMP = 2;

    public static function FileCopy(Ab_Database $db, $filehash){
        $newfilehash = FileManagerQuery::GetFileHash($db);

        $sql = "
			INSERT INTO ".$db->prefix."fm_file 
				(userid, filehash, filename, title, filedata, filesize, extension,
				dateline, attribute, isimage, imgwidth, imgheight, folderid)
				SELECT
					userid, 
					'".$newfilehash."',
					filename, title, filedata, filesize, extension, dateline, attribute, isimage, imgwidth, imgheight, folderid
				FROM ".$db->prefix."fm_file
				WHERE filehash=".bkstr($filehash)." 
		";
        $db->query_write($sql);

        return $newfilehash;
    }

    public static function ImageEditorSave(Ab_Database $db, $userid, $filehash, $lastedit, $iscopy){

        $newfilehash = $lastedit['fhdst'];

        FileManagerQuery::FileSetAttribute($db, $newfilehash, FileManagerQuery::FILEATTRIBUTE_NONE);

        if (!$iscopy){
            FileManagerQuery::FileDelete($db, $filehash);
            $sql = "
				UPDATE ".$db->prefix."fm_file
				SET filehash='".bkint($filehash)."' 
				WHERE filehash='".bkstr($newfilehash)."'
				LIMIT 1
			";
            $db->query_write($sql);
        }

        $sql = "
			DELETE FROM ".$db->prefix."fm_editor
			WHERE userid=".bkint($userid)." 
		";
        $db->query_write($sql);

        $sql = "
			DELETE FROM ".$db->prefix."fm_file
			WHERE userid=".bkint($userid)." AND attribute=".FileManagerQuery::FILEATTRIBUTE_TEMP."
		";
        $db->query_write($sql);
    }

    public static function FileSetAttribute(Ab_Database $db, $filehash, $attribute){
        $sql = "
			UPDATE ".$db->prefix."fm_file
			SET attribute='".bkint($attribute)."' 
			WHERE filehash='".bkstr($filehash)."'
		";
        $db->query_write($sql);
    }

    /**
     * Добавление в редактор последние изменения картинки
     */
    public static function EditorAppend(Ab_Database $db, $userid, $filehashsrc, $filehashdst, $left, $top, $width, $height, $tools, $session){
        $sql = "
			INSERT INTO ".$db->prefix."fm_editor 
			(userid, filehashsrc, `left`, top, width, height, tools, filehashdst, dateline, session) VALUES
			(
				".bkint($userid).",
				'".bkstr($filehashsrc)."',
				".bkint($left).",
				".bkint($top).",
				".bkint($width).",
				".bkint($height).",
				'".bkstr($tools)."',
				'".bkstr($filehashdst)."',
				".TIMENOW.",
				".bkint($session)."
			)
		";
        $db->query_write($sql);
    }

    const EDITOR_FIELD = "
		editorid as id,
		filehashsrc as fhsrc,
		width as w,
		height as h,
		`left` as l,
		top as t,
		tools,
		filehashdst as fhdst,
		dateline as dl,
		session as ss
	";

    public static function EditorList(Ab_Database $db, $filehash, $session){
        $sql = "
			SELECT
				".FileManagerQuery::EDITOR_FIELD."
			FROM ".$db->prefix."fm_editor
			WHERE filehashsrc='".bkstr($filehash)."' AND session='".bkstr($session)."'
			ORDER BY dateline DESC
		";
        return $db->query_read($sql);
    }

    /**
     * Информация о последних изминениях картинки
     */
    public static function EditorInfo(Ab_Database $db, $filehash, $session){
        $sql = "
			SELECT
				".FileManagerQuery::EDITOR_FIELD."
			FROM ".$db->prefix."fm_editor
			WHERE filehashsrc='".bkstr($filehash)."' AND session='".bkstr($session)."'
			ORDER BY dateline DESC
			LIMIT 1
		";
        return $db->query_first($sql);
    }

    public static function FolderInfoByName(Ab_Database $db, $userid, $parentFolderId, $folderName){
        $sql = "
			SELECT 
				folderid as id, 
				parentfolderid as pid, 
				name as fn, 
				phrase as ph,
				userid as uid
			FROM ".$db->prefix."fm_folder
			WHERE userid=".bkint($userid)." AND parentfolderid=".bkint($parentFolderId)." AND name='".bkstr($folderName)."' 
			LIMIT 1
		";
        return $db->query_first($sql);
    }

    public static function FolderInfo(Ab_Database $db, $folderid){
        $sql = "
			SELECT 
				folderid as id, 
				parentfolderid as pid, 
				name as fn, 
				phrase as ph,
				userid as uid
			FROM ".$db->prefix."fm_folder
			WHERE folderid=".bkint($folderid)."
			LIMIT 1
		";
        return $db->query_first($sql);
    }

    public static function FolderRemove(Ab_Database $db, $folderid){
        $rows = FileManagerQuery::FolderChildIdList($db, $folderid);
        while (($row = $db->fetch_array($rows))){
            FileManagerQuery::FolderRemove($db, $row['id']);
        }

        $rows = FileManagerQuery::FileListInFolder($db, $folderid);
        while (($row = $db->fetch_array($rows))){
            FileManagerQuery::FileDelete($db, $row['fh']);
        }
        $sql = "
			DELETE FROM ".$db->prefix."fm_folder
			WHERE folderid=".bkint($folderid)."
		";
        $db->query_write($sql);
    }

    /**
     * Список дочерних папок в дирректории
     */
    public static function FolderChildIdList(Ab_Database $db, $folderid){
        $sql = "
			SELECT 
				folderid as id 
			FROM ".$db->prefix."fm_folder
			WHERE parentfolderid=".bkint($folderid)."
		";
        return $db->query_read($sql);
    }

    public static function FolderList(Ab_Database $db, $userid){
        $sql = "
			SELECT 
				folderid as id, 
				parentfolderid as pid, 
				name as fn, 
				phrase as ph
			FROM ".$db->prefix."fm_folder
			WHERE userid=".bkint($userid)."
			ORDER BY phrase
		";
        return $db->query_read($sql);
    }

    public static function FolderChangePhrase(Ab_Database $db, $folderid, $phrase){
        $sql = "
			UPDATE ".$db->prefix."fm_folder 
			SET phrase='".bkstr($phrase)."'
			WHERE folderid=".bkint($folderid)."  
			LIMIT 1
		";
        $db->query_write($sql);
    }

    public static function FolderAdd(Ab_Database $db, $parentfolderid, $userid, $name, $phrase = ''){
        if (empty($phrase)){
            $phrase = $name;
        }
        if (empty($name)){
            return 0;
        }
        $parentfolderid = intval($parentfolderid);
        $sql = "
			SELECT folderid as id
			FROM ".$db->prefix."fm_folder
			WHERE parentfolderid=".bkint($parentfolderid)."
				AND userid=".bkint($userid)."
				AND name='".bkstr($name)."'
			LIMIT 1
		";
        $row = $db->query_first($sql);
        if (!empty($row)){
            return $row['id'];
        }

        $sql = "
			INSERT INTO ".$db->prefix."fm_folder
				(parentfolderid, userid, name, phrase, dateline)
			VALUES (
				".bkint($parentfolderid).",
				".bkint($userid).",
				'".bkstr($name)."',
				'".bkstr($phrase)."',
				".TIMENOW."
			)
		";
        $db->query_write($sql);
        return $db->insert_id();
    }

    public static function FileDelete(Ab_Database $db, $fileid){
        FileManagerQuery::FilesDelete($db, array($fileid));
    }

    /**
     * Удаление файлов и их превью
     */
    public static function FilesDelete(Ab_Database $db, $files){
        if (empty($files)){
            return;
        }

        $where = array();
        $whereprev = array();
        foreach ($files as $filehash){
            array_push($whereprev, "filehashsrc='".bkstr($filehash)."'");
            array_push($where, "filehash='".bkstr($filehash)."'");

            $fsFile = FileManagerQuery::FSPathGet($db, $filehash);
            if (file_exists($fsFile)){
                @unlink($fsFile);
            }
        }
        $whprev = implode(" OR ", $whereprev);
        $sql = "
			SELECT filehashdst
			FROM ".$db->prefix."fm_imgprev
			WHERE ".$whprev."
		";
        $rows = $db->query_read($sql);
        while (($row = $db->fetch_array($rows))){
            array_push($where, "filehash='".bkstr($row['filehashdst'])."'");
        }

        $sql = "
			DELETE 
			FROM ".$db->prefix."fm_imgprev
			WHERE ".$whprev."
		";
        $db->query_write($sql);

        $wh = implode(" OR ", $where);
        $sql = "
			DELETE 
			FROM ".$db->prefix."fm_file
			WHERE ".$wh."
		";
        $db->query_write($sql);
    }

    /**
     * Кол-во используемого пространства
     */
    public static function FileUsedSpace(Ab_Database $db, $userid){
        $sql = "
			SELECT sum(filesize) as fullsize
			FROM ".$db->prefix."fm_file
			WHERE userid=".bkint($userid)."
			GROUP BY userid
			LIMIT 1
		";
        $row = $db->query_first($sql);
        return intval($row['fullsize']);
    }

    public static function ImagePreviewAdd(Ab_Database $db, $filehashsrc, $filehashdst, $width, $height, $cnv){
        $sql = "
			INSERT INTO ".$db->prefix."fm_imgprev
			(filehashsrc, width, height, cnv, filehashdst) VALUES (
				'".bkstr($filehashsrc)."',
				".bkint($width).",
				".bkint($height).",
				'".bkstr($cnv)."',
				'".bkstr($filehashdst)."'
			)
		";
        $db->query_write($sql);
    }

    public static function ImagePreviewHash(Ab_Database $db, $filehashsrc, $width, $height, $cnv){
        $sql = "
			SELECT filehashdst
			FROM ".$db->prefix."fm_imgprev
			WHERE 
				filehashsrc='".bkstr($filehashsrc)."' 
				AND width=".bkint($width)."
				AND height=".bkint($height)."
				AND cnv='".bkstr($cnv)."'
		";
        $row = $db->query_first($sql);
        if (empty($row)){
            return "";
        }
        return $row['filehashdst'];
    }

    public static function FileUpdateCounter(Ab_Database $db, $filehash){
        $sql = "
			UPDATE ".$db->prefix."fm_file 
			SET counter=counter+1, lastget=".TIMENOW."
			WHERE filehash='".bkstr($filehash)."' 
			LIMIT 1
		";
        $db->query_write($sql);
    }

    public static function ImageExist(Ab_Database $db, $filehash){
        $sql = "
			SELECT filename, folderid
			FROM ".$db->prefix."fm_file
			WHERE filehash = '".bkstr($filehash)."' AND isimage > 0
			LIMIT 1
		";
        return $db->query_first($sql);
    }

    public static function &FileData(Ab_Database $db, $filehash, $begin = 1, $count = 1048576){
        $sql = "
			SELECT 
				fileid, 
				userid, 
				filehash, 
				filename, 
				filesize, 
				counter, 
				dateline, 
				extension, 
				SUBSTRING(filedata, ".bkint($begin).", ".bkint($count).") AS filedata,
				fsname, folderid
			FROM ".$db->prefix."fm_file
			WHERE filehash = '".bkstr($filehash)."'
			LIMIT 1
		";
        $row = $db->query_first($sql);

        return $row;
    }

    const FILE_FIELD = "
		fileid as id, 
		filehash as fh, 
		filename as fn,
		title as tl, 
		filesize as fs, 
		dateline as d,
		attribute as a, 
		extension as ext, 
		isimage as img, 
		imgwidth as w, 
		imgheight as h,
		folderid as fdid,
		userid as uid
	";

    public static function FSPathCreate(Ab_Database $db, $filehash){
        $finfo = FileManagerQuery::FileInfo($db, $filehash);
        if (empty($finfo)){
            return;
        }

        $fsfn = FileManagerQuery::GenerateFileHash()."_".$filehash."_".$finfo['ext'];

        $sql = "
			UPDATE ".$db->prefix."fm_file
				SET fsname = '".$fsfn."'
			WHERE filehash='".bkstr($filehash)."'
		";
        $db->query_write($sql);

        return FileManagerQuery::FSPathGet($db, $filehash);
    }

    public static function FSPathGet(Ab_Database $db, $filehash){
        $finfo = FileManagerQuery::FileInfo($db, $filehash, true);
        if (empty($finfo)){
            return;
        }
        return FileManagerQuery::FSPathGetByInfo($db, $finfo);
    }

    public static function FSPathGetByInfo(Ab_Database $db, $fi){
        return FileManagerQuery::FSPathGetByEls($fi['uid'], $fi['fdid'], $fi['fsnm']);
    }

    public static function FSPathGetByEls($userid, $folderid, $fsname){
        if (empty($fsname)){
            return "";
        }
        return CWD."/modules/filemanager/upload/".$userid."/".$folderid."/".$fsname;
    }

    /**
     * Получить информацию о файле
     */
    public static function FileInfo(Ab_Database $db, $filehash, $withFSName = false){
        $select = FileManagerQuery::FILE_FIELD;
        if ($withFSName){
            $select .= ",fsname as fsnm ";
        }
        $sql = "
			SELECT ".$select." 
			FROM ".$db->prefix."fm_file
			WHERE filehash = '".bkstr($filehash)."'
			LIMIT 1
		";
        return $db->query_first($sql);
    }

    public static function FileInfoByName(Ab_Database $db, $userid, $folderid, $filename){
        $sql = "
			SELECT
				".FileManagerQuery::FILE_FIELD."
			FROM ".$db->prefix."fm_file
			WHERE
				userid=".bkint($userid)." 
				AND folderid=".bkint($folderid)." 
				AND filename='".bkstr($filename)."'
			LIMIT 1
		";
        return $db->query_first($sql);
    }

    public static function FileListInFolder(Ab_Database $db, $folderid){
        $sql = "
			SELECT 
				".FileManagerQuery::FILE_FIELD."
			FROM ".$db->prefix."fm_file
			WHERE folderid=".bkint($folderid)."
		";
        return $db->query_read($sql);
    }

    public static function FileList(Ab_Database $db, $userid, $folderId, $attribute = -1){
        $sql = "
			SELECT 
				".FileManagerQuery::FILE_FIELD."
			FROM ".$db->prefix."fm_file
			WHERE userid = ".bkint($userid)."
			".($attribute > -1 ? " AND attribute=".$attribute : "")."
			AND folderid='".bkstr($folderId)."'
			ORDER BY filename, dateline
		";
        return $db->query_read($sql);
    }

    /**
     * Генерация 8-и битного ключа
     */
    public static function GenerateFileHash($i = 0){
        return substr(md5(time() + $i), 0, 8);
    }

    private static function FileHashCheck(Ab_Database $db, $filehash){
        $row = $db->query_first("
			SELECT filehash
			FROM ".$db->prefix."fm_file
			WHERE filehash = '".bkstr($filehash)."'
			LIMIT 1
		");
        return !empty($row);
    }

    public static function GetFileHash(Ab_Database $db){
        $i = 0;
        do {
            $filehash = FileManagerQuery::GenerateFileHash($i++);
        } while (FileManagerQuery::FileHashCheck($db, $filehash));

        return $filehash;
    }

    public static function FileUpload(Ab_Database $db, $userid, $folderid, $filename, $filedata, $filesize, $extension, $isimage = 0, $imgwidth = 0, $imgheight = 0, $attribute = 0){
        $filehash = FileManagerQuery::GetFileHash($db);
        $sql = "
			INSERT INTO ".$db->prefix."fm_file 
				(filehash, userid, filename, filedata, filesize, extension, isimage, imgwidth, imgheight, attribute, folderid, dateline ) VALUES (
				'".bkstr($filehash)."',
				'".bkint($userid)."',
				'".bkstr($filename)."',
				'".addslashes($filedata)."',
				'".bkint($filesize)."',
				'".bkstr($extension)."',
				'".bkstr($isimage)."',
				'".bkint($imgwidth)."',
				'".bkint($imgheight)."',
				'".bkstr($attribute)."',
				'".bkint($folderid)."',
				'".TIMENOW."'".
            ")";
        $db->query_write($sql);
        return $filehash;
    }

    public static function FileUploadPart(Ab_Database $db, $filehash, $data){
        $sql = "
			UPDATE ".$db->prefix."fm_file
			SET filedata=CONCAT(filedata, '".addslashes($data)."')
			WHERE filehash='".bkstr($filehash)."'
		";
        $db->query_write($sql);
    }

    public static function FileTypeUpdateMime(Ab_Database $db, $fileTypeId, $mimeType){
        $sql = "
			UPDATE ".$db->prefix."fm_filetype 
			SET mimetype = '".$mimeType."'
			WHERE filetypeid = ".bkint($fileTypeId)." 
			LIMIT 1
		";
        $db->query_write($sql);
    }

    public static function EnThumbsCheck(Ab_Database $db, $width, $height){
        $sql = "
			SELECT
				width as w,
				height as h 
			FROM ".$db->prefix."fm_enthumbs 
			WHERE width=".bkint($width)." AND height=".bkint($height)."
			LIMIT 1
		";
        $row = $db->query_first($sql);
        return !empty($row);
    }

    public static function GroupList(Ab_Database $db){
        $sql = "
			SELECT
				groupid as id,
				groupname as gnm
			FROM ".$db->prefix."group
		";
        return $db->query_read($sql);
    }

    public static function UserGroupLimitUpdate(Ab_Database $db, $d){
        $sql = "
			UPDATE ".$db->prefix."fm_usergrouplimit
			SET flimit=".bkint($d->lmt)."
			WHERE usergrouplimitid=".bkint($d->id)."
		";
        $db->query_write($sql);
    }

    public static function UserGroupLimitAppend(Ab_Database $db, $d){
        $row = FileManagerQuery::UserGroupLimit($db, $d->gid, true);
        if (!empty($row)){
            return 0;
        }
        $sql = "
			INSERT INTO ".$db->prefix."fm_usergrouplimit (usergroupid, flimit) VALUES (
				".bkint($d->gid).",
				".bkint($d->lmt)."
			)
		";
        $db->query_write($sql);
        return $db->insert_id();
    }

    public static function UserGroupLimitRemove(Ab_Database $db, $limitid){
        $sql = "
			DELETE FROM ".$db->prefix."fm_usergrouplimit
			WHERE usergrouplimitid=".bkint($limitid)."
		";
        $db->query_write($sql);
    }


    public static function UserGroupLimitList(Ab_Database $db){
        $sql = "
			SELECT
				a.usergrouplimitid as id,
				a.usergroupid as gid,
				a.flimit as lmt,
				b.groupname as gnm
			FROM ".$db->prefix."fm_usergrouplimit a
			INNER JOIN ".$db->prefix."group b ON b.groupid=a.usergroupid
		";
        return $db->query_read($sql);
    }

    public static function UserGroupLimit(Ab_Database $db, $groupid, $retarray = false){
        $sql = "
			SELECT
				a.usergrouplimitid as id,
				a.usergroupid as gid,
				a.flimit as lmt,
				b.groupname as gnm
			FROM ".$db->prefix."fm_usergrouplimit a
			INNER JOIN ".$db->prefix."group b ON b.groupid=a.usergroupid
			WHERE a.usergroupid=".bkint($groupid)."
			ORDER BY a.flimit DESC
			LIMIT 1
		";
        return $retarray ? $db->query_first($sql) : $db->query_read($sql);
    }

    public static function FileTypeList(Ab_Database $db){
        $sql = "
			SELECT a.filetypeid as id, a.*
			FROM ".$db->prefix."fm_filetype a
			ORDER BY extension
		";
        return $db->query_read($sql);
    }

    public static function FileTypeUpdate(Ab_Database $db, $d){
        $sql = "
			UPDATE ".$db->prefix."fm_filetype
			SET
				extension='".bkstr($d->extension)."',
				mimetype='".bkstr($d->mimetype)."',
				maxsize=".bkint($d->maxsize).",
				maxwidth=".bkint($d->maxwidth).",
				maxheight=".bkint($d->maxheight)."
			WHERE filetypeid=".bkint($d->filetypeid)."
		";
        $db->query_write($sql);
    }

    public static function FileTypeAppend(Ab_Database $db, $d){
        $sql = "
			INSERT INTO ".$db->prefix."fm_filetype (extension, mimetype, maxsize, maxwidth, maxheight) VALUES (
				'".bkstr($d->extension)."',
				'".bkstr($d->mimetype)."',
				".bkint($d->maxsize).",
				".bkint($d->maxwidth).",
				".bkint($d->maxheight)."
			)
		";
        $db->query_write($sql);
    }
}

?>
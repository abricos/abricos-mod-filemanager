<?php
/**
 * @package Abricos
 * @subpackage FileManager
 * @copyright 2008-2016 Alexander Kuzmin
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @author Alexander Kuzmin <roosit@abricos.org>
 */

require_once 'dbquery.php';

class UploadError {
    /**
     * Нет ошибки - 0
     *
     * @var integer
     */
    const NO_ERROR = 0;

    /**
     * Неизвестный тип файла - 1
     *
     * @var integer
     */
    const UNKNOWN_TYPE = 1;

    /**
     * Размер файла превышает допустимый - 2
     *
     * @var integer
     */
    const FILESIZE_IS_LARGER = 2;

    /**
     * Ошибка сервера - 3
     *
     * @var integer
     */
    const SERVER_ERROR = 3;

    /**
     * Размер картинки (в пикселях) превышает допустимый - 4
     *
     * @var integer
     */
    const IMAGESIZE_IS_LARGER = 4;

    /**
     * Свободное место в профиле закончилось - 5
     *
     * @var integer
     */
    const PROFILE_FREESPACE = 5;

    /**
     * Нет прав на выгрузку - 6
     *
     * @var integer
     */
    const ACCESS_DENIED = 6;

    /**
     * Файл с таким именем уже существует - 7
     *
     * @var integer
     */
    const FILE_EXISTS = 7;

    /**
     * Файла для выгрузки отсутствует (не выбран файл) - 8
     *
     * @var integer
     */
    const FILE_NOT_FOUND = 8;

    /**
     * Не удалось преобразовать картинку (подогнать по размеру и т.п.) - 9
     *
     * @var integer
     */
    const IMAGE_PROCESS = 9;

    /**
     * Было заявлено на загрузку картинки, а файл не картинка - 10
     *
     * @var integer
     */
    const IS_NOT_IMAGE = 10;

}

class UploadFile {

    /**
     * @var User
     */
    private $user = null;
    private $userid = 0;

    /**
     * @var FileManager
     */
    public $manager = null;

    ////////////////// настройки ///////////////

    /**
     * Игнорировать роль на выгрузку
     *
     * @var boolean default false
     */
    public $ignoreUploadRole = false;

    /**
     * Загрузить в глобальное хранилище (не в профиль пользователя)
     *
     * @var boolean default false
     */
    public $outUserProfile = false;

    /**
     * Отключить проверку на допустимый тип файла
     *
     * @var boolean default false
     */
    public $ignoreFileExtension = false;

    /**
     * Отключить проверку на наличия места в профиле
     *
     * @var boolean default false
     */
    public $ignoreFreeSpace = false;

    /**
     * Отключить проверку на допустимый размер файла.
     * Если $ignoreFileExtension==true, этот параметр не используется.
     *
     * @var boolean default false
     */
    public $ignoreFileSize = false;

    /**
     * Отключить проверку на допустимый размер картинки.
     *
     * @var boolean default false
     */
    public $ignoreImageSize = false;

    /**
     * Максимальная ширина картинки.
     * Если больше нуля, то игнорирует глобальные настройки разрешенных лимитов.
     * Если $ignoreImageSize==true, этот параметр не используется.
     *
     * @var integer default 0
     */
    public $maxImageWidth = 0;

    /**
     * Максимальная высота картинки.
     * Если больше нуля, то игнорирует глобальные настройки разрешенных лимитов
     * Если $ignoreImageSize==true, этот параметр не используется.
     *
     * @var integer default 0
     */
    public $maxImageHeight = 0;

    /**
     * Конвертировать изображение
     *
     * @var string
     */
    public $imageConvertTo = '';

    /**
     * Загружаемый файл должен быть картинкой.
     *
     * @var integer default false
     */
    public $isOnlyImage = false;

    /**
     * Атрибут файла: 0 - стандартый, 1 - скрытый
     *
     * @var integer
     */
    public $fileAttribute = 0;

    /**
     * Открытое имя файла
     * Если параметр не задан, имя файла устанавливается оригинальное
     *
     * @var string
     */
    public $filePublicName = '';

    /**
     * Разрешенные типы файлов и их размеры
     * Если null, то применяется правила настроек модуля
     * Параметр должен содержать именованный массив: array(
     *  '[extension]' => array(
     *    'maxsize' => [допустимый_размер_файла]
     *  ), ...
     * )
     *
     * @var array|null
     */
    public $cfgFileExtensions = null;

    public $filePath = '';
    public $fileName = '';
    public $folderid = 0;

    public $uploadFileHash = '';
    public $folderPath = '';


    /**
     * @var array $file $_FILES['form_field']
     */
    public $file = null;

    /**
     * Конструктор
     *
     * @param $filePath путь файла
     * @param $fileName имя файла в менеджере файлов
     * @param $folderid идентификатор папки в менеджере файлов
     */
    public function __construct($filePath, $fileName = '', $folderid = 0){

        $this->manager = FileManagerModule::$instance->GetFileManager();

        $this->user = Abricos::$user;
        $this->userid = $this->user->id;

        $this->filePath = $filePath;
        if (empty($fileName)){
            $pi = pathinfo($filePath);
            $fileName = $pi['basename'];
        }
        $this->fileName = $fileName;
        $this->filePublicName = $fileName;
        $this->folderid = $folderid;

        $this->db = Abricos::$db;
    }

    public function IsFileUploadRole(){
        return FileManagerModule::$instance->permission->CheckAction(FileManagerAction::FILES_UPLOAD) > 0;
    }

    /**
     * Загрузить картинку в базу
     *
     * @return integer 0 - нет ошибки, иначе идентификатор ошибки
     */
    public function Upload(){
        // попытка загрузить не выбранный файл
        if (!file_exists($this->filePath)){
            return UploadError::FILE_NOT_FOUND;
        }
        $log = "";
        // проверка роли на выгрузку файла
        if (!$this->ignoreUploadRole){
            if (!$this->IsFileUploadRole()){
                return UploadError::ACCESS_DENIED;
            }
        } else {
            $this->outUserProfile = true;
        }

        // выгрузка в профиль или глобальное хранилище?
        $userid = $this->userid;
        if (!$this->outUserProfile){
            if (intval($this->userid) == 0){
                return UploadError::ACCESS_DENIED;
            }
        } else {
            $userid = 0;
        }

        $fName = $this->fileName;
        $pi = pathinfo($this->fileName);
        $fExt = strtolower($pi['extension']);
        $fPath = $this->filePath;
        $fSize = filesize($fPath);

        if (!file_exists($fPath)){
            return UploadError::SERVER_ERROR;
        }

        // есть ли свободное место в профиле пользователя?
        if (!$this->outUserProfile && !$this->ignoreFreeSpace){
            $freespace = $this->manager->GetFreeSpaceMethod($this->userid);
            // TODO: возможно есть смысл делать эту проверку после того, как картинка будет сжата
            if ($freespace < $fSize){
                return UploadError::PROFILE_FREESPACE;
            }
        }

        $maxFileSize = 0;
        $maxImageWidth = intval($this->maxImageWidth);
        $maxImageHeight = intval($this->maxImageHeight);
        $imageWidth = 0;
        $imageHeight = 0;

        // upload для обработки картинок
        if (is_array($this->file)){
            $upload = $this->manager->GetUploadLib($this->file);
        } else {
            $upload = $this->manager->GetUploadLib($fPath);
        }

        // разрешенные типы файлов
        if (!$this->ignoreFileExtension){ // проверка на допустимые типы файлов включена
            if (!empty($this->cfgFileExtensions) && count($this->cfgFileExtensions)){
                $extensions = $this->cfgFileExtensions;
            } else {
                $extensions = $this->manager->GetFileExtensionList(true);
            }
            $filetype = $extensions[$fExt];
            if (empty($filetype)){ // нет в списке разрешенных типов файлов
                return UploadError::UNKNOWN_TYPE;
            }
            if (!$this->ignoreFileSize){
                $maxFileSize = intval($filetype['maxsize']);
            }
            if ($maxImageWidth == 0){
                $maxImageWidth = intval($filetype['maxwidth']);
            }
            if ($maxImageHeight == 0){
                $maxImageHeight = intval($filetype['maxheight']);
            }
            if (empty($filetype['mimetype'])){
                FileManagerQuery::FileTypeUpdateMime(Abricos::$db, $filetype['filetypeid'], $upload->file_src_mime);
                $filetype['mimetype'] = $upload->file_src_mime;
            }
        }

        // проверка допустимого размера файла
        if ($maxFileSize > 0 && $fSize > $maxFileSize){
            return UploadError::FILESIZE_IS_LARGER;
        }

        // Если файл должен быть только картинкой
        if ($this->isOnlyImage && !$upload->file_is_image){
            return UploadError::IS_NOT_IMAGE;
        }

        $upload->file_new_name_body = $this->filePublicName;
        $fName = $this->filePublicName;

        // для картинки необходимо выполнить возможные преобразования
        if ($upload->file_is_image && !$this->ignoreImageSize
            && (($maxImageWidth > 0 && $upload->image_src_x > $maxImageWidth)
                || ($maxImageHeight > 0 && $upload->image_src_y > $maxImageHeight))
        ){

            $upload->image_resize = true;

            if ($maxImageWidth > 0 && $maxImageHeight > 0){
                $w = $upload->image_src_x;
                $h = $upload->image_src_y;

                if ($w > $maxImageWidth){
                    $pr = $maxImageWidth / $w;
                    $w = $w * $pr;
                    $h = $h * $pr;
                }
                if ($h > $maxImageHeight){
                    $pr = $maxImageHeight / $h;
                    $w = $w * $pr;
                    $h = $h * $pr;
                }
                $upload->image_x = $w;
                $upload->image_y = $h;

            } else {
                if ($maxImageWidth > 0 && $upload->image_src_x > $maxImageWidth){
                    $upload->image_x = $maxImageWidth;
                    $upload->image_ratio_y = true;
                }
                if ($maxImageHeight > 0 && $upload->image_src_y > $maxImageHeight){
                    $upload->image_y = $maxImageHeight;
                    $upload->image_ratio_x = true;
                }
            }

            // необходимо ли конвертировать картинку
            if (!empty($this->imageConvertTo)){
                $upload->image_convert = $this->imageConvertTo;
                $fExt = $this->imageConvertTo;
                $pi = pathinfo($upload->file_new_name_body);
                $upload->file_new_name_body = $pi['filename'];
            }

            $upload->process(CWD."/cache");

            $fName = $upload->file_dst_name;

            if (Abricos::$config['Misc']['develop_mode']){
                $log .= $upload->log;
                @file_put_contents(CWD."/cache/#uploadlog.html", $log);
            }

            $fPath = $upload->file_dst_pathname;

            if (!file_exists($fPath)){
                return UploadError::IMAGE_PROCESS;
            }
            $fSize = filesize($fPath);
        }

        $isUnlink = !empty($upload->file_dst_name) && $upload->file_src_name != $upload->file_dst_name;
        $log .= "Unlink Source File=".($isUnlink ? "true" : "false")."<br />";

        if ($upload->file_is_image){
            $imageWidth = $upload->image_dst_x;
            $imageHeight = $upload->image_dst_y;
            // Обязательно необходимо указывать именно размер источника
            // TODO: необходимо исправить из-за новой технологии конвертации картинок
            // $imageWidth = $upload->image_src_x;
            // $imageHeight = $upload->image_src_y;
        }

        // установить идентификатор директории, если есть
        if ($this->folderid == 0 && !empty($this->folderPath)){
            $this->folderid = $this->manager->CreateFolderByPathMethod($this->folderPath);
        }

        $db = Abricos::$db;

        if ($userid > 0){
            // а вдруг этот файл грузят второй раз?
            $finfo = FileManagerQuery::FileInfoByName($db, $userid, $this->folderid, $fName);
            if (!empty($finfo)){ // точно! так оно и есть.
                // а может быть этот файл тот же самый?
                if (intval($fSize) == intval($finfo['fs'])){ // размеры совпадают, нужно сравнить побайтно
                    if ($this->manager->FilesCompare($fPath, $finfo['fh'])){
                        $this->uploadFileHash = $finfo['fh'];
                        if ($isUnlink)
                            @unlink($fPath);
                        return UploadError::NO_ERROR;
                    }
                }
                // у этих файлов одинаковое только имя
                // TODO: необходимо создавать новое имя файла, и делать повторно попытку его загрузки
            }
        }
        // все нормально, теперь можно загружать файл в базу
        $handle = fopen($fPath, 'rb');
        if (empty($handle)){
            if ($isUnlink)
                @unlink($fPath);
            return UploadError::SERVER_ERROR;
        }
        $first = true;
        $filehash = '';
        while (!feof($handle)){
            $data = fread($handle, 1048576);

            if ($first){
                $first = false;
                $filehash = FileManagerQuery::FileUpload(
                    Abricos::$db, $userid, $this->folderid,
                    $fName, $data, $fSize, $fExt,
                    ($upload->file_is_image ? 1 : 0),
                    $imageWidth, $imageHeight, $this->fileAttribute
                );
            } else {
                FileManagerQuery::FileUploadPart(Abricos::$db, $filehash, $data);
            }
        }
        fclose($handle);

        if (empty($filehash) || Abricos::$db->IsError()){
            if ($isUnlink)
                @unlink($fPath);
            return UploadError::SERVER_ERROR;
        }
        $this->uploadFileHash = $filehash;
        if ($isUnlink)
            @unlink($fPath);

        return UploadError::NO_ERROR;
    }
}

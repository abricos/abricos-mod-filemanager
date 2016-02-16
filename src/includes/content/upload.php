<?php
/**
 * @package Abricos
 * @subpackage FileManager
 * @copyright 2008-2016 Alexander Kuzmin
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @author Alexander Kuzmin <roosit@abricos.org>
 */

$fileManager = FileManagerModule::$instance->GetManager();

if (!$fileManager->IsFileUploadRole()){
    return;
}

$brick = Brick::$builder->brick;
$var = &$brick->param->var;

$p_userid = Abricos::CleanGPC('g', 'userid', TYPE_STR);
$p_winid = Abricos::CleanGPC('g', 'winid', TYPE_STR);
$p_sysfolder = Abricos::CleanGPC('g', 'sysfolder', TYPE_STR);
$p_folderid = Abricos::CleanGPC('g', 'folderid', TYPE_STR);
$p_folderpath = Abricos::CleanGPC('g', 'folderpath', TYPE_STR);

// формирование списка разрешенных типов файлов и их макс. размеры
$list = "";

$fexts = $fileManager->GetFileExtensionList();

foreach ($fexts as $key => $value){
    $imgSize = "&nbsp;";
    if (!empty($value['maxwidth'])){
        $imgSize = $value['maxwidth']."x".$value['maxheight'];
    }
    $list .= Brick::ReplaceVarByData($brick->param->var["ftypelsti"], array(
        "ext" => $key,
        "fsize" => round(($value['maxsize'] / 1024))." Kb",
        "imgsize" => $imgSize
    ));
}
$freeSpace = $fileManager->GetFreeSpace();

$brick->content = Brick::ReplaceVarByData($brick->content, array(
    "freespace" => (round($freeSpace / 1024 / 1024))." mb",
    "userid" => $p_userid,
    "winid" => $p_winid,
    "folderid" => $p_folderid,
    "folderpath" => $p_folderpath,
    "sysfolder" => $p_sysfolder,
    "ftypelst" => $list
));


$p_do = Abricos::CleanGPC('g', 'do', TYPE_STR);
if ($p_do !== "upload"){
    return;
}


$uploadFile = $fileManager->CreateUploadByVar('uploadfile');
if ($p_folderid > 0){
    $uploadFile->folderid = $p_folderid;
} else if (!empty($p_folderpath)){
    $uploadFile->folderPath = $p_folderpath;
} else if ($p_sysfolder){
    $uploadFile->folderPath = "system/".date("d.m.Y", TIMENOW);
}
$error = $uploadFile->Upload();

if ($error == 0){
    $var['command'] = Brick::ReplaceVarByData($var['ok'], array(
        "winid" => $p_winid,
        "fhash" => $uploadFile->uploadFileHash,
        "fname" => $uploadFile->fileName
    ));
} else {
    $var['command'] = Brick::ReplaceVarByData($var['error'], array(
        "errnum" => $error
    ));

    $brick->content = Brick::ReplaceVarByData($brick->content, array(
        "fname" => $uploadFile->fileName
    ));
}


?>
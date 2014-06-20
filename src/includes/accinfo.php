<?php
/**
 * @package Abricos
 * @subpackage FileManager
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @author Alexander Kuzmin <roosit@abricos.org>
 */


$fileManager = FileManagerModule::$instance->GetManager();

$brick = Brick::$builder->brick;

if (!$fileManager->IsFileUploadRole()){ 
	$brick->content = "";
	return;
}

$p = &$brick->param->param;
$v = &$brick->param->var;

// формирование списка разрешенных типов файлов и их макс. размеры
$list = "";

if (!empty($p['fextobj'])){
	$fexts = $p['fextobj'];
}else{
	$fexts = $fileManager->GetFileExtensionList();
}

foreach ($fexts as $key => $value){
	$imgSize = "&nbsp;";
	if (!empty($value['maxwidth'])){
		$imgSize = $value['maxwidth']."x".$value['maxheight'];
	}
	$list .= Brick::ReplaceVarByData($v["ftypelsti"], array(
		"ext" => $key,
		"fsize" => round(($value['maxsize']/1024))." Kb",
		"imgsize" => $imgSize 
	));
}
$freeSpace = $fileManager->GetFreeSpace();

$brick->content = Brick::ReplaceVarByData($brick->content, array(
	"userspace" => $p['hideuserspace'] ? "" : Brick::ReplaceVarByData($v['userspace'], array(
		"freespace" => (round($freeSpace/1024/1024))." mb",
	)),
	"userid" => Abricos::$user->id,
	"clerrtext" => ($freeSpace <= 0 ? 'errtext' : ''),
	"ftypelst" => $list
));

?>
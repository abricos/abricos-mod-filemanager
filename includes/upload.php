<?php
/**
 * @version $Id$
 * @package Abricos
 * @subpackage FileManager
 * @copyright Copyright (C) 2008 Abricos All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @author Alexander Kuzmin (roosit@abricos.org)
 */


$fileManager = FileManagerModule::$instance->GetManager();

if (!$fileManager->IsFileUploadRole()){ return; }

$back = Brick::$builder->brick;

$folderid = Brick::$input->clean_gpc('g', 'folderid', TYPE_STR);
$brick->param->var['fid'] = $folderid;

// формирование списка разрешенных типов файлов и их макс. размеры
$list = "";

$fileExtensionList = $fileManager->GetFileExtensionList();

foreach ($fileExtensionList as $key => $value){
	$imgSize = "&nbsp;";
	if (!empty($value['maxwidth'])){
		$imgSize = $value['maxwidth']."x".$value['maxheight'];
	}
	$list .= Brick::ReplaceVarByData($brick->param->var["ftypelsti"], array(
		"1" => $key,
		"2" => round(($value['maxsize']/1024))." Kb",
		"3" => $imgSize 
	));
}
$brick->param->var['ftypelst'] = $list;


$freeSpace = $fileManager->GetFreeSpace();
$brick->param->var['freespace'] = (round($freeSpace/1024/1024))." mb";


$p_do = Brick::$input->clean_gpc('g', 'do', TYPE_STR);
if ($p_do == "upload"){
	$p_file = Brick::$input->clean_gpc('f', 'uploadfile', TYPE_FILE);
	$errornum = $fileManager->UploadFiles($folderid, $p_file);
	if ($errornum > 0){
		$errorText = 
			str_replace("#1",
				$brick->param->var['err'.$errornum],
				$brick->param->var['errt']
			);
		$errorText = str_replace("#2",	$p_file['name'], $errorText);
		$brick->param->var['err'] = $errorText; 
	}else{
		$brick->param->var['onload'] = str_replace("#fid#", $folderid, $brick->param->var['onloads']); 
	}
}

?>
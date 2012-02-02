<?php
/**
 * @version $Id$
 * @package Abricos
 * @subpackage FileManager
 * @copyright Copyright (C) 2008 Abricos All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @author Alexander Kuzmin (roosit@abricos.org)
 */

$list = Brick::$builder->brick->param->param['list'];
$arr = explode("/", $list);
$size = array();
foreach($arr as &$arr1){
	$arr2 = explode("x", $arr1);
	array_push($size, array(
		"w" => $arr2[0],
		"h" => $arr2[1] 
	));
}
Abricos::GetModule('filemanager')->EnableThumbSize($size);

?>
<?php
/**
 * @package Abricos
 * @subpackage FileManager
 * @copyright 2008-2016 Alexander Kuzmin
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @author Alexander Kuzmin <roosit@abricos.org>
 */

$list = Brick::$builder->brick->param->param['list'];
$arr = explode("/", $list);
$size = array();
foreach ($arr as &$arr1){
    $arr2 = explode("x", $arr1);
    array_push($size, array(
        "w" => $arr2[0],
        "h" => $arr2[1]
    ));
}
Abricos::GetModule('filemanager')->EnableThumbSize($size);

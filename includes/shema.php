<?php
/**
 * Схема таблиц модуля
 * @version $Id$
 * @package Abricos
 * @subpackage FileManager
 * @copyright Copyright (C) 2008 Abricos All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @author Alexander Kuzmin (roosit@abricos.org)
 */


$charset = "CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'";
$updateManager = CMSRegistry::$instance->modules->updateManager; 
$db = CMSRegistry::$instance->db;
$pfx = $db->prefix;

if ($updateManager->isInstall()){
	// таблица файлов
	$db->query_write("
		CREATE TABLE IF NOT EXISTS ".$pfx."fm_file (
		  `fileid` int(10) unsigned NOT NULL auto_increment,
		  `userid` int(10) unsigned NOT NULL,
		  `filehash` varchar(8) NOT NULL,
		  `filename` varchar(250) NOT NULL default '',
		  `title` VARCHAR( 250 ) NOT NULL default '',
		  `filedata` mediumblob,
		  `filesize` int(10) unsigned NOT NULL default 0,
		  `extension` varchar(20) NOT NULL default '',
		  `counter` int(10) unsigned NOT NULL default '0',
		  `lastget` int(10) unsigned NOT NULL default '0',
		  `dateline` int(10) unsigned NOT NULL,
		  `deldate` int(10) unsigned NOT NULL default '0',
		  `attribute` int(6) unsigned NOT NULL default '0',
		  `isimage` int(1) unsigned NOT NULL default '0',
		  `imgwidth` int(6) unsigned NOT NULL default '0',
		  `imgheight` int(6) unsigned NOT NULL default '0',
		  `folderid` int(10) unsigned NOT NULL default '0',
		  PRIMARY KEY  (`fileid`),
		  UNIQUE KEY `filehash` (`filehash`),
		  KEY `userid` (`userid`)
		)".$charset
	);
	
	// таблица типов файлов и их параметры
	$db->query_write("
		CREATE TABLE IF NOT EXISTS ".$pfx."fm_filetype (
		  `filetypeid` int(5) unsigned NOT NULL auto_increment,
		  `extension` varchar(20) NOT NULL default '',
		  `usergroupid` int(5) unsigned NOT NULL,
		  `mimetype` varchar(50) NOT NULL default '',
		  `maxsize` int(10) unsigned NOT NULL default '0',
		  `maxwidth` int(5) unsigned NOT NULL default '0',
		  `maxheight` int(5) unsigned NOT NULL default '0',
		  `disable` tinyint(1) unsigned NOT NULL default '0',
		  PRIMARY KEY  (`filetypeid`),
		  KEY `usergroupid` (`usergroupid`)
		)".$charset
	);
	
	// список разрешеных типов файлов и их параметры
	$db->query_write("
		INSERT INTO `".$pfx."fm_filetype` 
		(`extension`, `usergroupid`, `mimetype`, `maxsize`, `maxwidth`, `maxheight`, `disable`) VALUES 
		('bmp', 0, '', 1048576, 1024, 768, 0),
		('gif', 0, 'image/gif', 1048576, 1024, 1024, 0),
		('jpe', 0, 'image/jpeg', 1048576, 1024, 1024, 0),
		('jpeg', 0, 'image/jpeg', 1048576, 1024, 1024, 0),
		('jpg', 0, 'image/jpeg', 1048576, 1024, 1024, 0),
		('doc', 0, 'application/msword', 1048576, 0, 0, 0),
		('xls', 0, 'application/msexcel', 1048576, 0, 0, 0),
		('pdf', 0, 'application/pdf', 1048576, 0, 0, 0),
		('png', 0, 'image/png', 1048576, 1024, 1024, 0),
		('txt', 0, 'text/plain', 1048576, 0, 0, 0),
		('rar', 0, 'application/rar', 1048576, 0, 0, 0),
		('zip', 0, 'application/zip', 1048576, 0, 0, 0)	
	");
	
	$db->query_write("
		CREATE TABLE IF NOT EXISTS ".$pfx."fm_folder (
		  `folderid` int(10) unsigned NOT NULL auto_increment,
		  `parentfolderid` int(10) unsigned NOT NULL default '0',
		  `userid` int(10) unsigned NOT NULL,
		  `name` varchar(100) NOT NULL,
		  `phrase` varchar(250) NOT NULL,
		  `dateline` int(10) unsigned NOT NULL default '0',
		  `deldate` int(10) unsigned NOT NULL default '0',
		  PRIMARY KEY  (`folderid`),
		  KEY `parentfolderid` (`parentfolderid`,`userid`)
		)".$charset
	);
	
	$db->query_write("
		CREATE TABLE IF NOT EXISTS ".$pfx."fm_imgprev (
		  `imgprevid` int(10) unsigned NOT NULL auto_increment,
		  `filehashsrc` varchar(8) NOT NULL,
		  `width` int(6) NOT NULL,
		  `height` int(6) NOT NULL,
		  `cnv` varchar(20) default NULL,
		  `filehashdst` varchar(8) NOT NULL,
		  PRIMARY KEY  (`imgprevid`),
		  KEY `filehashsrc` (`filehashsrc`)
		)".$charset
	);
	
	$db->query_write("
		CREATE TABLE IF NOT EXISTS ".$pfx."fm_usergrouplimit (
		  `usergrouplimitid` int(4) unsigned NOT NULL auto_increment,
		  `usergroupid` int(4) unsigned NOT NULL,
		  `flimit` int(10) unsigned NOT NULL,
		  PRIMARY KEY  (`usergrouplimitid`)
		)".$charset
	);

	// таблица для хранения изменений в редакторе картинок
	$db->query_write("
		CREATE TABLE IF NOT EXISTS ".$pfx."fm_editor (
		  `editorid` int(10) unsigned NOT NULL auto_increment,
		  `userid` int(10) unsigned NOT NULL COMMENT 'Идентификатор пользователя',
		  `filehashsrc` varchar(8) NOT NULL COMMENT 'Картинка источник',
		  `width` int(6) unsigned NOT NULL DEFAULT 0,
		  `height` int(6) unsigned NOT NULL DEFAULT 0,
		  `left` int(6) unsigned NOT NULL DEFAULT 0,
		  `top` int(6) unsigned NOT NULL DEFAULT 0,
		  `tools` varchar(20) default NULL,
		  `filehashdst` varchar(8) NOT NULL,
		  `dateline` int(10) unsigned NOT NULL DEFAULT 0,
		  `session` int(10) unsigned NOT NULL DEFAULT 0,
		  PRIMARY KEY  (`editorid`),
		  KEY `filehashsrc` (`filehashsrc`)
		)".$charset
	);
}
if ($updateManager->serverVersion == "1.0.0"){
	$db->query_write("
		ALTER TABLE `".$pfx."fm_file` ADD `title` VARCHAR( 250 ) NOT NULL AFTER `filename`
	");

	$db->query_write("
		CREATE TABLE IF NOT EXISTS ".$pfx."fm_editor (
		  `editorid` int(10) unsigned NOT NULL auto_increment,
		  `userid` int(10) unsigned NOT NULL ,
		  `filehashsrc` varchar(8) NOT NULL ,
		  `width` int(6) unsigned NOT NULL DEFAULT 0,
		  `height` int(6) unsigned NOT NULL DEFAULT 0,
		  `left` int(6) unsigned NOT NULL DEFAULT 0,
		  `top` int(6) unsigned NOT NULL DEFAULT 0,
		  `tools` varchar(20) default NULL,
		  `filehashdst` varchar(8) NOT NULL,
		  `dateline` int(10) unsigned NOT NULL DEFAULT 0,
		  `session` int(10) unsigned NOT NULL DEFAULT 0,
		  PRIMARY KEY  (`editorid`),
		  KEY `filehashsrc` (`filehashsrc`)
		)".$charset
	);
}

if ($updateManager->isInstall() || $updateManager->isUpdate('0.3')){
	
	// Добавить поле имени файла в файловой системе
	$db->query_write("
		ALTER TABLE `".$db->prefix."fm_file` 
			ADD `fsname` varchar(250) NOT NULL default '' AFTER `folderid`
	");
	
}

if ( $updateManager->isUpdate('0.3.1')){
	CMSRegistry::$instance->modules->GetModule('filemanager')->permission->Install();
	
	$db->query_write("
		TRUNCATE TABLE `".$pfx."fm_usergrouplimit`
	");
	
	// лимиты объема файлов на группу пользователей
	$db->query_write("
		INSERT INTO `".$pfx."fm_usergrouplimit` (`usergroupid`, `flimit`) VALUES 
		(2, 15728640),
		(3, 104857600)
	");
	
}
if ( $updateManager->isUpdate('0.3.2')){
	
	$db->query_write("
		CREATE TABLE IF NOT EXISTS ".$pfx."fm_enthumbs (
		  `enthumbs` int(4) unsigned NOT NULL auto_increment,
		  `width` int(6) unsigned NOT NULL DEFAULT 0,
		  `height` int(6) unsigned NOT NULL DEFAULT 0,
		  PRIMARY KEY  (`enthumbs`),
		  UNIQUE KEY `size` (`width`,`height`)
		)".$charset
	);
}

?>
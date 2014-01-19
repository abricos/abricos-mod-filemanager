/*
@version $Id$
@package Abricos
@license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/

var Component = new Brick.Component();
Component.requires = {};
Component.entryPoint = function(NS){

	var Dom = YAHOO.util.Dom,
		E = YAHOO.util.Event,
		L = YAHOO.lang,
		R = NS.roles;
	
	var buildTemplate = this.buildTemplate;
	
	var FreeSpiceLineWidget = function(container, config){
		config = L.merge({
			'width': 0,
			'flimit': null,
			'fsize': null
		}, config || {});
		this.init(container, config);
	};
	FreeSpiceLineWidget.prototype = {
		init: function(container, config){
			var TM = buildTemplate(this, 'widget');
		}
	};
	NS.FreeSpiceLineWidget = FreeSpiceLineWidget;
};
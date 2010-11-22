/*
@version $Id$
@package Abricos
@copyright Copyright (C) 2008 Abricos All rights reserved.
@license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/

/**
 * @module Feedback
 * @namespace Brick.mod.feedback
 */
var Component = new Brick.Component();
Component.requires = {
	yahoo: ['tabview','dragdrop'],
	mod:[
		{name: 'filemanager', files: ['api.js']},
		{name: 'sys', files: ['data.js', 'form.js']}
	]
};
Component.entryPoint = function(){
	
	var Dom = YAHOO.util.Dom,
		E = YAHOO.util.Event,
		L = YAHOO.lang;
	
	var NS = this.namespace, 
		TMG = this.template; 
	
	var API = NS.API;
	
	
	if (!NS.data){
		NS.data = new Brick.util.data.byid.DataSet('filemanager');
	}
	var DATA = NS.data;
	
	var buildTemplate = function(widget, templates){
		var TM = TMG.build(templates), T = TM.data, TId = TM.idManager;
		widget._TM = TM; 
		widget._T = T; 
		widget._TId = TId;
	};
	
	var ManagerWidget = function(container){
		
		var TM = TMG.build('widget'), T = TM.data, TId = TM.idManager;
		this._TM = TM; this._T = T; this._TId = TId;

		container = L.isString(container) ? Dom.get(container) : container;
		this.init(container);
	};
	ManagerWidget.prototype = {
		pages: null,
		
		init: function(container){
			container.innerHTML = this._T['widget'];
			var TId = this._TId;
			
			var tabView = new YAHOO.widget.TabView(TId['widget']['id']);
			var pages = {};
			
			pages['limit'] = new NS.UserGroupLimitWidget(Dom.get(TId['widget']['limit']));
			pages['ext'] = new NS.ExtensionFileWidget(Dom.get(TId['widget']['exten']));
			
			this.pages = pages;
	
			var __self = this;
			E.on(container, 'click', function(e){
				if (__self.onClick(E.getTarget(e))){ E.stopEvent(e); }
			});
			DATA.request();
		}, 
		onClick: function(el){
			for (var n in this.pages){
				if (this.pages[n].onClick(el)){ return true; }
			}
			return false;
		}
	};
	NS.ManagerWidget = ManagerWidget;
	API.showManagerWidget = function(container){
		return new NS.ManagerWidget(container);
	};


	var UserGroupLimitWidget = function(container){
		var TM = TMG.build('limitwidget,limitrowwait,limitrow,limittable'), 
			T = TM.data, TId = TM.idManager;
		this._TM = TM; this._T = T; this._TId = TId;

		container = L.isString(container) ? Dom.get(container) : container;
		this.init(container);
	};
	UserGroupLimitWidget.prototype = {
		init: function(container){
			container.innerHTML = this._T['limitwidget'];
			var tables = {
				'usergrouplimit': DATA.get('usergrouplimit', true)
			};
			DATA.onStart.subscribe(this.dsEvent, this, true);
			DATA.onComplete.subscribe(this.dsEvent, this, true);
			if (DATA.isFill(tables)){
				this.renderElements();
			}else{
				this.renderWait();
			}
		},
		dsEvent: function(type, args){
			if (args[0].checkWithParam('usergrouplimit', {})){
				if (type == 'onComplete'){
					this.renderElements(); 
				}else{
					this.renderWait();
				}
			}
		},
		destroy: function(){
			DATA.onComplete.unsubscribe(this.dsEvent);
			DATA.onStart.unsubscribe(this.dsEvent);
		},
		renderElements: function(){
			var TM = this._TM, T = this._T, TId = this._TId, 
				lst = "";
			DATA.get('usergrouplimit').getRows().foreach(function(row){
				var di = row.cell;
				lst += TM.replace('limitrow', {
					'id': di['id'],
					'lmt': di['lmt'],
					'gnm': di['gnm']
				});
			});
			this._TM.getEl('limitwidget.table').innerHTML = this._TM.replace('limittable', {'rows': lst});
		},
		renderWait: function(){
			this._TM.getEl('limitwidget.table').innerHTML = this._TM.replace('limittable', {'rows': this._T['limitrowwait']});
		},
		onClick: function(el){
			var TId = this._TId, tp = TId['limitwidget'];
			switch(el.id){
			case tp['bappend']:  this.editGroupLimit(0); return true;
			}

			var prefix = el.id.replace(/([0-9]+$)/, '');
			var numid = el.id.replace(prefix, "");
			
			switch(prefix){
			case (TId['limitrow']['edit']+'-'):
				this.editGroupLimit(numid);
				return true;
			case (TId['limitrow']['remove']+'-'):
				this.removeGroupLimit(numid);
				return true;
			}
			return false;
		},
		editGroupLimit: function(id){
			var table = DATA.get('usergrouplimit'),
				rows = table.getRows(),
				row = id == 0 ? table.newRow() : rows.getById(id);
			new GroupLimitEditorPanel(row, function(){
				if (id == 0){ rows.add(row); }
				table.applyChanges();
				DATA.request();
			});
		},
		removeGroupLimit: function(id){
			var table = DATA.get('usergrouplimit'),
			rows = table.getRows(),
			row = id == 0 ? table.newRow() : rows.getById(id);
			row.remove();
			table.applyChanges();
			DATA.request();
		}
	};
	NS.UserGroupLimitWidget = UserGroupLimitWidget;
	
	
	var GroupLimitEditorPanel = function(row, callback){
		this.row = row;
		this.tname = row.isNew() ? 'limitappendpanel' : 'limiteditorpanel';
		this.callback = callback;
		GroupLimitEditorPanel.superclass.constructor.call(this, {
			modal: true,
			fixedcenter: true, width: '400px', resize: true
		});
	};
	YAHOO.extend(GroupLimitEditorPanel, Brick.widget.Panel, {
		el: function(name){ return Dom.get(this._TId[this.tname][name]); },
		elv: function(name){ return Brick.util.Form.getValue(this.el(name)); },
		setelv: function(name, value){ Brick.util.Form.setValue(this.el(name), value); },
		initTemplate: function(){
			buildTemplate(this, this.tname+',grouptable,grouprow,grouprowwait');
			return this._T[this.tname];
		},
		onLoad: function(){
			
			DATA.onStart.subscribe(this.dsEvent, this, true);
			DATA.onComplete.subscribe(this.dsEvent, this, true);
			DATA.isFill({
				'grouplist': DATA.get('grouplist', true)
			}) ? this.renderTable() : this.renderWait();  

			var di = this.row.cell;
			this.setelv('size', di['lmt']);
			DATA.request();
		},
		dsEvent: function(type, args){
			if (args[0].checkWithParam('grouplist')){ type == 'onComplete' ? this.renderTable() : this.renderWait(); }
		},
		destroy: function(){
			DATA.onComplete.unsubscribe(this.dsEvent);
			DATA.onStart.unsubscribe(this.dsEvent);
			GroupLimitEditorPanel.superclass.destroy.call(this);
		},
		renderWait: function(){
			var TM = this._TM, T = this._T;
			TM.getEl(this.tname+ '.table').innerHTML = TM.replace('grouptable', {'rows': T['grouprowwait']});
		},
		renderTable: function(){
			var TM = this._TM, lst = "";
			DATA.get('grouplist').getRows().foreach(function(row){
				var di = row.cell;
				lst += TM.replace('grouprow', {
					'id': di['id'],
					'nm': di['gnm']
				});
			});
			TM.getEl(this.tname+'.table').innerHTML = TM.replace('grouptable', {'rows': lst});
			
			var tbl = this._TM.getEl('grouptable.id');
			if (!this.row.isNew()){
				tbl.disabled = 'disabled';
				tbl.value = this.row.cell['gid'];
			}

		},
		onClick: function(el){
			var tp = this._TId[this.tname];
			switch(el.id){
			case tp['bcancel']: this.close(); return true;
			case tp['bsave']: this.save(); return true;
			}
			return false;
		},
		save: function(){
			this.row.update({
				'lmt': this.elv('size'),
				'gid': this._TM.getEl('grouptable.id').value
			});
			this.callback();
			this.close();
		}
	});
	NS.GroupLimitEditorPanel = GroupLimitEditorPanel;
	
	
	
	var ExtensionFileWidget = function(container){
		var TM = TMG.build('extwidget,extrowwait,extrow,exttable'), 
			T = TM.data, TId = TM.idManager;
		this._TM = TM; this._T = T; this._TId = TId;

		container = L.isString(container) ? Dom.get(container) : container;
		this.init(container);
	};
	ExtensionFileWidget.prototype = {
		init: function(container){
			container.innerHTML = this._T['extwidget'];
			var tables = {
				'extensions': DATA.get('extensions', true)
			};
			DATA.onStart.subscribe(this.dsEvent, this, true);
			DATA.onComplete.subscribe(this.dsEvent, this, true);
			DATA.isFill(tables) ? this.renderElements() :  this.renderWait();
		},
		dsEvent: function(type, args){
			if (args[0].checkWithParam('extensions', {})){
				type == 'onComplete' ?  this.renderElements() : this.renderWait();
			}
		},
		destroy: function(){
			DATA.onComplete.unsubscribe(this.dsEvent);
			DATA.onStart.unsubscribe(this.dsEvent);
		},
		renderElements: function(){
			var TM = this._TM, T = this._T, TId = this._TId, 
				lst = "";
			DATA.get('extensions').getRows().foreach(function(row){
				var di = row.cell;
				lst += TM.replace('extrow', {
					'id': di['filetypeid'],
					'ext': di['extension'],
					'mime': di['mimetype'],
					'size': di['maxsize'],
					'width': di['maxwidth'],
					'height': di['maxheight']
				});
			});
			this._TM.getEl('extwidget.table').innerHTML = this._TM.replace('exttable', {'rows': lst});
		},
		renderWait: function(){
			this._TM.getEl('extwidget.table').innerHTML = this._TM.replace('exttable', {'rows': this._T['extrowwait']});
		},
		onClick: function(el){
			var TId = this._TId;
			
			var prefix = el.id.replace(/([0-9]+$)/, '');
			var numid = el.id.replace(prefix, "");
			
			switch(prefix){
			case (TId['extrow']['edit']+'-'):
				this.editExtension(numid);
				return true;
			}

			return false;
		},
		editExtension: function(id){
			var table = DATA.get('extensions'),
				rows = table.getRows(),
				row = id == 0 ? table.newRow() : rows.getById(id);
			new ExtensionEditorPanel(row, function(){
				if (id == 0){ rows.add(row); }
				table.applyChanges();
				DATA.request();
			});
		}
	};
	NS.ExtensionFileWidget = ExtensionFileWidget;
	
	
	var ExtensionEditorPanel = function(row, callback){
		this.row = row;
		this.callback = callback;
		ExtensionEditorPanel.superclass.constructor.call(this, {
			modal: true,
			fixedcenter: true, width: '600px', resize: true
		});
	};
	
	YAHOO.extend(ExtensionEditorPanel, Brick.widget.Panel, {
		el: function(name){ return Dom.get(this._TId['exteditorpanel'][name]); },
		elv: function(name){ return Brick.util.Form.getValue(this.el(name)); },
		setelv: function(name, value){ Brick.util.Form.setValue(this.el(name), value); },
		initTemplate: function(){
			buildTemplate(this, 'exteditorpanel');
			return this._T['exteditorpanel'];
		},
		onLoad: function(){
			var di = this.row.cell;
			this.setelv('ext', di['extension']);
			this.setelv('mime', di['mimetype']);
			this.setelv('size', di['maxsize']);
			this.setelv('width', di['maxwidth']);
			this.setelv('height', di['maxheight']);
		},
		onClick: function(el){
			var tp = this._TId['exteditorpanel'];
			switch(el.id){
			case tp['bcancel']: this.close(); return true;
			case tp['bsave']: this.save(); return true;
			}
			return false;
		},
		save: function(){
			
			this.row.update({
				'extension': this.elv('ext'),
				'mimetype': this.elv('mime'),
				'maxsize': this.elv('size'),
				'maxwidth': this.elv('width'),
				'maxheight': this.elv('height')
			});
			
			this.callback();
			this.close();
		}
	});
	NS.ExtensionEditorPanel = ExtensionEditorPanel;
};
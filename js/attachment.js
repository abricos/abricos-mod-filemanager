/*
@version $Id: draweditor.js 368 2011-08-08 10:07:03Z roosit $
@package Abricos
@copyright Copyright (C) 2011 Abricos All rights reserved.
@license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/

var Component = new Brick.Component();
Component.requires = {
	mod:[
        {name: 'filemanager', files: ['lib.js']}
	]
};
Component.entryPoint = function(){
	
	var Dom = YAHOO.util.Dom,
		E = YAHOO.util.Event,
		L = YAHOO.lang;
	
	var NS = this.namespace, 
		TMG = this.template,
		API = NS.API;
	
	var initCSS = false,
		buildTemplate = function(w, ts){
		if (!initCSS){
			Brick.util.CSS.update(Brick.util.CSS['filemanager']['attachment']);
			initCSS = true;
		}
		w._TM = TMG.build(ts); w._T = w._TM.data; w._TId = w._TM.idManager;
	};
	
	var AttachmentListWidget = function(container, files){
		this.init(container, files);
	};
	AttachmentListWidget.prototype = {
		init: function(container, files){
			buildTemplate(this, 'list,viewrow');
			container.innerHTML = this._TM.replace('list');
			this.setFiles(files);
		},
		setFiles: function(files){
			this.files = files = files || [];
			var alst = [], lst = "", TM = this._TM;
			for (var i=0;i<files.length;i++){
				var f = files[i];
				var lnk = new Brick.mod.filemanager.Linker({
					'id': f['id'],
					'name': f['nm']
				});
				alst[alst.length] = TM.replace('viewrow', {
					'fid': f['id'],
					'nm': f['nm'],
					'src': lnk.getSrc()
				});
			}
			lst = alst.join('');
			TM.getEl('list.table').innerHTML = lst;
		}
	};
	NS.AttachmentListWidget = AttachmentListWidget;
	
	// TODO: Нет роли на загрузку, но есть список - показать только список
	var AttachmentWidget = function(container, files){
		this.init(container, files);
	};
	AttachmentWidget.prototype = {
		init: function(container, files){
			this.files = files;
			
			buildTemplate(this, 'files,ftable,frow');
			container.innerHTML = this._TM.replace('files');
			
			var __self = this;
			E.on(container, 'click', function(e){
                if (__self.onClick(E.getTarget(e))){ E.preventDefault(e); }
			});
			
			this.fileUploader = new NS.FileUploader(Brick.env.user.id, function(fileid, filename){
				__self.onFileUpload(fileid, filename);
			});
			
			this.showButtons(false);
			this.render();
		},
		onClick: function(el){
			var TId = this._TId, tp = TId['files'];
			switch(el.id){
			case tp['bshowbtnsex']:
			case tp['bshowbtns']: this.showButtons(true); return true;
			case tp['bcancel']: this.showButtons(); return true;
			case tp['bshowfm']: this.showFileBrowser(); return true;
			case tp['bupload']: this.fileUploader.fileUpload(); return true;
			}
			
			var arr = el.id.split('-');
			if (arr.length == 2 && arr[0] == TId['frow']['remove']){
				this.removeFile(arr[1]);
				return true;
			}
			return false;
		},
		showButtons: function(en){
			var TM = this._TM;
			TM.elShowHide('files.bshowbtns,bshowbtnsex', false);
			if (this.files.length > 0){
				TM.elShowHide('files.bshowbtnsex', !en);
			}else{
				TM.elShowHide('files.bshowbtns', !en);
			}
			TM.elShowHide('files.fm', en);
		},
		showFileBrowser: function(){
			var __self = this;
			Brick.f('filemanager', 'api', 'showFileBrowserPanel', function(result){
				var fi = result['file'];
				__self.appendFile({
					'id': fi['id'],
					'nm': fi['name'],
					'sz': fi['size']
				});
        	});
		},
		removeFile: function(fid){
			var fs = this.files, nfs = [];
			
			for (var i=0; i<fs.length; i++){
				if (fs[i]['id'] != fid){
					nfs[nfs.length] = fs[i];
				}
			}
			this.files = nfs;
			this.showButtons(false);
			this.render();
		},
		appendFile: function(fi){
			var fs = this.files;
			for (var i=0; i<fs.length; i++){
				if (fs[i]['id'] == fi['id']){ return; }
			}
			fs[fs.length] = fi;
			this.showButtons(false);
			this.render();
		},
		render: function(){
			var TM = this._TM, lst = "", fs = this.files;
			
			for (var i=0; i<fs.length; i++){
				var f = fs[i];
				var lnk = new Brick.mod.filemanager.Linker({
					'id': f['id'],
					'name': f['nm']
				});
				lst	+= TM.replace('frow', {
					'fid': f['id'],
					'nm': f['nm'],
					'src': lnk.getSrc()
				});
			}
			TM.getEl('files.table').innerHTML = fs.length > 0 ? TM.replace('ftable', {
				'rows': lst 
			}) : "";
		},
		onFileUpload: function(fileid, filename){
			this.appendFile({
				'id': fileid,
				'nm': filename
			});
		}
	};
	NS.AttachmentWidget = AttachmentWidget;

};
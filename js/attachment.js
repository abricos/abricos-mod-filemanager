/*
@package Abricos
@license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/

var Component = new Brick.Component();
Component.requires = {
	mod:[
        {name: 'widget', files: ['notice.js']},
        {name: 'filemanager', files: ['lib.js']}
	]
};
Component.entryPoint = function(NS){
	
	var Dom = YAHOO.util.Dom,
		E = YAHOO.util.Event,
		L = YAHOO.lang,
		buildTemplate = this.buildTemplate,
		BW = Brick.mod.widget.Widget;
	
	var AttachmentListWidget = function(container, files){
		AttachmentListWidget.superclass.constructor.call(this, container, {
			'buildTemplate': buildTemplate, 'tnames': 'list,viewrow' 
		}, files);
	};
	YAHOO.extend(AttachmentListWidget, BW, {
		onLoad: function(files){
			this.setFiles(files);
		},
		setFiles: function(files){
			if (L.isString(files)){
				files = files.split(',');
			}
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
	});
	NS.AttachmentListWidget = AttachmentListWidget;
	
	var AttachmentWidget = function(container, files, cfg){
		cfg = L.merge({
			'hideFMButton': false,
			'clickFileUploadCallback': null
		}, cfg || {});
		files = files || [];
		AttachmentWidget.superclass.constructor.call(this, container, {
			'buildTemplate': buildTemplate, 'tnames': 'files,ftable,frow' 
		}, files, cfg);
	};
	YAHOO.extend(AttachmentWidget, BW, {
		init: function(files, cfg){
			this.files = files;
			this.cfg = cfg;
		},
		onLoad: function(files, cfg){
			if (!L.isFunction(cfg['clickFileUploadCallback'])){
				this.fileUploader = new NS.FileUploader(Brick.env.user.id, function(fileid, filename){
					__self.onFileUpload(fileid, filename);
				});
			}
			
			this.showButtons(false);
		},
		onClick: function(el, tp){
			var TId = this._TId;
			switch(el.id){
			case tp['bshowbtnsex']:
			case tp['bshowbtns']: this.showButtons(true); return true;
			case tp['bcancel']: this.showButtons(); return true;
			case tp['bshowfm']: this.showFileBrowser(); return true;
			case tp['bupload']: this.showFileUpload(); return true;
			}
			
			var arr = el.id.split('-');
			if (arr.length == 2 && arr[0] == TId['frow']['remove']){
				this.removeFile(arr[1]);
				return true;
			}
			return false;
		},
		showButtons: function(en){
			this.elHide('bshowbtns,bshowbtnsex,bshowbtns', false);
			
			if (this.files.length > 0){
				this.elSetVisible('bshowbtnsex', !en);
			}else{
				this.elSetVisible('bshowbtns', !en);
			}
			this.elSetVisible('fm', en);
			
			if (this.cfg['hideFMButton']){
				this.elHide('bshowfm');
			}
		},
		showFileUpload: function(){
			if (L.isFunction(this.cfg['clickFileUploadCallback'])){
				this.cfg['clickFileUploadCallback']();
			}else{
				this.fileUploader.fileUpload();
			}
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
			this.elSetHTML({
				'table': fs.length > 0 ? TM.replace('ftable', {
					'rows': lst 
				}) : ""
			});
		},
		onFileUpload: function(fileid, filename){
			this.appendFile({
				'id': fileid,
				'nm': filename
			});
		}	
	});
	
	// TODO: Нет роли на загрузку, но есть список - показать только список
	
	NS.AttachmentWidget = AttachmentWidget;

};
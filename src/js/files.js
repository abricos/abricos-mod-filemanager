var Component = new Brick.Component();
Component.requires = {
    yahoo: ['animation', 'container', 'dragdrop', 'treeview', 'imagecropper'],
    mod: [
        {name: 'sys', files: ['panel.js', 'old-form.js', 'data.js', 'container.js']},
        {name: '{C#MODNAME}', files: ['api.js', 'lib.js']}
    ]
};
Component.entryPoint = function(NS){

    var Y = Brick.YUI,
        COMPONENT = this,
        SYS = Brick.mod.sys;

    var Dom = YAHOO.util.Dom;

    if (!NS.data){
        NS.data = new Brick.util.data.byid.DataSet('filemanager');
    }
    var DATA = NS.data;

    var FilesPanel = function(owner, container, folderid, onSelect){
        folderid = folderid || '0';
        this.onSelect = onSelect;
        this.init(owner, container, folderid);
    };
    FilesPanel.prototype = {
        init: function(owner, container, folderid){
            this.owner = owner;
            this.folderid = -1;
            this.selectedItem = null;
            this.isinit = true;

            COMPONENT.buildTemplate(this, 'filesrow,filesrowparent,filesrowfd,imagefile,files');

            this.tables = {
                'files': DATA.get('files', true),
                'folders': DATA.get('folders', true)
            };
            DATA.onComplete.subscribe(this.onDSComplete, this, true);
            this.setFolderId(folderid);
        },
        onDSComplete: function(type, args){
            if (args[0].check(['files', 'folders'])){
                this.renderElements();
            }
        },
        destroy: function(){
            DATA.onComplete.unsubscribe(this.onDSComplete);
        },
        setFolderId: function(folderid){
            if (this.folderid == folderid){
                return;
            }
            this.folderid = folderid;
            this.rows = DATA.get('files').getRows({'folderid': folderid});
            if (DATA.isFill(this.tables)){
                this.renderElements();
            }
            if (this.isinit){
                this.isinit = false;
            } else {
                DATA.request();
            }
        },
        refresh: function(){
            this.rows.clear();
            DATA.get('files').applyChanges();
            DATA.request();
        },
        onClick: function(el){
            var TId = this._TId,
                prefix = el.id.replace(/([0-9]+$)/, ''),
                numid = el.id.replace(prefix, "");

            switch (prefix) {
                case TId['filesrow']['edit'] + '-':
                    this.itemEdit(numid, false);
                    break;
                case TId['filesrow']['remove'] + '-':
                    this.itemRemove(numid, false);
                    break;
                case TId['filesrowfd']['edit'] + '-':
                    this.itemEdit(numid, true);
                    break;
                case TId['filesrowfd']['remove'] + '-':
                    this.itemRemove(numid, true);
                    break;
            }
            var fel = el;

            var _checkId = function(curel, id){
                var lprefix, lnumid;
                for (var i = 0; i < 3; i++){
                    lprefix = curel.id.replace(/([0-9]+$)/, '');
                    lnumid = curel.id.replace(lprefix, "");
                    if (lprefix == id){
                        numid = lnumid;
                        return true;
                    }
                    curel = curel.parentNode;
                }
                return false;
            };

            if (_checkId(el, TId['filesrowparent']['id'] + '-')){
                this.selectItem(numid, true, true);
                return true;
            }
            if (_checkId(el, TId['filesrowfd']['id'] + '-')){
                this.selectItem(numid, true);
                return true;
            }
            if (_checkId(el, TId['filesrow']['id'] + '-')){
                this.selectItem(numid, false);
                return true;
            }
            return false;
        },
        selectItem: function(itemid, isFolder, isParent){
            isParent = isParent || false;
            this.selectedItem = null;
            var item = null;
            var TId = this._TId;

            var pparentFolderId = '0';
            var row;
            if (this.folderid * 1 > 0){
                row = DATA.get('folders').getRows().getById(this.folderid);
                pparentFolderId = row.cell['pid'];
            }
            if (isParent){
                item = new NS.Folder({'id': pparentFolderId, 'pid': (row ? row.cell['pid'] : '-1')});
            }
            var parentfolderid = isFolder && isParent ? itemid : 'none';
            var el = Dom.get(TId['filesrowparent']['id'] + '-' + pparentFolderId);
            if (el){
                el.className = parentfolderid == itemid ? 'selected' : '';
            }

            var fileid = isFolder ? 'none' : itemid;
            this.rows.foreach(function(row){
                var id = row.cell['id'];
                var el = Dom.get(TId['filesrow']['id'] + '-' + id);
                if (id == fileid){
                    item = new NS.File(row.cell);
                }
                el.className = id == fileid ? 'selected' : '';
            });

            var folderid = isFolder ? itemid : 'none';
            var parentFolder = this.folderid;
            DATA.get('folders').getRows().foreach(function(row){
                if (row.cell['pid'] != parentFolder){
                    return;
                }
                var id = row.cell['id'];
                var el = Dom.get(TId['filesrowfd']['id'] + '-' + id);
                if (id == folderid){
                    item = new NS.Folder(row.cell);
                }
                el.className = id == folderid ? 'selected' : '';
            });
            this.selectedItem = item;
            if (this.onSelect){
                this.onSelect(item);
            }
        },
        itemEdit: function(itemid, isFolder){
            if (!isFolder){
                var row = this.rows.getById(itemid);
                API.showImageEditorPanel(row.cell);
            } else {
                var row = DATA.get('folders').getRows().getById(itemid);
                var rows = this.rows;
                new FolderEditPanel(row, function(name){
                    var table = DATA.get('folders');
                    row.update({'ph': name});
                    table.applyChanges();
                    DATA.request();
                });
            }
        },
        itemRemove: function(itemid, isFolder){
            if (!isFolder){
                var row = this.rows.getById(itemid);
                new FileRemoveMsg(row, function(){
                    row.remove();
                    DATA.get('files').applyChanges();
                    DATA.request();
                });
            } else {
                var row = DATA.get('folders').getRows().getById(itemid);
                new FolderRemoveMsg(row, function(){
                    var table = DATA.get('folders');
                    row.remove();
                    table.applyChanges();
                    DATA.request();
                });
            }
        },
        renderElements: function(){
            var lstFolders = "",
                rowsFD = DATA.get('folders').getRows(),
                folderid = this.folderid,
                TM = this._TM;

            if (folderid > 0){
                var row = rowsFD.getById(folderid);
                lstFolders += TM.replace('filesrowparent', {
                    'id': row.cell['pid']
                });
            }
            rowsFD.foreach(function(row){
                var di = row.cell;
                if (folderid != di['pid']){
                    return;
                }
                lstFolders += TM.replace('filesrowfd', {
                    'id': di['id'], 'fn': di['ph']
                });
            });

            var lstFiles = "";
            this.rows.foreach(function(row){
                var di = row.cell;
                var file = new NS.File(di);
                var img = TM.replace('imagefile');

                if (!Y.Lang.isNull(file.image)){
                    var linker = new NS.Linker(file);
                    linker.setSize(16, 16);
                    img = linker.getHTML();
                }

                lstFiles += TM.replace('filesrow', {
                    'id': di['id'],
                    'img': img,
                    'fn': di['fn'],
                    'fs': Brick.byteToString(di['fs']),
                    'dl': Brick.dateExt.convert(di['d'], 1)
                });
            });

            var el = this.owner._TM.gel('files');
            el.innerHTML = TM.replace('files', {
                files: lstFiles,
                folders: lstFolders
            });
            this.selectItem(null);
        }
    };

    NS.FilesPanel = FilesPanel;

    var FileRemoveMsg = function(row, callback){
        this.row = row;
        this.callback = callback;
        FileRemoveMsg.superclass.constructor.call(this);
    };
    YAHOO.extend(FileRemoveMsg, Brick.widget.Dialog, {
        initTemplate: function(){
            var t = T['fileremovemsg'];
            return tSetVar(t, 'info', this.row.cell['fn']);
        },
        onClick: function(el){
            var tp = TId['fileremovemsg'];
            switch (el.id) {
                case tp['bremove']:
                    this.close();
                    this.callback();
                    return true;
                case tp['bcancel']:
                    this.close();
                    return true;
            }
            return false;
        }
    });

};


var Component = new Brick.Component();
Component.requires = {
    yahoo: ['animation', 'container', 'dragdrop', 'treeview', 'imagecropper'],
    mod: [
        {name: 'sys', files: ['panel.js', 'data.js', 'container.js']},
        {name: '{C#MODNAME}', files: ['api.js', 'lib.js']}
    ]
};
Component.entryPoint = function(NS){

    var Y = Brick.YUI,
        COMPONENT = this,
        SYS = Brick.mod.sys;

    if (!NS.data){
        NS.data = new Brick.util.data.byid.DataSet('filemanager');
    }
    var DATA = NS.data;

    Brick.byteToString = function(byte){
        var ret = byte;
        var px = "";
        if (byte < 1024){
            ret = byte;
            px = "б";
        } else if (byte < 1024 * 1024){
            ret = Math.round((byte / 1024) * 100) / 100;
            px = 'кб';
        } else {
            ret = Math.round((byte / 1024 / 1024) * 100) / 100;
            px = 'мб';
        }
        return ret + ' ' + px;
    };

    NS.FileListWidget = Y.Base.create('fileListWidget', SYS.AppWidget, [], {
        onInitAppWidget: function(err, appInstance, options){
            this.tables = {
                files: DATA.get('files', true),
                folders: DATA.get('folders', true)
            };
            DATA.onComplete.subscribe(this.onDSComplete, this, true);

            this.after('parentFolderIdChange', function(e){
                this.rows = DATA.get('files').getRows({
                    folderid: e.newVal
                });

                if (DATA.isFill(this.tables)){
                    this.renderElements();
                } else {
                    DATA.request();
                }
            }, this);

            this.set('parentFolderId', 0);
        },
        destructor: function(){
            DATA.onComplete.unsubscribe(this.onDSComplete);
        },
        onDSComplete: function(type, args){
            if (args[0].check(['files', 'folders'])){
                this.renderElements();
            }
        },
        refresh: function(){
            this.rows.clear();
            DATA.get('files').applyChanges();
            DATA.request();
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
            var tp = this.template,
                lstFolders = "",
                lstFiles = "",
                rowsFD = DATA.get('folders').getRows(),
                folderid = this.get('parentFolderId');

            if (folderid > 0){
                var row = rowsFD.getById(folderid);
                lstFolders += tp.replace('filesrowparent', {
                    id: row.cell['pid']
                });
            }

            rowsFD.foreach(function(row){
                var di = row.cell;
                if (folderid !== (di.pid | 0)){
                    return;
                }
                lstFolders += tp.replace('filesrowfd', {
                    id: di.id, fn: di.ph
                });
            });

            this.rows.foreach(function(row){
                var di = row.cell,
                    file = new NS.File(di),
                    img = tp.replace('imagefile');

                if (!Y.Lang.isNull(file.image)){
                    var linker = new NS.Linker(file);
                    linker.setSize(16, 16);
                    img = linker.getHTML();
                }

                lstFiles += tp.replace('filesrow', {
                    id: di.id,
                    'img': img,
                    'fn': di['fn'],
                    'fs': Brick.byteToString(di['fs']),
                    'dl': Brick.dateExt.convert(di['d'], 1)
                });
            });

            tp.setHTML({
                list: tp.replace('files', {
                    files: lstFiles,
                    folders: lstFolders
                })
            });

            this.set('selected', 0);
        },
        _selectItemByClick: function(type, itemid){
            var tp = this.template,
                row,
                item = null;

            if (type === 'folder'){
                row = DATA.get('folders').getRows().getById(itemid);
                if (row){
                    item = new NS.Folder(row.cell);
                }
            } else if (type === 'file'){
                row = this.rows.getById(itemid);
                if (row){
                    item = new NS.File(row.cell);
                }
            }

            tp.one('list').all('tr').each(function(node){
                if (item && item.type === node.getData('itemtype')
                    && itemid == node.getData('id')){
                    node.addClass('selected');
                } else {
                    node.removeClass('selected');
                }
            }, this);

            this.set('selected', item);
        },
        onClick: function(e){
            var target = e.defineTarget || e.target,
                itemid = target.getData('id') | 0;

            switch (e.dataClick) {
                case 'itemSelect':
                    this._selectItemByClick(target.getData('itemtype'), itemid);
            }

            return;

            /*
            // TODO: old code to remove
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
            /**/
        },
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'widget,files,filesrow,filesrowparent,filesrowfd,imagefile'},
            selected: {
                value: null
            },
            parentFolderId: {
                setter: function(val){
                    return val | 0;
                }
            }
        },
    });


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


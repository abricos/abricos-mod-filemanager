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

    if (!NS.data){
        NS.data = new Brick.util.data.byid.DataSet('filemanager');
    }
    var DATA = NS.data;

    var ROOT_FOLDER = new NS.Folder({
        'id': '0', 'pid': '-1', "fn": "root",
        'ph': Brick.util.Language.getc('mod.filemanager.myfiles')
    });


    var FolderNode = function(oData, oParent, expanded){
        FolderNode.superclass.constructor.call(this, oData, oParent, expanded);
    };
    YAHOO.extend(FolderNode, YAHOO.widget.TextNode, {});

    NS.FolderListWidget = Y.Base.create('folderListWidget', SYS.AppWidget, [], {
        onInitAppWidget: function(err, appInstance, options){
            this.publish('selectedItem');

            this.index = [];

            var tp = this.template;

            this.tv = new YAHOO.widget.TreeView(tp.gel('tree'));
            this.tv.subscribe("clickEvent", function(event){
                this.set('selected', event.node.data.folder.id);
                return true;
            }, this, true);

            var tables = {'folders': DATA.get('folders', true)};
            this.rows = tables['folders'].getRows();
            DATA.onComplete.subscribe(this.onDSComplete, this, true);
            if (DATA.isFill(tables)){
                this.renderElements();
            }

            this.after('selectedChange', this._selectedChange, this);
        },
        destructor: function(){
            DATA.onComplete.unsubscribe(this.onDSComplete);
        },
        _selectedChange: function(e){
            var folderid = e.newVal;

            var data;
            if (folderid === 0){
                data = {id: 0, pid: -1};
            } else {
                data = DATA.get('folders').getRows().getById(folderid).cell;
            }
            this._selectedFolder = new NS.Folder(data);
            var index = this.index[data.id],
                node = this.tv.getNodeByIndex(index);

            if (node){
                node.expand();
                node.focus();
            }
        },
        onDSComplete: function(type, args){
            if (args[0].check(['folders'])){
                this.renderElements();
            }
        },
        renderElements: function(){
            this.tv.removeChildren(this.tv.getRoot());
            var rootNode = this.tv.getRoot();
            this.index['0'] = rootNode.index;
            this.renderNode(rootNode, ROOT_FOLDER);
            this.tv.render();
        },
        renderNode: function(parentNode, folder){
            var nodeval = {'label': folder.phrase, 'folder': folder};
            if (folder.id == 0){
                nodeval.expanded = true;
            } else {
                nodeval.editable = true;
            }
            var node = new FolderNode(nodeval, parentNode);
            this.index[folder.id] = node.index;

            var instance = this;
            this.rows.foreach(function(row){
                var di = row.cell;
                if (di['pid'] == folder['id']){
                    instance.renderNode(node, new NS.Folder(di));
                }
            });
        },
        createFolder: function(){
            this._currentEditorType = 'create';

            var tp = this.template;
            tp.setHTML({
                editor: tp.replace('create')
            });

            tp.toggleView(true, 'editor', 'tree');
        },
        editFolder: function(folderid){
            var row = DATA.get('folders').getRows().getById(folderid);
            return;
            if (!row){
                return;
            }

            this._currentEditorType = 'edit';

            var tp = this.template;
            tp.setHTML({
                editor: tp.replace('create')
            });

            tp.toggleView(true, 'editor', 'tree');
        },
        _buildFullPath: function(folderid){
            var row = this.rows.getById(folderid);
            if (Y.Lang.isNull(row)){
                return '//' + ROOT_FOLDER.phrase;
            }
            return this._buildFullPath(row.cell['pid']) + "/" + row.cell['ph'];
        },
        closeEditor: function(){
            this.template.toggleView(true, 'tree', 'editor');
            this._currentEditorType = '';
        },
        actionEditor: function(){
            var tp = this.template,
                table = DATA.get('folders'),
                rows = table.getRows(),
                row = table.newRow();

            if (this._currentEditorType === 'create'){
                var name = tp.getValue('create.name');
                if (name === ''){
                    return;
                }
                row.update({
                    pid: this.get('selected'),
                    ph: tp.getValue('create.name')
                });
                rows.add(row);
                table.applyChanges();
                DATA.request();
            } else if (this._currentEditorType === 'edit'){
                var name = tp.getValue('edit.name');
                if (name === ''){
                    return;
                }
                row.update({'ph': name});
                table.applyChanges();
                DATA.request();
            }
            this.closeEditor();
        }
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'widget,create,edit,remove'},
            selected: {
                value: 0,
                setter: function(val){
                    return val | 0;
                }
            },
            selectedFolder: {
                readOnly: true,
                getter: function(){
                    return this._selectedFolder;
                }
            },
            selectedFullPath: {
                getter: function(){
                    var selected = this.get('selected');
                    return this._buildFullPath(selected);
                }
            }
        },
        CLICKS: {
            closeEditor: 'closeEditor',
            actionEditor: 'actionEditor'
        }
    });

    var FolderRemoveMsg = function(row, callback){
        this.row = row;
        this.callback = callback;
        FolderRemoveMsg.superclass.constructor.call(this);
    };
    YAHOO.extend(FolderRemoveMsg, Brick.widget.Dialog, {
        initTemplate: function(){
            return buildTemlate(this, 'folderremovemsg').replace('folderremovemsg', {
                info: this.row.cell['ph']
            });
        },
        onClick: function(el){
            var tp = this._TId['folderremovemsg'];
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

    NS.FolderRemoveMsg = FolderRemoveMsg;

};


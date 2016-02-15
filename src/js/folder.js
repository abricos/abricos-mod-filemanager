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

    var buildTemplate = this.buildTemplate;

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

    var FolderPanel = function(owner, onSelectItem){
        this.init(owner, onSelectItem);
    };
    FolderPanel.prototype = {
        init: function(owner, onSelectItem){
            this.owner = owner;
            this.onSelectItem = onSelectItem;
            this.index = {};

            var instance = this;
            this.tv = new YAHOO.widget.TreeView(owner.template.gel('folders'));
            this.tv.subscribe("clickEvent", function(event){
                instance.setFolderId(event.node.data.folder.id);
                return true;
            });

            this.selectedFolderId = "0";
            var tables = {'folders': DATA.get('folders', true)};
            this.rows = tables['folders'].getRows();
            DATA.onComplete.subscribe(this.onDSComplete, this, true);
            if (DATA.isFill(tables)){
                this.renderElements();
            }
        },
        onDSComplete: function(type, args){
            if (args[0].check(['folders'])){
                this.renderElements();
            }
        },
        destroy: function(){
            DATA.onComplete.unsubscribe(this.onDSComplete);
        },
        onClick: function(el){
            return false;
        },
        renderElements: function(){
            this.tv.removeChildren(this.tv.getRoot());
            var rootNode = this.tv.getRoot();
            this.index['0'] = rootNode.index;
            this.renderNode(rootNode, ROOT_FOLDER);
            this.tv.render();
        },
        renderNode: function(parentNode, folder){
            var i, nodeval = {'label': folder.phrase, 'folder': folder};
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
            var selectedFolderId = this.selectedFolderId;
            new CreateFolderPanel(function(name){
                var table = DATA.get('folders');
                var rows = table.getRows();
                var row = table.newRow();
                row.update({'pid': selectedFolderId, 'ph': name});
                rows.add(row);
                table.applyChanges();
                DATA.request();
            });
        },
        setFolderId: function(folderid){
            if (this.selectedFolderId == folderid){
                return;
            }
            this.selectedFolderId = folderid;
            var data;
            if (folderid == '0'){
                data = {'id': '0', 'pid': '-1'};
            } else {
                data = DATA.get('folders').getRows().getById(folderid).cell;
            }
            var index = this.index[data['id']];
            var node = this.tv.getNodeByIndex(index);
            if (!Y.Lang.isNull(node)){
                node.expand();
                node.focus();
            }
            this.onSelectItem(new NS.Folder(data));
        },
        fullPath: function(folderid){
            var row = this.rows.getById(folderid);
            if (Y.Lang.isNull(row)){
                return '//' + ROOT_FOLDER.phrase;
            }
            return this.fullPath(row.cell['pid']) + "/" + row.cell['ph'];
        }
    };

    NS.FolderPanel = FolderPanel;

    var CreateFolderPanel = function(callback){
        this.callback = callback;
        CreateFolderPanel.superclass.constructor.call(this);
    };
    YAHOO.extend(CreateFolderPanel, Brick.widget.Dialog, {
        el: function(name){
            return this._TM.gel(name);
        },
        elv: function(name){
            return Brick.util.Form.getValue(this.el(name));
        },
        initTemplate: function(){
            return buildTemplate(this, 'createfolderpanel').replace('createfolderpanel');
        },
        onClick: function(el){
            var tp = this._TId['createfolderpanel'];
            switch (el.id) {
                case tp['bcreate']:
                    this.callback(this.elv('name'));
                    this.close();
                    return true;
                case tp['bcancel']:
                    this.close();
                    return true;
            }
            return false;
        }
    });

    NS.CreateFolderPanel = CreateFolderPanel;

    var FolderEditPanel = function(row, callback){
        this.row = row;
        this.callback = callback;
        FolderEditPanel.superclass.constructor.call(this);
    };
    YAHOO.extend(FolderEditPanel, Brick.widget.Dialog, {
        el: function(name){
            return this._TM.gel(name);
        },
        elv: function(name){
            return Brick.util.Form.getValue(this.el(name));
        },
        setelv: function(name, value){
            Brick.util.Form.setValue(this.el(name), value);
        },
        initTemplate: function(){
            return buildTemplate(this, 'editfolderpanel').replace('editfolderpanel');
        },
        onLoad: function(){
            this.setelv('name', this.row.cell['ph']);
        },
        onClick: function(el){
            var tp = this._TId['editfolderpanel'];
            switch (el.id) {
                case tp['bsave']:
                    this.callback(this.elv('name'));
                    this.close();
                    return true;
                case tp['bcancel']:
                    this.close();
                    return true;
            }
            return false;
        }
    });

    NS.FolderEditPanel = FolderEditPanel;

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


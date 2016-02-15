var Component = new Brick.Component();
Component.requires = {
    yahoo: ['animation', 'container', 'dragdrop', 'treeview', 'imagecropper'],
    mod: [
        {name: 'sys', files: ['panel.js', 'old-form.js', 'data.js', 'container.js']},
        {name: '{C#MODNAME}', files: ['folder.js', 'files.js', 'screenshot.js', 'api.js', 'lib.js']}
    ]
};
Component.entryPoint = function(NS){

    var Y = Brick.YUI,
        COMPONENT = this,
        SYS = Brick.mod.sys;

    if (!NS.data){
        NS.data = new Brick.util.data.byid.DataSet('filemanager');
    }

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

    NS.BrowserPanel = Y.Base.create('browserPanel', SYS.Dialog, [], {
        initializer: function(){
            this.publish('selected');
            this.publish('canceled');

            Y.after(this._syncUIDialog, this, 'syncUI');
        },
        _syncUIDialog: function(){
            var instance = this;
            this.browserWidget = new NS.BrowserWidget({
                srcNode: this.template.gel('widget'),
                onRenderWidgets: function(){
                    instance.centered();
                }
            });

            this.browserWidget.on('selected', function(){
                this.hide();
                this.fire('selected');
            }, this);

            this.browserWidget.on('canceled', function(){
                this.hide();
                this.fire('canceled');
            }, this);
        },
        destructor: function(){
            if (this.browserWidget){
                this.browserWidget.destroy();
            }
        }
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'panel'},
            width: {value: 800}
        },
    });

    NS.BrowserWidget = Y.Base.create('browserWidget', SYS.AppWidget, [], {
        onInitAppWidget: function(err, appInstance, options){
            this.publish('selected');
            this.publish('canceled');

            var tp = this.template,
                instance = this;

            this.screenshot = new NS.Screenshot(this, tp.gel('screenshot'));
            this.folders = new NS.FolderPanel(this, function(item){
                instance.onSelectItem_foldersPanel(item);
            });
            this.files = new NS.FilesPanel(this, tp.gel('files'), '0', function(item){
                instance.onSelectItem_filesPanel(item);
            });

            this.fileUploader = new NS.FileUploader(Brick.env.user.id, function(fileid, filename){
                instance.onFileUpload(fileid, filename);
            });

            var onRenderWidgets = this.get('onRenderWidgets');
            if (Y.Lang.isFunction(onRenderWidgets)){
                onRenderWidgets.call(this);
            }
        },
        destructor: function(){
            this.files.destroy();
            this.folders.destroy();
        },
        onSelectItem_foldersPanel: function(item){
            this.files.setFolderId(item.id);
            this.refreshPath();
        },
        refreshPath: function(){
            this.template.setValue('path', this.folders.fullPath(this.folders.selectedFolderId));
        },
        onSelectItem_filesPanel: function(item){
            var fname = '', fsrc = '', fpath = '';
            if (!Y.Lang.isNull(item)){
                if (item.type == 'file'){
                    fname = item.name;
                    var lnk = new NS.Linker(item);
                    fsrc = lnk.getHTML();
                    fpath = lnk.getSrc();
                } else {
                    fname = item.phrase;
                }
            }
            this.setelv('filesrchtml', fsrc);
            this.setelv('filesrc', fpath);
            this.el('bselect').disabled = Y.Lang.isNull(item) ? "disabled" : "";
            this.screenshot.setImage(item);
            this.refreshPath();
        },
        showUpload: function(){
            this.fileUploader.fileUpload({
                'folderid': this.folders.selectedFolderId
            });
        },
        onFileUpload: function(fileid, filename){
            this.files.refresh();
        },
        select: function(){
            if (Y.Lang.isNull(this.files.selectedItem)){
                return;
            }
            var item = this.files.selectedItem;
            if (item.type == 'folder'){
                this.files.setFolderId(item.id);
                this.folders.setFolderId(item.id);
            } else {
                if (this.callback){
                    var linker = new NS.Linker(item);
                    this.callback({
                        'html': this.elv('filesrchtml'),
                        'file': item,
                        'src': linker.getSrc()
                    });
                    this.close();
                }
            }
        },
        cancel: function(){
            this.fire('canceled');
        },
        onClick: function(e){
            switch (e.dataClick) {
                case 'bnewfolder':
                    this.folders.createFolder();
                    return true;
                case 'bupload':
                    this.showUpload();
                    return true;
                case 'bcancel':
                    this.cancel();
                    return true;
                case 'bselect':
                    this.select();
                    return true;
            }
            if (this.screenshot.onClick(el)){
                return true;
            }
            if (this.files.onClick(el)){
                return true;
            }
            return false;
        },
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'widget'},
            onRenderWidgets: {value: null}
        },
    });

};


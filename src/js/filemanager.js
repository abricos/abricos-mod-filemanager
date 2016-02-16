var Component = new Brick.Component();
Component.requires = {
    mod: [
        {name: 'sys', files: ['panel.js', 'old-form.js', 'data.js', 'container.js']},
        {name: '{C#MODNAME}', files: ['folders.js', 'files.js', 'screenshot.js', 'api.js', 'lib.js']}
    ]
};
Component.entryPoint = function(NS){

    var Y = Brick.YUI,
        COMPONENT = this,
        SYS = Brick.mod.sys;

    if (!NS.data){
        NS.data = new Brick.util.data.byid.DataSet('filemanager');
    }

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

            this.browserWidget.on('selected', function(e){
                this.fire('selected', e);
                this.hide();
            }, this);

            this.browserWidget.on('canceled', function(){
                this.fire('canceled');
                this.hide();
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

            this.screenshot = new NS.ScreenshotWidget({
                srcNode: tp.gel('screenshot')
            });
            this.screenshot.on('selected', function(e){
                this.fire('selected', e);
            }, this);

            this.folders = new NS.FolderListWidget({
                srcNode: tp.gel('folders')
            });
            this.folders.after('selectedChange', this._foldersSelectedChange, this);

            var onRenderWidgets = this.get('onRenderWidgets');
            if (Y.Lang.isFunction(onRenderWidgets)){
                onRenderWidgets.call(this);
            }
            this.files = new NS.FileListWidget({
                srcNode: tp.gel('files')
            });
            this.files.after('selectedChange', this._filesSelectedChange, this);

            this.fileUploader = new NS.FileUploader(Brick.env.user.id, function(fileid, filename){
                instance.onFileUpload(fileid, filename);
            });

            this.refreshPath();

            NS.data.request(true);
        },
        destructor: function(){
            if (this.screenshot){
                this.screenshot.destroy();
                this.files.destroy();
                this.folders.destroy();
            }
        },
        refreshPath: function(){
            this.template.setValue('path', this.folders.get('selectedFullPath'));
        },
        _foldersSelectedChange: function(e){
            this.refreshPath();
            this.files.set('parentFolderId', e.newVal);
        },
        _filesSelectedChange: function(e){
            var tp = this.template,
                item = e.newVal,
                fsrc = '', fpath = '',
                isFile = item && item.type === 'file';

            if (isFile){
                var lnk = new NS.Linker(item);
                fsrc = lnk.getHTML();
                fpath = lnk.getSrc();
            }

            tp.setValue({
                filesrchtml: fsrc,
                filesrc: fpath
            });

            tp.one('bselect').set('disabled', !item ? "disabled" : "");

            this.screenshot.setImage(item);
            this.refreshPath();
        },
        showUpload: function(){
            this.fileUploader.fileUpload({
                'folderid': this.folders.get('selected')
            });
        },
        onFileUpload: function(fileid, filename){
            this.files.refresh();
        },
        select: function(){
            var item = this.files.get('selected');

            if (!item){
                return;
            }

            if (item.type == 'folder'){
                this.folders.set('selected', item.id);
            } else {
                var linker = new NS.Linker(item);
                this.fire('selected', {
                    html: this.template.getValue('filesrchtml'),
                    file: item,
                    src: linker.getSrc()
                });
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
        },
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'widget'},
            onRenderWidgets: {value: null}
        },
    });

};


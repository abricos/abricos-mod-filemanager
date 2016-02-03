var Component = new Brick.Component();
Component.requires = {
    mod: [
        {name: 'sys', files: ['panel.js', 'widgets.js', 'data.js', 'old-form.js']},
        {name: '{C#MODNAME}', files: ['lib.js']}
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

    NS.ExtensionManagerWidget = Y.Base.create('extensionManagerWidget', SYS.AppWidget, [], {
        onInitAppWidget: function(err, appInstance, options){
            var tables = {
                'extensions': DATA.get('extensions', true)
            };
            DATA.onStart.subscribe(this.dsEvent, this, true);
            DATA.onComplete.subscribe(this.dsEvent, this, true);
            if (DATA.isFill(tables)){
                this.renderElements()
            } else {
                this.set('waiting', true);
                DATA.request();
            }
        },
        destructor: function(){
            DATA.onComplete.unsubscribe(this.dsEvent);
            DATA.onStart.unsubscribe(this.dsEvent);
        },
        dsEvent: function(type, args){
            if (args[0].checkWithParam('extensions', {})){
                type == 'onComplete' ? this.renderElements() : this.set('waiting', true);
            }
        },
        renderElements: function(){
            this.set('waiting', false);
            var tp = this.template,
                lst = "";

            DATA.get('extensions').getRows().foreach(function(row){
                var di = row.cell;
                lst += tp.replace('extrow', {
                    'id': di['filetypeid'],
                    'ext': di['extension'],
                    'mime': di['mimetype'],
                    'size': di['maxsize'],
                    'width': di['maxwidth'],
                    'height': di['maxheight']
                });
            });
            tp.setHTML({
                table: tp.replace('exttable', {'rows': lst})
            });
        },
        onClick: function(e){
            var extid = e.target.getData('id') | 0;

            switch (e.dataClick) {
                case 'create':
                    this.editExtension(0);
                    return true;
                case 'edit':
                    this.editExtension(extid);
                    return true;
            }
        },
        editExtension: function(id){
            var table = DATA.get('extensions'),
                rows = table.getRows(),
                row = id == 0 ? table.newRow() : rows.getById(id);

            new NS.ExtensionEditorPanel({
                row: row,
                callback: function(){
                    if (id == 0){
                        rows.add(row);
                    }
                    table.applyChanges();
                    DATA.request();
                }
            });
        }
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'extwidget,extrow,exttable'}
        }
    });

    NS.ExtensionEditorPanel = Y.Base.create('extensionEditorPanel', SYS.Dialog, [], {
        initializer: function(){
            Y.after(this._syncUIDialog, this, 'syncUI');
        },
        _syncUIDialog: function(){
            var tp = this.template,
                di = this.get('row').cell;

            tp.setValue({
                'ext': di['extension'],
                'mime': di['mimetype'],
                'size': di['maxsize'],
                'width': di['maxwidth'],
                'height': di['maxheight']
            });
        },
        save: function(){
            var tp = this.template;

            this.get('row').update({
                extension: tp.getValue('ext'),
                mimetype: tp.getValue('mime'),
                maxsize: tp.getValue('size'),
                maxwidth: tp.getValue('width'),
                maxheight: tp.getValue('height')
            });

            this.get('callback').call(this);
            this.hide();
        }
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'exteditorpanel'},
            row: {value: null},
            callback: {value: null},
            width: {value: 600}
        },
        CLICKS: {
            save: 'save',
            cancel: 'hide'
        }
    });

};
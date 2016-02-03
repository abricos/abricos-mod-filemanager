var Component = new Brick.Component();
Component.requires = {
    yahoo: ['tabview', 'dragdrop'],
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

    NS.LimitManagerWidget = Y.Base.create('limitManagerWidget', SYS.AppWidget, [], {
        onInitAppWidget: function(err, appInstance, options){
            var tp = this.template;

            var tables = {
                'usergrouplimit': DATA.get('usergrouplimit', true)
            };
            DATA.onStart.subscribe(this.dsEvent, this, true);
            DATA.onComplete.subscribe(this.dsEvent, this, true);
            if (DATA.isFill(tables)){
                this.renderElements();
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
            if (args[0].checkWithParam('usergrouplimit', {})){
                if (type == 'onComplete'){
                    this.renderElements();
                } else {
                    this.set('waiting', true);
                }
            }
        },
        renderElements: function(){
            this.set('waiting', false);
            var tp = this.template,
                lst = "";

            DATA.get('usergrouplimit').getRows().foreach(function(row){
                var di = row.cell;
                lst += tp.replace('limitrow', {
                    'id': di['id'],
                    'lmt': di['lmt'],
                    'gnm': di['gnm']
                });
            });
            tp.setHTML({
                table: tp.replace('limittable', {'rows': lst})
            });
        },
        editGroupLimit: function(id){
            var table = DATA.get('usergrouplimit'),
                rows = table.getRows(),
                row = id == 0 ? table.newRow() : rows.getById(id);

            new NS.GroupLimitEditorPanel({
                row: row,
                callback: function(){
                    if (id == 0){
                        rows.add(row);
                    }
                    table.applyChanges();
                    DATA.request();
                }
            });
        },
        removeGroupLimit: function(id){
            var table = DATA.get('usergrouplimit'),
                rows = table.getRows(),
                row = id == 0 ? table.newRow() : rows.getById(id);

            row.remove();
            table.applyChanges();
            DATA.request();
        },
        onClick: function(e){
            var groupid = e.target.getData('id') | 0;
            switch (e.dataClick) {
                case 'create':
                    this.editGroupLimit(0);
                    return true;
                case 'edit':
                    this.editGroupLimit(groupid);
                    return true;
                case 'remove':
                    this.removeGroupLimit(groupid);
                    return true;
            }
        }
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'limitwidget,limitrow,limittable'}
        }
    });

    NS.GroupLimitEditorPanel = Y.Base.create('groupLimitEditorPanel', SYS.Dialog, [], {
        buildTData: function(){
            return {};
        },
        initializer: function(){
            Y.after(this._syncUIDialog, this, 'syncUI');
        },
        _syncUIDialog: function(){
            DATA.onStart.subscribe(this.dsEvent, this, true);
            DATA.onComplete.subscribe(this.dsEvent, this, true);
            var isFill = DATA.isFill({
                grouplist: DATA.get('grouplist', true)
            });

            if (isFill){
                this.renderTable();
            } else {
                this.set('waiting', true);
            }

            var tp = this.template,
                di = this.get('row').cell;

            tp.setValue({
                size: di['lmt']
            });

            DATA.request();
        },
        destructor: function(){
            DATA.onComplete.unsubscribe(this.dsEvent);
            DATA.onStart.unsubscribe(this.dsEvent);
        },
        dsEvent: function(type, args){
            if (args[0].checkWithParam('grouplist')){
                type == 'onComplete' ? this.renderTable() : this.renderWait();
            }
        },
        renderTable: function(){
            var tp = this.template,
                lst = "";

            DATA.get('grouplist').getRows().foreach(function(row){
                var di = row.cell;
                lst += tp.replace('option', {
                    id: di['id'],
                    nm: di['gnm']
                });
            });

            tp.setHTML({
                table: tp.replace('select', {'rows': lst})
            });

            var tbl = tp.gel('select.id'),
                row = this.get('row');

            if (!row.isNew()){
                tbl.disabled = 'disabled';
                tbl.value = row.cell['gid'];
            }
        },
        save: function(){
            var tp = this.template;

            this.get('row').update({
                'lmt': tp.getValue('size'),
                'gid': tp.getValue('select.id')
            });
            this.get('callback').call(this);
            this.hide();
        }
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'limiteditorpanel,select,option'},
            row: {value: null},
            callback: {value: null},
            width: {value: 600}
        },
        CLICKS: {
            save: 'save',
            cancel: 'hide'
        }
    });


    NS.ExtensionManagerWidget = Y.Base.create('extensionManagerWidget', SYS.AppWidget, [], {
        onInitAppWidget: function(err, appInstance, options){
            var tp = this.template;
            this.managerWidget = new NS.ExtensionFileWidget(tp.gel('widget'))
        },
        destructor: function(){
            if (this.managerWidget){
                this.managerWidget.destroy();
            }
        }
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'limitConfigWidget'}
        }
    });

    return;


    var GroupLimitEditorPanel = function(row, callback){
        this.row = row;
        this.tname = row.isNew() ? 'limitappendpanel' : 'limitappendpanel';
        this.callback = callback;
        GroupLimitEditorPanel.superclass.constructor.call(this, {
            width: '400px', resize: true
        });
    };
    YAHOO.extend(GroupLimitEditorPanel, Brick.widget.Dialog, {

    });
    // NS.GroupLimitEditorPanel = GroupLimitEditorPanel;


    var ExtensionFileWidget = function(container){
        var TM = buildTemplate(this, 'extwidget,extrowwait,extrow,exttable');

        container = L.isString(container) ? Dom.get(container) : container;
        this.init(container);
    };
    ExtensionFileWidget.prototype = {
        init: function(container){
            container.innerHTML = this._TM.replace('extwidget');
            var tables = {
                'extensions': DATA.get('extensions', true)
            };
            DATA.onStart.subscribe(this.dsEvent, this, true);
            DATA.onComplete.subscribe(this.dsEvent, this, true);
            DATA.isFill(tables) ? this.renderElements() : this.renderWait();
        },
        dsEvent: function(type, args){
            if (args[0].checkWithParam('extensions', {})){
                type == 'onComplete' ? this.renderElements() : this.renderWait();
            }
        },
        destroy: function(){
            DATA.onComplete.unsubscribe(this.dsEvent);
            DATA.onStart.unsubscribe(this.dsEvent);
        },
        renderElements: function(){
            var TM = this._TM,
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
            this._TM.getEl('extwidget.table').innerHTML = this._TM.replace('exttable', {'rows': this._TM.replace('extrowwait')});
        },
        onClick: function(el){
            var TId = this._TId;

            if (el.id == TId['extwidget']['bappend']){
                this.editExtension(0);
                return true;
            }

            var prefix = el.id.replace(/([0-9]+$)/, '');
            var numid = el.id.replace(prefix, "");

            switch (prefix) {
                case (TId['extrow']['edit'] + '-'):
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
                if (id == 0){
                    rows.add(row);
                }
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
            width: '600px', resize: true
        });
    };

    YAHOO.extend(ExtensionEditorPanel, Brick.widget.Dialog, {
        el: function(name){
            return Dom.get(this._TId['exteditorpanel'][name]);
        },
        elv: function(name){
            return Brick.util.Form.getValue(this.el(name));
        },
        setelv: function(name, value){
            Brick.util.Form.setValue(this.el(name), value);
        },
        initTemplate: function(){
            return buildTemplate(this, 'exteditorpanel').replace('exteditorpanel');
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
            switch (el.id) {
                case tp['bcancel']:
                    this.close();
                    return true;
                case tp['bsave']:
                    this.save();
                    return true;
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
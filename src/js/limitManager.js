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

    NS.LimitManagerWidget = Y.Base.create('limitManagerWidget', SYS.AppWidget, [], {
        onInitAppWidget: function(err, appInstance, options){
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

};
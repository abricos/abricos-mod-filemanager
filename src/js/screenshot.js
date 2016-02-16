var Component = new Brick.Component();
Component.requires = {
    mod: [
        {name: 'sys', files: ['panel.js', 'old-form.js', 'container.js']},
        {name: '{C#MODNAME}', files: ['lib.js']}
    ]
};
Component.entryPoint = function(NS){

    var Y = Brick.YUI,
        COMPONENT = this,
        SYS = Brick.mod.sys;

    NS.ScreenshotWidget = Y.Base.create('screenshotWidget', SYS.AppWidget, [], {
        onInitAppWidget: function(err, appInstance, options){
            this.publish('selected');
            this.setImage(null);

            var instance = this;
            Brick.appFunc('user', 'userOptionList', '{C#MODNAME}', function(err, res){
                instance.userOptionList = res.userOptionList;
                instance.renderWidget();
            });
        },
        getData: function(){
            var tp = this.template;
            return {
                tpl: tp.getValue('code'),
                w: tp.getValue('width') | 0,
                h: tp.getValue('height') | 0
            };
        },
        renderWidget: function(){
            var uOptions = this.userOptionList;
            if (!uOptions){
                return;
            }
            var tp = this.template,
                tplVal = uOptions.getById('tpl-screenshot'),
                scsTemplate = uOptions.getById('scsTemplate'),
                scsWidth = uOptions.getById('scsWidth'),
                scsHeight = uOptions.getById('scsHeight');

            if (!scsTemplate){
                return;
            }

            if (tplVal && tplVal.get('value') && tplVal.get('value').length > 0){
                var val = Y.JSON.parse(tplVal.get('value'));

                scsTemplate.set('value', val['tpl']);
                scsWidth.set('value', val['w']);
                scsHeight.set('value', val['h']);
            }

            var tmp = scsTemplate.get('value');
            if (Y.Lang.isNull(tmp) || tmp.length === 0){
                tp.setValue({
                    code: tp.replace('default')
                });
                return;
            }

            tp.setHTML({
                code: scsTemplate.get('value'),
                width: scsWidth.get('value'),
                height: scsHeight.get('value')
            });
        },
        setImage: function(file){
            if (!file || file.type !== 'file' || !file.image){
                file = null;
            }
            this.file = file;

            var tp = this.template,
                elNames = ['bselect', 'title', 'width', 'height', 'code'];

            for (var i = 0; i < elNames.length; i++){
                tp.one(elNames[i]).set('disabled', !file ? 'disabled' : '');
            }
        },
        save: function(){
            var uOptions = this.userOptionList;
            if (!uOptions){
                return;
            }

            var d = this.getData(),
                scsTemplate = uOptions.getById('scsTemplate'),
                scsWidth = uOptions.getById('scsWidth'),
                scsHeight = uOptions.getById('scsHeight');

            scsTemplate.set('value', d['tpl']);
            scsWidth.set('value', d['w']);
            scsHeight.set('value', d['h']);

            Brick.appFunc('user', 'userOptionSave', '{C#MODNAME}', [scsTemplate, scsWidth, scsHeight]);
        },
        selectItem: function(){
            if (!this.file){
                return false;
            }

            var tp = this.template,
                item = this.file,
                linker = new NS.Linker(item),
                title = tp.getValue('title'),
                width = tp.getValue('width') | 0,
                height = tp.getValue('height') | 0;

            if (width === 0 && height === 0){
                return;
            }

            var smallLinker = new NS.Linker(item);
            smallLinker.setSize(width, height);

            var html = Abricos.TemplateManager.replace(tp.getValue('code'), {
                isrc: linker.getSrc(),
                sisrc: smallLinker.getSrc(),
                siw: width,
                sih: height,
                sialt: title,
                sitl: title
            });

            this.save();
            this.fire('selected', {
                'html': html,
                'file': item,
                'src': linker.getSrc()
            });
        },
        onClick: function(e){
            switch (e.dataClick) {
                case 'bselect':
                    this.selectItem();
                    return true;
            }
            return false;
        },
    }, {
        ATTRS: {
            component: {value: COMPONENT},
            templateBlockName: {value: 'widget,default'},
        },
    });

};


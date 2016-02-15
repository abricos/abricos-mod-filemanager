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

    var buildTemplate = this.buildTemplate;

    var Screenshot = function(owner, container){
        this.init(owner, container);
    };
    Screenshot.prototype = {
        el: function(name){
            return this._TM.gel(name);
        },
        elv: function(name){
            return Brick.util.Form.getValue(this.el(name));
        },
        setel: function(el, value){
            Brick.util.Form.setValue(el, value);
        },
        setelv: function(name, value){
            Brick.util.Form.setValue(this.el(name), value);
        },
        getData: function(){
            return {
                'tpl': this.elv('code'),
                'w': this.elv('width') * 1,
                'h': this.elv('height') * 1
            };
        },
        init: function(owner, container){
            this.owner = owner;

            container.innerHTML = buildTemplate(this, 'screenshot,screenshottemplate').replace('screenshot');

            this.setImage(null);

            var instance = this;
            Brick.appFunc('user', 'userOptionList', '{C#MODNAME}', function(err, res){
                instance.userOptionList = res.userOptionList;
                instance.render();
            });
        },
        render: function(){
            var uOptions = this.userOptionList;
            if (!uOptions){
                return;
            }
            var tplVal = uOptions.getById('tpl-screenshot'),
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

            if (!scsTemplate.get('value') || scsTemplate.get('value').length === 0){
                this.setelv('code', this._TM.replace('screenshottemplate'));
                return;
            }

            this.setelv('code', scsTemplate.get('value'));
            this.setelv('width', scsWidth.get('value'));
            this.setelv('height', scsHeight.get('value'));
        },

        setImage: function(file){
            if (Y.Lang.isNull(file) || file.type != 'file' || Y.Lang.isNull(file.image)){
                file = null;
            }
            this.file = file;

            var elBSelect = this.el('bselect');
            var elImgTitle = this.el('title');
            var elImgWidth = this.el('width');
            var elImgHeight = this.el('height');
            var elImgCode = this.el('code');

            this.disabled([elBSelect, elImgTitle, elImgWidth, elImgHeight, elImgCode], Y.Lang.isNull(file));
        },
        disabled: function(els, disabled){
            for (var i = 0; i < els.length; i++){
                els[i].disabled = disabled ? 'disabled' : '';
            }
        },
        clearValue: function(els){
            for (var i = 0; i < els.length; i++){
                this.setel(els[i], '');
            }
        },
        onClick: function(el){
            if (Y.Lang.isNull(this.file)){
                return false;
            }
            var tp = this._TId['screenshot'];
            switch (el.id) {
                case tp['bselect']:
                    this.selectItem();
                    return true;
            }
            return false;
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
            if (!this.owner.callback){
                return;
            }
            var item = this.file;
            var linker = new NS.Linker(item);

            var width = this.elv('width') * 1;
            var height = this.elv('height') * 1;
            if (width == 0 && height == 0){
                return;
            }
            var title = this.elv('title');

            var smallLinker = new NS.Linker(item);
            smallLinker.setSize(width, height);

            var html = Abricos.TemplateManager.replace(this.elv('code'), {
                isrc: linker.getSrc(),
                sisrc: smallLinker.getSrc(),
                siw: width,
                sih: height,
                sialt: title,
                sitl: title
            });

            this.owner.callback({
                'html': html,
                'file': item,
                'src': linker.getSrc()
            });
            this.save();
            this.owner.close();
        }
    };

    NS.Screenshot = Screenshot;

};


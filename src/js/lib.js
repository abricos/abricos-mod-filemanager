var Component = new Brick.Component();
Component.requires = {
    mod: [
        {name: 'sys', files: ['application.js']},
        // {name: '{C#MODNAME}', files: ['model.js']}
    ]
};

Component.entryPoint = function(NS){

    var COMPONENT = this,
        SYS = Brick.mod.sys;

    var Y = Brick.YUI,
        L = Y.Lang;

    NS.roles = new Brick.AppRoles('{C#MODNAME}', {
        isAdmin: 50,
        isWrite: 30,
        isView: 10
    });

    SYS.Application.build(COMPONENT, {}, {
        initializer: function(){
            NS.roles.load(function(){
                this.initCallbackFire();
            }, this);
        },
    }, [], {
        REQS: {},
        ATTRS: {
            isLoadAppStructure: {value: false},
        },
        URLS: {
            ws: "#app={C#MODNAMEURI}/wspace/ws/",
            manager: {
                limit: function(){
                    return this.getURL('ws') + 'limitManager/LimitManagerWidget/';
                },
                extension: function(){
                    return this.getURL('ws') + 'extensionManager/ExtensionManagerWidget/';
                },
            }
        }
    });


    var File = function(d){
        this.init(d);
    };
    File.prototype = {
        init: function(d){
            this.type = 'file';
            this.id = d['fh'];
            this.privateid = d['id'];
            this.name = d['fn'];
            this.size = d['fs'];
            this.date = d['d'];
            this.folderid = d['fdid'];
            this.extension = d['ext'];
            this.attribute = d['a'];
            this.image = null;
            if (d['w'] > 0 && d['h'] > 0){
                this.image = {width: d['w'], height: d['h']};
            }
        }
    };
    NS.File = File;

    var Folder = function(d){
        this.init(d);
    };
    Folder.prototype = {
        init: function(d){
            this.id = d['id'];
            this.pid = d['pid'];
            this.name = d['fn'];
            this.phrase = d['ph'];
            this.type = 'folder';
        }
    };
    NS.Folder = Folder;


    var Linker = function(file){
        this.init(file);
    };
    Linker.prototype = {
        init: function(file){
            this.objid = null;
            this.file = file;
            this.imgsize = {w: 0, h: 0};
        },
        setSize: function(width, height){
            this.imgsize = {w: width, h: height};
        },
        setId: function(id){
            this.objid = id;
        },
        getObject: function(){
            var o;
            if (!L.isNull(this.file.image)){
                o = document.createElement('img');
                o.src = this.getSrc();
                o.alt = this.file.name;
            } else {
                o = document.createElement('a');
                o.href = this.getSrc();
                o.innerHTML = this.file.name;
            }
            o.title = this.file.name;
            if (!L.isNull(this.objid)){
                o.id = this.objid;
            }
            return o;
        },
        _getSrc: function(id, name, w, h){
            var ps = '', p = [];
            if (w * 1 > 0){
                p[p.length] = 'w_' + w;
            }
            if (h * 1 > 0){
                p[p.length] = 'h_' + h;
            }
            if (p.length > 0){
                ps = '/' + p.join('-');
            }
            var loc = window.location;

            var src = loc.protocol + '//' + loc.hostname;
            if (loc.port * 1 != 80 && loc.port * 1 > 0){
                src += ":" + loc.port;
            }
            src += '/filemanager/i/' + id + ps + '/' + name;

            return src;
        },
        getSrc: function(){
            var f = this.file;

            return this._getSrc(f.id, f.name, this.imgsize.w, this.imgsize.h);
        },
        getHTML: function(){
            var f = this.file;
            if (!L.isNull(f.image)){
                var w = 0, h = 0, width = f.image.width, height = f.image.height, isz = this.imgsize;
                if (!L.isNull(isz)){
                    if (isz.w > 0){
                        w = isz.w;
                        width = isz.w;
                    }
                    if (isz.h > 0){
                        h = isz.h;
                        height = isz.h;
                    }
                }
                var src = this._getSrc(f.id, f.name, w, h);
                var t = "<img {v#id} src='{v#src}' title='{v#title}' alt='{v#alt}' width='{v#width}' height='{v#height}' />";
                var html = Abricos.TemplateManager.replace(t, {
                    'src': src,
                    'width': width + 'px',
                    'height': height + 'px',
                    'title': f.name,
                    'alt': f.name,
                    'id': !L.isNull(this.objid) ? ("id='" + this.objid + "'") : ''
                });
                return html;
            }

            var div = document.createElement('div');
            div.appendChild(this.getObject());
            return div.innerHTML;
        }
    };

    NS.Linker = Linker;


    var FileUploaders = function(){
        this.init();
    };
    FileUploaders.prototype = {
        init: function(){
            this.idinc = 0;
            this.list = {};
        },
        register: function(uploader){
            var id = this.idinc++;
            this.list[id] = uploader;
            return id;
        },
        remove: function(id){
            delete this.list[id];
        },
        setFile: function(id, fileid, filename){
            var ed = this.list[id];
            if (!ed){
                return;
            }
            try {
                ed.setFile(fileid, filename);
            } catch (e) {
            }
        }
    };

    NS.fileUploaders = new FileUploaders();

    var FileUploader = function(userid, callback, cfg){
        cfg = Y.merge({
            'folderid': 0,
            'folderpath': '',
            'sysfolder': false
        }, cfg || {});
        this.init(userid, callback, cfg);
    };
    FileUploader.prototype = {
        init: function(userid, callback, cfg){
            this.uploadWindow = null;
            this.userid = userid;
            this.callback = callback;
            this.cfg = cfg;
            this.id = NS.fileUploaders.register(this);
        },
        destroy: function(){
            NS.fileUploaders.remove(this.id);
        },
        fileUpload: function(ucfg){
            if (!L.isNull(this.uploadWindow) && !this.uploadWindow.closed){
                this.uploadWindow.focus();
                return;
            }
            var cfg = Y.merge(this.cfg, ucfg || {});

            var url = '/filemanager/upload.html?userid=' + this.userid + '&winid=' + this.id + '&sysfolder=' + (cfg.sysfolder ? '1' : '0') + '&folderid=' + cfg.folderid + "&folderpath=" + cfg.folderpath;
            this.uploadWindow = window.open(
                url, 'upload' + this.id,
                'statusbar=no,menubar=no,toolbar=no,scrollbars=yes,resizable=yes,width=480,height=450'
            );
        },
        setFile: function(fileid, filename){
            if (L.isFunction(this.callback)){
                this.callback(fileid, filename);
            }
        }
    };
    NS.FileUploader = FileUploader;


    /**
     * API модуля
     *
     * @class API
     * @extends Brick.Component.API
     * @static
     */
    var API = NS.API;

    API.showFileBrowserPanel = function(callback){
        API.fn('filemanager', function(){
            API.activeBrowser = new NS.BrowserPanel(callback);
            API.dsRequest();
        });
    };

    API.showImageEditorPanel = function(file){
        API.fn('editor', function(){
            new NS.ImageEditorPanel(new NS.File(file));
        });
    };

    /**
     * Запросить DataSet произвести обновление данных.
     *
     * @method dsRequest
     */
    API.dsRequest = function(){
        if (!NS.data){
            return;
        }
        NS.data.request(true);
    };

};


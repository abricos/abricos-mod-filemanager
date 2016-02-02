var Component = new Brick.Component();
Component.requires = {yahoo: ['dom']};
Component.entryPoint = function(){

    var Dom = YAHOO.util.Dom,
        L = YAHOO.lang;

    var NS = this.namespace;

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

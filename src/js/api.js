var Component = new Brick.Component();
Component.entryPoint = function(NS){

    var API = NS.API;

    API.showFileBrowserPanel = function(callback){
        API.fn('filemanager', function(){
            API.activeBrowser = new NS.BrowserPanel();
            API.activeBrowser.on('selected', callback);
        });
    };

    API.showImageEditorPanel = function(file){
        API.fn('editor', function(){
            new NS.ImageEditorPanel(new NS.File(file));
        });
    };
};

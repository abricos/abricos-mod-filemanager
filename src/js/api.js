var Component = new Brick.Component();
Component.entryPoint = function(NS){

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

    API.dsRequest = function(){
        if (!NS.data){
            return;
        }
        NS.data.request(true);
    };
};

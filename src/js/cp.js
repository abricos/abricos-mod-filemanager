var Component = new Brick.Component();
Component.requires = {
    mod: [{name: 'user', files: ['cpanel.js']}]
};
Component.entryPoint = function(){

    if (Brick.AppRoles.check('user', '50')){
        return;
    }
    var cp = Brick.mod.user.cp;

    var menuItem = new cp.MenuItem(this.moduleName, 'filemanager');
    menuItem.icon = '/modules/filemanager/images/cp_icon.gif';
    menuItem.titleId = 'mod.filemanager.cp.title';
    menuItem.entryComponent = 'manager';
    menuItem.entryPoint = 'Brick.mod.filemanager.API.showManagerWidget';
    cp.MenuManager.add(menuItem);
};

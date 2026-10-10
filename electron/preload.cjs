const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('desktopApp', {
  reload: () => ipcRenderer.invoke('desktop:reload'),
});

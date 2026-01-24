const { contextBridge, ipcRenderer } = require('electron');

// Expose protected methods that allow the renderer process to use
// the ipcRenderer without exposing the entire object
contextBridge.exposeInMainWorld('electronAPI', {
  getSources: () => ipcRenderer.invoke('get-sources'),
  saveRecording: (buffer, filename) => ipcRenderer.invoke('save-recording', buffer, filename),
  onWindowShown: (callback) => ipcRenderer.on('window-shown', callback),
  onWindowHidden: (callback) => ipcRenderer.on('window-hidden', callback)
});
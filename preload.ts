import { contextBridge, ipcRenderer } from 'electron';

// Expose protected methods that allow the renderer process to use
// the ipcRenderer without exposing the entire object
contextBridge.exposeInMainWorld('electronAPI', {
  getSources: () => ipcRenderer.invoke('get-sources'),
  startRecording: (sourceId: string) => ipcRenderer.invoke('start-recording', sourceId),
  saveRecording: (buffer: Buffer, filename: string) => ipcRenderer.invoke('save-recording', buffer, filename),
  autoSaveRecording: (buffer: Buffer, filename: string) => ipcRenderer.invoke('auto-save-recording', buffer, filename),
  getNetworkUsage: () => ipcRenderer.invoke('get-network-usage'),
  onNetworkUsageUpdate: (callback: (event: Electron.IpcRendererEvent, data: any) => void) => {
    ipcRenderer.on('network-usage-update', callback);
  },
  removeNetworkUsageUpdateListener: (callback: (event: Electron.IpcRendererEvent, data: any) => void) => {
    ipcRenderer.removeListener('network-usage-update', callback);
  },
  onWindowShown: (callback: () => void) => ipcRenderer.on('window-shown', callback),
  onWindowHidden: (callback: () => void) => ipcRenderer.on('window-hidden', callback),
  onResetStates: (callback: () => void) => ipcRenderer.on('reset-all-states-before-logout', callback),
  onStopRecordingBeforeLogout: (callback: () => void) => ipcRenderer.on('stop-recording-before-logout', callback),
  getUserInfo: (callback: (event: Electron.IpcRendererEvent, user: any) => void) => {
    ipcRenderer.on('user-info', callback);
  },
  loginSuccess: (user: any) => ipcRenderer.invoke('login-success', user),
  checkIn: () => ipcRenderer.invoke('check-in'),
  break: (isOnBreak: boolean) => ipcRenderer.invoke('break', isOnBreak),
  checkOut: () => ipcRenderer.invoke('check-out'),
  logoutRequest: () => ipcRenderer.invoke('logout-request')
});
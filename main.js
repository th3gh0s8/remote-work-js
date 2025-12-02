const { app, BrowserWindow, ipcMain, desktopCapturer } = require('electron');
const path = require('path');

let mainWindow;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 800,
    height: 600,
    webPreferences: {
      nodeIntegration: true,
      contextIsolation: false
    },
    icon: path.join(__dirname, 'assets/icon.png') // Optional: Add an icon
  });

  mainWindow.loadFile('index.html');
}

// Handle getting available sources for screen capture
ipcMain.handle('get-sources', async () => {
  try {
    const sources = await desktopCapturer.getSources({
      types: ['screen', 'window'],
      thumbnailSize: { width: 150, height: 150 }
    });
    
    return sources.map(source => ({
      name: source.name,
      id: source.id,
      thumbnail: source.thumbnail.toDataURL()
    }));
  } catch (error) {
    console.error('Error getting sources:', error);
    throw error;
  }
});

// Handle starting recording (will pass source ID to renderer)
ipcMain.handle('start-recording', async (event, sourceId) => {
  try {
    // Just return the source ID to the renderer process
    // The actual recording will be handled in the renderer
    return sourceId;
  } catch (error) {
    console.error('Error starting recording:', error);
    throw error;
  }
});

// Handle saving the recorded file
ipcMain.handle('save-recording', async (event, buffer) => {
  try {
    const { dialog } = require('electron');

    const { filePath } = await dialog.showSaveDialog({
      filters: [
        { name: 'WebM Video', extensions: ['webm'] }
      ],
      defaultPath: `recording-${Date.now()}.webm`
    });

    if (filePath) {
      const fs = require('fs');
      fs.writeFileSync(filePath, buffer);
      return { success: true, filePath };
    } else {
      return { success: false, error: 'No file path selected' };
    }
  } catch (error) {
    console.error('Error saving recording:', error);
    return { success: false, error: error.message };
  }
});

// Handle auto-saving the recorded file to a default location
ipcMain.handle('auto-save-recording', async (event, buffer, filename) => {
  try {
    const fs = require('fs');
    const path = require('path');
    const os = require('os');

    // Create a 'Recordings' directory in the user's home folder
    const recordingsDir = path.join(os.homedir(), 'Recordings');

    // Create the directory if it doesn't exist
    if (!fs.existsSync(recordingsDir)) {
      fs.mkdirSync(recordingsDir, { recursive: true });
    }

    // Create the full file path
    const filePath = path.join(recordingsDir, filename);

    // Write the file
    fs.writeFileSync(filePath, buffer);

    return { success: true, filePath };
  } catch (error) {
    console.error('Error auto-saving recording:', error);
    return { success: false, error: error.message };
  }
});

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow();
  }
});
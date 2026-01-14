const { app, BrowserWindow, ipcMain, desktopCapturer } = require('electron');
const path = require('path');

let mainWindow;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 800,
    height: 600,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      enableRemoteModule: false,
      webSecurity: true,
      preload: path.join(__dirname, 'preload.js')
    },
    icon: path.join(__dirname, 'assets/icon.png')
  });

  // Enable screen capture permissions
  mainWindow.webContents.session.setPermissionRequestHandler((webContents, permission, callback) => {
    if (permission === 'media' || permission === 'desktop-capture') {
      callback(true);
    } else {
      callback(false);
    }
  });

  mainWindow.loadFile('index.html');

  // Open DevTools for debugging
  mainWindow.webContents.openDevTools({ mode: 'detach' });
}

// Handle getting screen sources
ipcMain.handle('get-sources', async () => {
  try {
    const sources = await desktopCapturer.getSources({
      types: ['screen'],
      thumbnailSize: { width: 0, height: 0 } // No thumbnail to reduce overhead
    });

    console.log(`Found ${sources.length} screen sources`);
    
    return sources.map(source => ({
      name: source.name,
      id: source.id
    }));
  } catch (error) {
    console.error('Error getting sources:', error);
    return [];
  }
});

// Handle saving recordings
ipcMain.handle('save-recording', async (event, buffer, filename) => {
  try {
    const fs = require('fs');
    const recordingsDir = path.join(__dirname, 'captures');

    if (!fs.existsSync(recordingsDir)) {
      fs.mkdirSync(recordingsDir, { recursive: true });
    }

    // Sanitize filename to prevent path traversal
    const safeFilename = path.basename(filename).replace(/\0/g, '');
    const safeFilePath = path.resolve(recordingsDir, safeFilename);
    const resolvedRecordingsDir = path.resolve(recordingsDir);

    // Verify the resolved path is within the intended directory
    if (!safeFilePath.startsWith(resolvedRecordingsDir)) {
      throw new Error('Invalid file path');
    }

    fs.writeFileSync(safeFilePath, buffer);

    return { success: true, filePath: safeFilePath };
  } catch (error) {
    console.error('Error saving recording:', error);
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
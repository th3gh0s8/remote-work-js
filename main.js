const { app, BrowserWindow, ipcMain, desktopCapturer, Tray, Menu, nativeImage } = require('electron');
const path = require('path');

let mainWindow;

function createWindow() {
  const { screen } = require('electron');

  mainWindow = new BrowserWindow({
    width: 800,
    height: 600,
    webPreferences: {
      nodeIntegration: true,
      contextIsolation: false,
      enableRemoteModule: true,
      // Enable screen capture permissions
      autoplayPolicy: 'no-user-gesture-required'
    },
    icon: path.join(__dirname, 'assets/icon.png'), // Optional: Add an icon
    webSecurity: false, // Allow mixed content for screen capture
    // Enable screen capture
    alwaysOnTop: false,
    fullscreenable: true
  });

  // Configure session to allow screen capture
  const ses = mainWindow.webContents.session;
  ses.setDisplayMediaRequestHandler((request, callback) => {
    // For screen capture, we'll allow access to screen sources
    callback({ video: true, audio: false });
  });

  // Enable media access for screen capture
  mainWindow.webContents.session.setPermissionRequestHandler((webContents, permission, callback) => {
    if (permission === 'media' || permission === 'desktop-capture') {
      callback(true); // Grant media and desktop capture access
    } else {
      callback(false); // Deny other permissions
    }
  });

  mainWindow.loadFile('index.html');

  // Open DevTools for debugging screen capture
  mainWindow.webContents.openDevTools({ mode: 'detach' });
}

// System tray functionality
let tray = null;

app.whenReady().then(() => {
  createWindow();

  // Create tray icon
  const iconPath = path.join(__dirname, 'assets/Powersoft_logo__1_.png');
  const iconImage = nativeImage.createFromPath(iconPath);

  // If icon doesn't exist, use a default icon
  tray = new Tray(iconImage.isEmpty() ? null : iconImage);

  const contextMenu = Menu.buildFromTemplate([
    {
      label: 'Show App',
      click: () => {
        if (mainWindow && !mainWindow.isDestroyed()) {
          mainWindow.show();
          mainWindow.focus();
        }
      }
    },
    {
      label: 'Quit',
      click: () => {
        app.quit();
      }
    }
  ]);

  tray.setContextMenu(contextMenu);
  tray.setToolTip('Remote Work Tracker');
  tray.setTitle('Remote Work Tracker');

  // Show window when tray icon is clicked
  tray.on('click', () => {
    if (mainWindow && !mainWindow.isDestroyed()) {
      if (mainWindow.isVisible()) {
        mainWindow.hide();
      } else {
        mainWindow.show();
        mainWindow.focus();
      }
    }
  });
});

// Handle getting available sources for screen capture
ipcMain.handle('get-sources', async () => {
  try {
    console.log('Getting screen sources...');
    const sources = await desktopCapturer.getSources({
      types: ['screen'],
      thumbnailSize: { width: 150, height: 150 }
    });

    console.log(`Found ${sources.length} screen sources:`, sources.map(s => ({name: s.name, id: s.id})));

    if (sources.length === 0) {
      console.error('No screen sources found!');
      return [];
    }

    return sources.map(source => ({
      name: source.name,
      id: source.id,
      thumbnail: source.thumbnail.toDataURL()
    }));
  } catch (error) {
    console.error('Error getting sources:', error);
    console.error('Error details:', error.message, error.stack);
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

// Handle auto-saving the recorded file to a default location
ipcMain.handle('save-recording', async (event, buffer, filename) => {
  try {
    const fs = require('fs');
    const path = require('path');

    // Create a 'captures' directory in the project root
    const recordingsDir = path.join(__dirname, 'captures');

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

// Handle auto-saving the recorded file to a default location
ipcMain.handle('auto-save-recording', async (event, buffer, filename) => {
  try {
    const fs = require('fs');
    const path = require('path');

    // Create a 'captures' directory in the project root
    const recordingsDir = path.join(__dirname, 'captures');

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

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    // Don't quit the app when window is closed, just hide it
    // The app will continue running in the system tray
  }
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow();
  }
});
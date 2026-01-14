const { app, BrowserWindow, ipcMain, desktopCapturer, Tray, Menu, nativeImage } = require('electron');
const path = require('path');
const DatabaseConnection = require('./db_connection');

let mainWindow;
let loginWindow = null;
const db = new DatabaseConnection();

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

  // Release reference so the window can be garbage collected
  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

// System tray functionality
let tray = null;

// Create login window
function createLoginWindow() {
  loginWindow = new BrowserWindow({
    width: 450,
    height: 600,
    resizable: false,
    webPreferences: {
      nodeIntegration: true,
      contextIsolation: false,
      enableRemoteModule: true
    },
    icon: path.join(__dirname, 'assets/icon.png')
  });

  loginWindow.loadFile('login.html');

  // Open DevTools for debugging
  // loginWindow.webContents.openDevTools({ mode: 'detach' });

  loginWindow.on('closed', () => {
    loginWindow = null;
  });
}

app.whenReady().then(async () => {
  // Connect to database
  const dbConnected = await db.connect();
  if (!dbConnected) {
    console.error('Failed to connect to database');
    // You might want to show an error message to the user here
  }

  // Show login window first
  createLoginWindow();
});

// Handle login
ipcMain.handle('login', async (event, repid, mobile) => {
  try {
    const result = await db.authenticateUser(repid, mobile);
    return result;
  } catch (error) {
    console.error('Login error:', error);
    return { success: false, message: 'An error occurred during authentication' };
  }
});

// Store logged-in user information
let loggedInUser = null;

// Handle successful login
ipcMain.handle('login-success', async (event, user) => {
  // Store the logged-in user
  loggedInUser = user;

  // Close login window
  if (loginWindow && !loginWindow.isDestroyed()) {
    loginWindow.close();
  }

  // Create main application window
  createWindow();

  // Pass user information to the renderer
  mainWindow.webContents.once('dom-ready', () => {
    mainWindow.webContents.send('user-info', user);
  });

  // Create tray icon
  const iconPath = path.join(__dirname, 'assets/logo.jpg');
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
    { type: 'separator' },
    {
      label: 'Quit',
      click: () => {
        // Force quit the application
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

  // Flag to determine if we're quitting the app or just closing the window
  let isQuitting = false;

  app.on('before-quit', () => {
    isQuitting = true; // Set flag to allow actual quitting
  });

  // Also handle the case when user tries to close the window
  mainWindow.on('close', (event) => {
    if (!isQuitting) {
      // Prevent the window from closing, just hide it
      event.preventDefault();
      mainWindow.hide();
    }
    // If isQuitting is true, the window will close normally
  });

  // Handle window visibility changes to notify renderer
  mainWindow.on('show', () => {
    mainWindow.webContents.send('window-shown');
  });

  mainWindow.on('hide', () => {
    mainWindow.webContents.send('window-hidden');
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

// Handle saving the recorded file to the web_images table
ipcMain.handle('save-recording', async (event, buffer, filename) => {
  try {
    // Extract user information from the currently logged-in user
    // We'll need to identify the user somehow - for now, we'll use a global variable
    // In a real application, you might track sessions differently
    const userId = loggedInUser ? loggedInUser.ID : 1; // Use logged-in user ID or default to 1
    const brId = loggedInUser ? loggedInUser.br_id : 1; // Use user's branch ID or default to 1
    const currentDate = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
    const currentTime = new Date().toTimeString().split(' ')[0]; // HH:MM:SS

    // Insert the recording into the web_images table
    const query = `
      INSERT INTO web_images
      (br_id, imgID, imgName, itmName, type, user_id, date, time, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `;

    // Use the filename as the imgName and extract the user ID from the login session
    // For now, we'll use a placeholder imgID - in a real scenario, you'd generate a unique ID
    const imgID = Date.now(); // Using timestamp as a simple unique ID

    // Execute the query
    const [result] = await db.connection.execute(query, [
      brId,           // br_id
      imgID,          // imgID
      filename,       // imgName (the filename of the recording)
      'Work Session Recording', // itmName (description of the recording)
      'recording',    // type (indicating this is a recording)
      userId,         // user_id (ID of the user who made the recording)
      currentDate,    // date
      currentTime,    // time
      'active'        // status
    ]);

    console.log(`Recording saved to database with ID: ${result.insertId} for user ID: ${userId}`);

    return {
      success: true,
      id: result.insertId,
      message: `Recording saved to database with ID: ${result.insertId}`
    };
  } catch (error) {
    console.error('Error saving recording to database:', error);
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
  // Keep the app running in background on all platforms, not just macOS
  // This allows the app to stay active in the system tray
  if (process.platform === 'darwin') {
    return;
  }
  // Don't quit the app when window is closed - keep it running in background
});

app.on('activate', () => {
  // On macOS it's common to re-create a window in the app when the
  // dock icon is clicked and there are no other windows open.
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow();
  } else {
    // If there are windows but they're hidden, show them
    const windows = BrowserWindow.getAllWindows();
    for (const win of windows) {
      if (!win.isVisible()) {
        win.show();
      }
      win.focus();
    }
  }
});
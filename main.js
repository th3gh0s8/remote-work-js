const { app, BrowserWindow, ipcMain, desktopCapturer, Tray, Menu, nativeImage, net } = require('electron');
const path = require('path');
const DatabaseConnection = require('./db_connection');

// Variables to track network usage
let totalBytesDownloaded = 0;
let totalBytesUploaded = 0;
let previousBytesDownloaded = 0;
let previousBytesUploaded = 0;
let networkUsageInterval = null;

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

// Function to log user activity to the database
async function logUserActivity(activityType, duration = 0) {
  if (!db.connection || !loggedInUser) {
    console.error('Database not connected or user not logged in');
    return;
  }

  try {
    const query = `
      INSERT INTO user_activity
      (salesrepTb, activity_type, duration, rDateTime)
      VALUES (?, ?, ?, NOW())
    `;

    const [result] = await db.connection.execute(query, [
      loggedInUser.ID,      // salesrepTb (user ID)
      activityType,         // activity_type
      duration              // duration
    ]);

    console.log(`Activity logged: ${activityType} for user ID: ${loggedInUser.ID}`);
    return { success: true, id: result.insertId };
  } catch (error) {
    console.error('Error logging user activity:', error);
    return { success: false, error: error.message };
  }
}

// Handle successful login
ipcMain.handle('login-success', async (event, user) => {
  // Store the logged-in user
  loggedInUser = user;

  // Log login activity
  await logUserActivity('login');

  // Close login window
  if (loginWindow && !loginWindow.isDestroyed()) {
    loginWindow.close();
  }

  // Create main application window
  createWindow();

  // Pass user information to the renderer
  mainWindow.webContents.once('dom-ready', () => {
    mainWindow.webContents.send('user-info', user);

    // Start network monitoring after DOM is ready
    startNetworkMonitoring();
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

  // Handle app quit to log logout activity
  app.on('quit', async () => {
    if (loggedInUser) {
      await logUserActivity('logout');
    }
  });

  // Handle window visibility changes to notify renderer
  mainWindow.on('show', () => {
    mainWindow.webContents.send('window-shown');
  });

  mainWindow.on('hide', () => {
    mainWindow.webContents.send('window-hidden');
  });
});

// Handle check-in activity
ipcMain.handle('check-in', async (event) => {
  if (!loggedInUser) {
    return { success: false, message: 'User not logged in' };
  }

  try {
    const result = await logUserActivity('check-in');
    return result;
  } catch (error) {
    console.error('Error logging check-in:', error);
    return { success: false, error: error.message };
  }
});

// Handle break activity
ipcMain.handle('break', async (event, isOnBreak) => {
  if (!loggedInUser) {
    return { success: false, message: 'User not logged in' };
  }

  try {
    const activityType = isOnBreak ? 'break-start' : 'break-end';
    const result = await logUserActivity(activityType);
    return result;
  } catch (error) {
    console.error('Error logging break:', error);
    return { success: false, error: error.message };
  }
});

// Handle check-out activity
ipcMain.handle('check-out', async (event) => {
  if (!loggedInUser) {
    return { success: false, message: 'User not logged in' };
  }

  try {
    const result = await logUserActivity('check-out');
    return result;
  } catch (error) {
    console.error('Error logging check-out:', error);
    return { success: false, error: error.message };
  }
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

// Handle uploading the recorded file to a server
ipcMain.handle('save-recording', async (event, buffer, filename) => {
  try {
    // Extract user information from the currently logged-in user
    const userId = loggedInUser ? loggedInUser.ID : 1; // Use logged-in user ID or default to 1
    const brId = loggedInUser ? loggedInUser.br_id : 1; // Use user's branch ID or default to 1

    // Define server configuration
    // In production, this would be a remote server URL
    // For local development, we'll use localhost with upload script
    const isProduction = process.env.NODE_ENV === 'production';
    const serverUrl = isProduction
      ? 'https://your-remote-server.com/upload'  // Replace with actual remote server
      : 'http://localhost/upload.php';  // Local development server with PHP script in htdocs

    // Perform the upload request using form data instead of JSON
    const FormData = require('form-data');
    const axios = require('axios');

    const formData = new FormData();
    // Convert buffer to base64 string to ensure binary data integrity during transmission
    const base64Data = buffer.toString('base64');
    formData.append('file', base64Data); // Send base64 string as form field
    formData.append('userId', userId);
    formData.append('brId', brId);
    formData.append('filename', filename);
    formData.append('type', 'recording');
    formData.append('description', 'Work Session Recording');

    const response = await axios.post(serverUrl, formData, {
      headers: {
        ...formData.getHeaders(),
        // Add any authentication headers if needed
        'Authorization': `Bearer ${process.env.UPLOAD_TOKEN || 'local-token'}`, // Example auth header
      },
      timeout: 120000 // Increased timeout to accommodate larger files
    });

    console.log(`Recording uploaded successfully to server:`, response.data);

    // If upload is successful, also log to the web_images table for reference
    if (response.data && response.data.fileId) {
      // Insert a record in web_images table with the server file ID
      const query = `
        INSERT INTO web_images
        (br_id, imgID, imgName, itmName, type, user_id, date, time, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      `;

      const currentDate = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
      const currentTime = new Date().toTimeString().split(' ')[0]; // HH:MM:SS
      const imgID = response.data.fileId || Date.now(); // Use server's file ID if available

      const [result] = await db.connection.execute(query, [
        brId,           // br_id
        imgID,          // imgID (server file ID)
        filename,       // imgName (original filename)
        'Work Session Recording', // itmName (description)
        'recording',    // type
        userId,         // user_id
        currentDate,    // date
        currentTime,    // time
        'uploaded'      // status (changed to uploaded since it's on server)
      ]);

      console.log(`Recording record saved to database with ID: ${result.insertId}`);

      return {
        success: true,
        id: result.insertId,
        fileId: response.data.fileId,
        message: `Recording uploaded to server and saved to database with ID: ${result.insertId}`
      };
    } else {
      // If server didn't return a file ID, use timestamp
      const query = `
        INSERT INTO web_images
        (br_id, imgID, imgName, itmName, type, user_id, date, time, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      `;

      const currentDate = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
      const currentTime = new Date().toTimeString().split(' ')[0]; // HH:MM:SS
      const imgID = Date.now(); // Fallback to timestamp

      const [result] = await db.connection.execute(query, [
        brId,           // br_id
        imgID,          // imgID
        filename,       // imgName (original filename)
        'Work Session Recording', // itmName (description)
        'recording',    // type
        userId,         // user_id
        currentDate,    // date
        currentTime,    // time
        'uploaded'      // status
      ]);

      console.log(`Recording record saved to database with ID: ${result.insertId}`);

      return {
        success: true,
        id: result.insertId,
        message: `Recording uploaded to server and saved to database with ID: ${result.insertId}`
      };
    }
  } catch (error) {
    console.error('Error uploading recording to server:', error);

    // If upload fails, save to local captures directory as fallback
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

      // Also log to the database with 'local' status
      const userId = loggedInUser ? loggedInUser.ID : 1;
      const brId = loggedInUser ? loggedInUser.br_id : 1;
      const currentDate = new Date().toISOString().split('T')[0];
      const currentTime = new Date().toTimeString().split(' ')[0];

      const query = `
        INSERT INTO web_images
        (br_id, imgID, imgName, itmName, type, user_id, date, time, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      `;

      const imgID = Date.now();

      const [result] = await db.connection.execute(query, [
        brId,           // br_id
        imgID,          // imgID
        filename,       // imgName
        'Work Session Recording', // itmName
        'recording',    // type
        userId,         // user_id
        currentDate,    // date
        currentTime,    // time
        'local-fallback' // status indicating it was saved locally due to upload failure
      ]);

      return {
        success: false,
        id: result.insertId,
        error: error.message,
        message: `Upload failed, saved locally instead. Error: ${error.message}`
      };
    } catch (fallbackError) {
      console.error('Fallback save also failed:', fallbackError);
      return {
        success: false,
        error: error.message,
        fallbackError: fallbackError.message,
        message: `Upload failed and local fallback also failed. Upload error: ${error.message}, Fallback error: ${fallbackError.message}`
      };
    }
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

// Function to get network usage statistics
function getNetworkUsage() {
  // In a real implementation, we would gather actual network statistics
  // For now, we'll simulate network usage based on the app's activities

  // This is a simplified approach - in a real scenario, we would need to
  // monitor actual network interfaces or track all network requests

  // For demonstration purposes, we'll return simulated values
  // that increase over time to show the functionality
  const simulatedDownload = totalBytesDownloaded + Math.floor(Math.random() * 10000);
  const simulatedUpload = totalBytesUploaded + Math.floor(Math.random() * 5000);

  return {
    totalDownloaded: simulatedDownload,
    totalUploaded: simulatedUpload,
    downloadSpeed: Math.floor(Math.random() * 500), // KB/s
    uploadSpeed: Math.floor(Math.random() * 200)    // KB/s
  };
}

// IPC handler to get current network usage
ipcMain.handle('get-network-usage', async (event) => {
  return getNetworkUsage();
});

// Function to start network usage monitoring
function startNetworkMonitoring() {
  if (networkUsageInterval) {
    clearInterval(networkUsageInterval);
  }

  // Update network usage every second
  networkUsageInterval = setInterval(() => {
    if (mainWindow) {
      // Send network usage to renderer
      mainWindow.webContents.send('network-usage-update', getNetworkUsage());
    }
  }, 1000);
}

// Handle network usage request from renderer
ipcMain.on('request-network-usage', (event) => {
  event.reply('network-usage-response', getNetworkUsage());
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
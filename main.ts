// main.ts - Main process for Electron application with TypeScript

import { app, BrowserWindow, ipcMain, desktopCapturer, Tray, Menu, nativeImage, net } from 'electron';
import * as path from 'path';
import * as si from 'systeminformation';
import DatabaseConnection from './db_connection';
import SessionManager from './session_manager';

// Variables to track network usage
let totalBytesDownloaded = 0;
let totalBytesUploaded = 0;
let previousBytesDownloaded = 0;
let previousBytesUploaded = 0;
let networkUsageInterval: NodeJS.Timeout | null = null;

let mainWindow: BrowserWindow | null = null;
let loginWindow: BrowserWindow | null = null;
const db = new DatabaseConnection();
let sessionManager: SessionManager;

// Global variable for the tray
let tray: Tray | null = null;

// Store logged-in user information
let loggedInUser: any = null;

// Variables to track the last known window state for comparison
let lastKnownWindowState: boolean | null = null;

/**
 * Monitors the currently relevant window (main when logged in, login otherwise) and updates the tray context menu when that window's visible/minimized state changes.
 *
 * When the observed visibility/minimized state differs from the last known state, the function rebuilds and sets the tray's context menu to reflect the new window state.
 */
function monitorWindowState() {
  // Check the current window state
  let targetWindow = null;
  let currentState = false;

  if (loggedInUser) {
    targetWindow = mainWindow;
  } else {
    targetWindow = loginWindow;
  }

  if (targetWindow && !targetWindow.isDestroyed()) {
    currentState = targetWindow.isVisible() && !targetWindow.isMinimized();
  }

  // Compare with the last known state
  if (lastKnownWindowState !== currentState) {
    // State has changed, update the tray menu
    lastKnownWindowState = currentState;
    if (tray) {
      const isLoggedIn = !!loggedInUser;
      const contextMenu = Menu.buildFromTemplate(createTrayMenu(isLoggedIn));
      tray.setContextMenu(contextMenu);
    }
  }
}

// Set up a periodic check for window state changes
setInterval(monitorWindowState, 200); // Check every 200ms



/**
 * Creates and shows the main application window configured for screen capture.
 *
 * Configures webPreferences to allow Node integration and autoplay, grants display-media and media
 * permissions for desktop capture, loads the renderer (index.html), opens DevTools, and clears the
 * global `mainWindow` reference (and stops network monitoring) when the window is closed.
 */
function createWindow(): void {
  const { screen } = require('electron');

  mainWindow = new BrowserWindow({
    width: 800,
    height: 600,
    webPreferences: {
      nodeIntegration: true,
      contextIsolation: false,
      // Enable screen capture permissions
      autoplayPolicy: 'no-user-gesture-required' as any // Cast to any to bypass TypeScript check
    },
    icon: path.join(__dirname, '..', 'assets', 'icon.png') // Optional: Add an icon
  });

  // Configure session to allow screen capture
  const ses = mainWindow.webContents.session;
  ses.setDisplayMediaRequestHandler((request) => {
    // For screen capture, we'll allow access to screen sources
    return Promise.resolve({ video: true, audio: false });
  });

  // Enable media access for screen capture
  mainWindow.webContents.session.setPermissionRequestHandler((webContents, permission) => {
    if (permission === 'media' || permission === 'display-capture') {
      return true; // Grant media and desktop capture access
    } else {
      return false; // Deny other permissions
    }
  });

  // Additional configuration for screen capture compatibility
  // Note: 'select-desktop-capture-source' is not a standard Electron event
  // We'll use the newer desktopCapturer API instead

  mainWindow.loadFile('index.html');

  // Open DevTools for debugging screen capture
  mainWindow.webContents.openDevTools({ mode: 'detach' });

  // Release reference so the window can be garbage collected
  mainWindow.on('closed', () => {
    // Clear network monitoring interval when window is closed
    if (networkUsageInterval) {
      clearInterval(networkUsageInterval);
      networkUsageInterval = null;
    }
    mainWindow = null;
  });
}

// System tray functionality
/**
 * Creates and manages the system tray icon and menu
 */
function setupTray(): void {
  // Create tray icon - look for assets in the parent directory since compiled JS is in dist/
  const iconPath = path.join(__dirname, '..', 'assets', 'logo.jpg');
  const iconImage = nativeImage.createFromPath(iconPath);

  // If icon doesn't exist, use a default icon
  tray = new Tray(iconImage.isEmpty() ? path.join(__dirname, '..', 'assets', 'icon.png') : iconPath);

  // Update tray tooltip based on login status
  if (loggedInUser) {
    tray.setToolTip(`XPloyee - Logged In as ${loggedInUser.Name || loggedInUser.RepID}`);
  } else {
    tray.setToolTip('XPloyee - Logged Out');
  }

  // Create context menu
  const contextMenu = Menu.buildFromTemplate(createTrayMenu(!!loggedInUser));
  tray.setContextMenu(contextMenu);
  if (tray) {
    tray.setTitle('XPloyee');
  }

  // Handle tray icon single click (toggle visibility)
  tray.removeAllListeners('click'); // Remove any existing listeners to prevent duplicates
  tray.on('click', () => {
    if (loggedInUser) {
      // User is logged in, toggle main window visibility
      if (mainWindow && !mainWindow.isDestroyed()) {
        if (mainWindow.isVisible()) {
          // Window is visible, hide it (minimize to tray)
          mainWindow.hide();
        } else {
          // Window is hidden, show it
          if (mainWindow.isMinimized()) {
            mainWindow.restore();
          }
          mainWindow.show();
          mainWindow.focus();
        }
      } else {
        // Main window doesn't exist, create it
        createWindow();
      }
    } else {
      // User is not logged in, toggle login window visibility
      if (loginWindow && !loginWindow.isDestroyed()) {
        if (loginWindow.isVisible()) {
          // Window is visible, hide it
          loginWindow.hide();
        } else {
          // Window is hidden, show it
          if (loginWindow.isMinimized()) {
            loginWindow.restore();
          }
          loginWindow.show();
          loginWindow.focus();
        }
      } else {
        // Login window doesn't exist, create it
        createLoginWindow();
      }
    }
    // Update the tray menu to reflect the new visibility state
    setTimeout(() => {
      updateTrayMenu();
    }, 50); // Small delay to ensure the window state is updated
  });



}


/**
 * Build a tray context menu reflecting current window visibility and authentication state.
 *
 * @param isLoggedIn - When `true`, include the authentication actions for a logged-in session (Login, separator, Logout); when `false`, include only the Login action. The final menu also contains Show App or Hide App depending on the currently targeted window's visibility and a Quit item.
 * @returns An array of Electron MenuItemConstructorOptions representing the constructed tray menu items in their display order.
 */
function createTrayMenu(isLoggedIn: boolean): Array<Electron.MenuItemConstructorOptions> {
  // Determine current window state to show appropriate buttons
  let targetWindow = null;
  let isWindowVisible = false;

  if (loggedInUser) {
    targetWindow = mainWindow;
  } else {
    targetWindow = loginWindow;
  }

  if (targetWindow && !targetWindow.isDestroyed()) {
    isWindowVisible = targetWindow.isVisible() && !targetWindow.isMinimized();
  }

  const menuItems: Array<Electron.MenuItemConstructorOptions> = [];

  // Add Show App button if window is not visible
  if (!isWindowVisible) {
    menuItems.push({
      label: 'Show App',
      click: () => {
        // Determine which window to operate on based on login state
        let targetWindow = null;

        if (loggedInUser) {
          // User is logged in, use main window
          targetWindow = mainWindow;
        } else {
          // User is not logged in, use login window
          targetWindow = loginWindow;
        }

        if (targetWindow && !targetWindow.isDestroyed()) {
          // Show the window
          if (targetWindow.isMinimized()) {
            targetWindow.restore();
          }
          targetWindow.show();
          targetWindow.focus();
        } else {
          // No appropriate window exists, create it
          if (loggedInUser) {
            // User is logged in, create main window
            createWindow();
          } else {
            // User is not logged in, create login window
            createLoginWindow();
          }
        }
        // Update the tray menu immediately to reflect the new state
        setImmediate(() => {
          updateTrayMenu();
        });
      }
    });
  }

  // Add Hide App button if window is visible
  if (isWindowVisible) {
    menuItems.push({
      label: 'Hide App',
      click: () => {
        // Determine which window to operate on based on login state
        let targetWindow = null;

        if (loggedInUser) {
          // User is logged in, use main window
          targetWindow = mainWindow;
        } else {
          // User is not logged in, use login window
          targetWindow = loginWindow;
        }

        if (targetWindow && !targetWindow.isDestroyed()) {
          // Hide the window
          targetWindow.hide();
        }
        // Update the tray menu immediately to reflect the new state
        setImmediate(() => {
          updateTrayMenu();
        });
      }
    });
  }

  if (isLoggedIn) {
    menuItems.push(
      {
        label: 'Login',
        click: async () => {
          // Update tray tooltip to indicate logged out status
          if (tray) {
            tray.setToolTip('XPloyee - Logged Out');
          }

          // Update the tray menu to reflect logged out state
          updateTrayMenu();

          // If there's a main window, close it first
          if (mainWindow && !mainWindow.isDestroyed()) {
            // Notify renderer to reset all states
            mainWindow.webContents.send('reset-all-states-before-logout');
            // Wait a brief moment for the renderer to process the reset command
            await new Promise(resolve => setTimeout(resolve, 500));

            mainWindow.close();
          }

          await createLoginWindow();
        }
      },
      { type: 'separator' },
      {
        label: 'Logout',
        click: async () => {
          // Notify renderer to reset all states before logging out
          if (mainWindow && !mainWindow.isDestroyed()) {
            mainWindow.webContents.send('reset-all-states-before-logout');
            // Wait a brief moment for the renderer to process the reset command
            await new Promise(resolve => setTimeout(resolve, 500));
          }

          // Clear the logged in user and all session data
          loggedInUser = null;
          await sessionManager.clearAllSessionData();

          // Update tray tooltip to indicate logged out status
          if (tray) {
            tray.setToolTip('XPloyee - Logged Out');
          }

          // Update the tray menu to reflect logged out state
          updateTrayMenu();

          // Close main window and show login window
          if (mainWindow && !mainWindow.isDestroyed()) {
            mainWindow.close();
          }

          await createLoginWindow();
        }
      }
    );
  } else {
    menuItems.push(
      {
        label: 'Login',
        click: async () => {
          // Update tray tooltip to indicate logged out status
          if (tray) {
            tray.setToolTip('XPloyee - Logged Out');
          }

          // Update the tray menu to reflect logged out state
          updateTrayMenu();

          // If there's a main window, close it first
          if (mainWindow && !mainWindow.isDestroyed()) {
            // Notify renderer to reset all states
            mainWindow.webContents.send('reset-all-states-before-logout');
            // Wait a brief moment for the renderer to process the reset command
            await new Promise(resolve => setTimeout(resolve, 500));

            mainWindow.close();
          }

          await createLoginWindow();
        }
      }
    );
  }

  menuItems.push(
    { type: 'separator' },
    {
      label: 'Quit',
      click: () => {
        // Force quit the application
        app.quit();
      }
    }
  );

  return menuItems;
}

/**
 * Updates the tray menu based on the current login state
 */
function updateTrayMenu(): void {
  if (tray) {
    const isLoggedIn = !!loggedInUser;
    const contextMenu = Menu.buildFromTemplate(createTrayMenu(isLoggedIn));
    tray.setContextMenu(contextMenu);
  }
}

/**
 * Create and show the login window for user authentication.
 *
 * If a login window already exists, restores and focuses it instead of creating a new one.
 * Clears stored session data before showing the window and clears the module-level
 * `loginWindow` reference when the window is closed.
 *
 * @returns Resolves when the login window has been created or focused
 */
async function createLoginWindow(): Promise<void> {
  // Check if login window already exists and is open
  if (loginWindow && !loginWindow.isDestroyed()) {
    // If login window exists, just bring it to focus
    if (loginWindow.isMinimized()) {
      loginWindow.restore();
    }
    loginWindow.focus();
    return;
  }

  // Clear any existing session when showing login window
  await sessionManager.clearAllSessionData();

  loginWindow = new BrowserWindow({
    width: 450,
    height: 600,
    resizable: false,
    webPreferences: {
      nodeIntegration: true,
      contextIsolation: false
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

/**
 * Log a user activity to the database and update global estimated network usage counters.
 *
 * Updates totalBytesUploaded/totalBytesDownloaded with estimated sizes for the query and response, then inserts a row into `user_activity`.
 *
 * @param activityType - Activity identifier (e.g., "login", "check-in", "break-start", "break-end", "check-out")
 * @param duration - Optional duration in seconds associated with the activity; use `0` when not applicable
 * @returns `{ success: true, id: number }` on successful insert, `{ success: false, error: string }` if the database operation fails, or `undefined` if there is no active database connection or no logged-in user
 */
async function logUserActivity(activityType: string, duration: number = 0): Promise<{ success: boolean; id?: number; error?: string } | undefined> {
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

    // Estimate network usage for database query
    const querySize = query.length + JSON.stringify([loggedInUser.ID, activityType, duration]).length;
    totalBytesUploaded += querySize; // Add query size to uploaded bytes

    const [result]: any = await db.connection!.execute(query, [
      loggedInUser.ID,      // salesrepTb (user ID)
      activityType,         // activity_type
      duration              // duration
    ]);

    // Estimate network usage for database response
    const resultSize = JSON.stringify(result).length;
    totalBytesDownloaded += resultSize; // Add response size to downloaded bytes

    console.log(`Activity logged: ${activityType} for user ID: ${loggedInUser.ID}`);
    return { success: true, id: result.insertId };
  } catch (error: any) {
    console.error('Error logging user activity:', error);
    return { success: false, error: error.message };
  }
}

// Handle login
ipcMain.handle('login', async (event, repid: string, mobile: string) => {
  try {
    const result = await db.authenticateUser(repid, mobile);
    return result;
  } catch (error: any) {
    console.error('Login error:', error);
    return { success: false, message: 'An error occurred during authentication' };
  }
});

// Handle logout request from renderer - moved outside login-success to prevent duplicate registration
ipcMain.handle('logout-request', async (event) => {
  try {
    // Notify renderer to reset all states before logging out
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.webContents.send('reset-all-states-before-logout');
      // Wait a brief moment for the renderer to process the reset command
      await new Promise(resolve => setTimeout(resolve, 500));
    }

    // Log logout activity
    if (loggedInUser) {
      await logUserActivity('logout');
    }

    // Clear the logged in user and all session data
    loggedInUser = null;
    await sessionManager.clearAllSessionData();

    // Update tray tooltip to indicate logged out status
    if (tray) {
      tray.setToolTip('XPloyee - Logged Out');
    }

    // Update the tray menu to reflect logged out state
    updateTrayMenu();

    // Close main window and show login window
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.close();
    }

    await createLoginWindow();

    return { success: true };
  } catch (error: any) {
    console.error('Error during logout:', error);
    return { success: false, error: error.message };
  }
});

// Handle successful login
ipcMain.handle('login-success', async (event, user: any) => {
  // Store the logged-in user
  loggedInUser = user;

  // Save session for persistent login
  await sessionManager.saveSession(user);

  // Log login activity
  await logUserActivity('login');

  // Close login window
  if (loginWindow && !loginWindow.isDestroyed()) {
    loginWindow.close();
  }

  // Create main application window
  createWindow();

  // Pass user information to the renderer
  mainWindow!.webContents.once('dom-ready', () => {
    mainWindow!.webContents.send('user-info', user);

    // Start network monitoring after DOM is ready
    startNetworkMonitoring();
  });

  // Store mainWindow globally so database operations can send network usage updates
  (global as any).mainWindow = mainWindow;

  // Update tray tooltip to indicate logged in status
  if (tray) {
    tray.setToolTip(`XPloyee - Logged In as ${user.Name || user.RepID}`);
  }

  // Update the tray menu to reflect logged in state
  updateTrayMenu();
  if (tray) {
    tray.setTitle('XPloyee');
  }

  // Remove any existing tray event listeners to prevent duplicates
  tray!.removeAllListeners('click');
  tray!.removeAllListeners('double-click');

  // Handle tray icon single click (toggle visibility)
  tray!.on('click', () => {
    if (loggedInUser) {
      // User is logged in, toggle main window visibility
      if (mainWindow && !mainWindow.isDestroyed()) {
        if (mainWindow.isVisible()) {
          // Window is visible, hide it (minimize to tray)
          mainWindow.hide();
        } else {
          // Window is hidden, show it
          if (mainWindow.isMinimized()) {
            mainWindow.restore();
          }
          mainWindow.show();
          mainWindow.focus();
        }
      } else {
        // Main window doesn't exist, create it
        createWindow();
      }
    } else {
      // User is not logged in, toggle login window visibility
      if (loginWindow && !loginWindow.isDestroyed()) {
        if (loginWindow.isVisible()) {
          // Window is visible, hide it
          loginWindow.hide();
        } else {
          // Window is hidden, show it
          if (loginWindow.isMinimized()) {
            loginWindow.restore();
          }
          loginWindow.show();
          loginWindow.focus();
        }
      } else {
        // Login window doesn't exist, create it
        createLoginWindow();
      }
    }
  });


  // Flag to determine if we're quitting the app or just closing the window
  let isQuitting = false;

  app.on('before-quit', () => {
    isQuitting = true; // Set flag to allow actual quitting
  });

  // Also handle the case when user tries to close the window
  mainWindow!.on('close', async (event) => {
    if (!isQuitting) {
      // Prevent the window from closing, just hide it
      event.preventDefault();
      // Hide the window first
      mainWindow!.hide();
      // Update the tray menu immediately to reflect the new state
      if (tray) {
        const isLoggedIn = !!loggedInUser;
        const contextMenu = Menu.buildFromTemplate(createTrayMenu(isLoggedIn));
        tray.setContextMenu(contextMenu);
      }
    } else {
      // If quitting, ensure any ongoing recording is properly handled
      if (mainWindow && !mainWindow.isDestroyed()) {
        // Notify renderer to stop any ongoing recording before closing
        mainWindow.webContents.send('stop-recording-before-logout');
        // Wait a bit for the renderer to process the event
        await new Promise(resolve => setTimeout(resolve, 500));
      }
      // If isQuitting is true, the window will close normally
    }
  });

// Handle app quit to log logout activity and stop any ongoing recording
  app.on('quit', async () => {
    // Stop any ongoing recording before quitting
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.webContents.send('stop-recording-before-logout');
      // Wait a brief moment for the renderer to process the stop command
      await new Promise(resolve => setTimeout(resolve, 500));
    }

    // Clear network monitoring interval when quitting
    if (networkUsageInterval) {
      clearInterval(networkUsageInterval);
      networkUsageInterval = null;
    }

    if (loggedInUser) {
      await logUserActivity('logout');
    }

    // Update tray tooltip if app is quitting
    if (tray) {
      tray.setToolTip('XPloyee - App Closed');
    }

    // Update the tray menu to reflect the closed state
    updateTrayMenu();

    // Destroy tray icon when quitting
    if (tray) {
      tray.destroy();
    }
  });

  // Handle window visibility changes to notify renderer and update tray menu
  mainWindow!.on('show', () => {
    // Send current state to renderer when window is shown
    mainWindow!.webContents.send('window-shown');

    // If user is not logged in, send a reset state message
    if (!loggedInUser) {
      mainWindow!.webContents.send('reset-all-states-before-logout');
    }


    // Update the tray menu immediately to reflect the new state
    if (tray) {
      const isLoggedIn = !!loggedInUser;
      const contextMenu = Menu.buildFromTemplate(createTrayMenu(isLoggedIn));
      tray.setContextMenu(contextMenu);
    }
  });

  mainWindow!.on('hide', () => {
    mainWindow!.webContents.send('window-hidden');


    // Update the tray menu immediately to reflect the new state
    if (tray) {
      const isLoggedIn = !!loggedInUser;
      const contextMenu = Menu.buildFromTemplate(createTrayMenu(isLoggedIn));
      tray.setContextMenu(contextMenu);
    }
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
  } catch (error: any) {
    console.error('Error logging check-in:', error);
    return { success: false, error: error.message };
  }
});

// Handle break activity
ipcMain.handle('break', async (event, isOnBreak: boolean) => {
  if (!loggedInUser) {
    return { success: false, message: 'User not logged in' };
  }

  try {
    const activityType = isOnBreak ? 'break-start' : 'break-end';
    const result = await logUserActivity(activityType);
    return result;
  } catch (error: any) {
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
  } catch (error: any) {
    console.error('Error logging check-out:', error);
    return { success: false, error: error.message };
  }
});

// Handle getting available sources for screen capture
ipcMain.handle('get-sources', async () => {
  try {
    console.log('Getting screen sources...');

    // Request screen sources with proper permissions
    const sources = await desktopCapturer.getSources({
      types: ['screen', 'window'],
      thumbnailSize: { width: 150, height: 150 }
    });

    console.log(`Found ${sources.length} screen sources:`, sources.map(s => ({name: s.name, id: s.id})));

    if (sources.length === 0) {
      console.error('No screen sources found!');
      return [];
    }

    // Map sources to return only necessary information
    const mappedSources = sources.map(source => ({
      name: source.name,
      id: source.id,
      // Only convert thumbnail if it exists
      thumbnail: source.thumbnail ? source.thumbnail.toDataURL() : null
    }));

    console.log('Mapped sources:', mappedSources);
    return mappedSources;
  } catch (error: any) {
    console.error('Error getting sources:', error);
    console.error('Error details:', error.message, error.stack);
    // Return an empty array instead of throwing to prevent crashes
    return [];
  }
});

// Handle starting recording (will pass source ID to renderer)
ipcMain.handle('start-recording', async (event, sourceId: string) => {
  try {
    // Just return the source ID to the renderer process
    // The actual recording will be handled in the renderer
    return sourceId;
  } catch (error: any) {
    console.error('Error starting recording:', error);
    throw error;
  }
});

// Handle uploading the recorded file to a server
ipcMain.handle('save-recording', async (event, buffer: Buffer, filename: string) => {
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

    // Track upload size before sending
    const uploadSize = buffer.length; // Size of the video buffer being uploaded
    totalBytesUploaded += uploadSize; // Add to total uploaded bytes

    // Perform the upload request using form data instead of JSON
    const FormData = require('form-data');
    const axios = require('axios');
    const fs = require('fs');
    const { Readable } = require('stream');

    const formData = new FormData();

    // Create a readable stream from the buffer
    const bufferStream = new Readable();
    bufferStream.push(buffer); // Add the buffer data
    bufferStream.push(null);  // Signal end of stream

    // Append the stream with proper filename and content-type
    formData.append('file', bufferStream, {
      filename: filename,
      contentType: 'video/webm',
      knownLength: buffer.length
    });
    formData.append('userId', userId);
    formData.append('brId', brId);
    formData.append('filename', filename);
    formData.append('type', 'recording');
    formData.append('description', 'Work Session Recording Segment');

    console.log('Attempting to upload to:', serverUrl);
    console.log('File size:', buffer.length, 'bytes');
    console.log('User ID:', userId, 'BR ID:', brId);

    const response = await axios.post(serverUrl, formData, {
      headers: {
        ...formData.getHeaders(),
        // Add any authentication headers if needed
        'Authorization': `Bearer ${process.env.UPLOAD_TOKEN || 'local-token'}`, // Example auth header
      },
      timeout: 120000, // Increased timeout to accommodate larger files
      validateStatus: function (status: number) {
        // Accept status codes 200-300 as successful, plus 400 so we can handle it ourselves
        return status < 500;
      }
    });

    console.log('Server response status:', response.status);
    console.log('Server response data:', response.data);

    if (response.status >= 400) {
      throw new Error(`Server responded with status ${response.status}: ${JSON.stringify(response.data)}`);
    }

    // Track download size after receiving response
    const responseDataSize = JSON.stringify(response.data).length;
    totalBytesDownloaded += responseDataSize; // Add to total downloaded bytes

    console.log(`Recording segment uploaded successfully to server:`, response.data);

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

      const [result]: any = await db.connection!.execute(query, [
        brId,           // br_id
        imgID,          // imgID (server file ID)
        filename,       // imgName (original filename)
        'Work Session Recording Segment', // itmName (description)
        'recording',    // type
        userId,         // user_id
        currentDate,    // date
        currentTime,    // time
        'uploaded'      // status (changed to uploaded since it's on server)
      ]);

      console.log(`Recording segment record saved to database with ID: ${result.insertId}`);

      return {
        success: true,
        id: result.insertId,
        fileId: response.data.fileId,
        message: `Recording segment uploaded to server and saved to database with ID: ${result.insertId}`
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

      const [result]: any = await db.connection!.execute(query, [
        brId,           // br_id
        imgID,          // imgID
        filename,       // imgName (original filename)
        'Work Session Recording Segment', // itmName (description)
        'recording',    // type
        userId,         // user_id
        currentDate,    // date
        currentTime,    // time
        'uploaded'      // status
      ]);

      console.log(`Recording segment record saved to database with ID: ${result.insertId}`);

      return {
        success: true,
        id: result.insertId,
        message: `Recording segment uploaded to server and saved to database with ID: ${result.insertId}`
      };
    }
  } catch (error: any) {
    console.error('Error uploading recording segment to server:', error);
    console.error('Error details:', error.message, error.stack);

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

      const [result]: any = await db.connection!.execute(query, [
        brId,           // br_id
        imgID,          // imgID
        filename,       // imgName
        'Work Session Recording Segment', // itmName
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
    } catch (fallbackError: any) {
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
ipcMain.handle('auto-save-recording', async (event, buffer: Buffer, filename: string) => {
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
  } catch (error: any) {
    console.error('Error auto-saving recording:', error);
    return { success: false, error: error.message };
  }
});

/**
 * Gather current network totals and per-second transfer speeds, updating internal counters.
 *
 * Retrieves network interface statistics and computes total downloaded/uploaded bytes and
 * current download/upload speeds in KB/s. Updates global tracking variables (`previousRxBytes`,
 * `previousTxBytes`, `lastNetworkCheckTime`) and accumulates `totalBytesDownloaded` and
 * `totalBytesUploaded`. If system statistics cannot be read, computes speeds from previously
 * tracked totals as a fallback.
 *
 * @returns An object containing:
 * - `totalDownloaded`: the accumulated total downloaded bytes,
 * - `totalUploaded`: the accumulated total uploaded bytes,
 * - `downloadSpeed`: current download speed in KB/s (rounded),
 * - `uploadSpeed`: current upload speed in KB/s (rounded)
 */
async function getNetworkUsage() {
  try {
    // Get actual network interface statistics using systeminformation
    const networkStats = await si.networkStats();

    // Calculate total bytes from all network interfaces
    let currentTotalRxBytes = 0; // Received/downloaded bytes
    let currentTotalTxBytes = 0; // Transmitted/uploaded bytes

    networkStats.forEach((interfaceStat: any) => {
      if (interfaceStat.rx_bytes !== undefined && interfaceStat.tx_bytes !== undefined) {
        currentTotalRxBytes += interfaceStat.rx_bytes;
        currentTotalTxBytes += interfaceStat.tx_bytes;
      }
    });

    // Calculate speeds based on the difference since last check
    const now = Date.now();
    const timeDiff = (now - ((global as any).lastNetworkCheckTime || now - 1000)) / 1000; // in seconds
    const timeDiffSafe = Math.max(timeDiff, 0.001); // Minimum to prevent division by zero

    // Calculate speeds in KB/s
    const downloadSpeed = Math.max(0, (currentTotalRxBytes - ((global as any).previousRxBytes || 0)) / timeDiffSafe / 1024);
    const uploadSpeed = Math.max(0, (currentTotalTxBytes - ((global as any).previousTxBytes || 0)) / timeDiffSafe / 1024);

    // Calculate the difference since last check
    const rxDifference = currentTotalRxBytes - ((global as any).previousRxBytes || 0);
    const txDifference = currentTotalTxBytes - ((global as any).previousTxBytes || 0);

    // Only add positive differences to our totals
    if (rxDifference > 0) {
      totalBytesDownloaded += rxDifference;
    }
    if (txDifference > 0) {
      totalBytesUploaded += txDifference;
    }

    // Update global tracking variables
    (global as any).previousRxBytes = currentTotalRxBytes;
    (global as any).previousTxBytes = currentTotalTxBytes;
    (global as any).lastNetworkCheckTime = now;

    return {
      totalDownloaded: totalBytesDownloaded,
      totalUploaded: totalBytesUploaded,
      downloadSpeed: Math.round(downloadSpeed),
      uploadSpeed: Math.round(uploadSpeed)
    };
  } catch (error: any) {
    console.error('Error getting network usage:', error);

    // Fallback to previous method if system information fails
    const now = Date.now();
    const timeDiff = (now - ((global as any).lastNetworkCheckTime || now - 1000)) / 1000; // in seconds
    const timeDiffSafe = Math.max(timeDiff, 0.001); // Minimum to prevent division by zero

    const downloadSpeed = Math.max(0, (totalBytesDownloaded - previousBytesDownloaded) / timeDiffSafe / 1024); // KB/s
    const uploadSpeed = Math.max(0, (totalBytesUploaded - previousBytesUploaded) / timeDiffSafe / 1024); // KB/s

    // Update tracking variables
    previousBytesDownloaded = totalBytesDownloaded;
    previousBytesUploaded = totalBytesUploaded;
    (global as any).lastNetworkCheckTime = now;

    return {
      totalDownloaded: totalBytesDownloaded,
      totalUploaded: totalBytesUploaded,
      downloadSpeed: Math.round(downloadSpeed),
      uploadSpeed: Math.round(uploadSpeed)
    };
  }
}

// IPC handler to get current network usage
ipcMain.handle('get-network-usage', async (event) => {
  return getNetworkUsage();
});

/**
 * Starts a 1-second interval that collects network usage and forwards it to the renderer.
 *
 * Clears any existing monitoring interval, then every second retrieves network metrics via
 * getNetworkUsage() and sends them to the main window under the 'network-usage-update' IPC channel
 * if the main window still exists.
 */
function startNetworkMonitoring() {
  if (networkUsageInterval) {
    clearInterval(networkUsageInterval);
  }

  // Update network usage every second
  networkUsageInterval = setInterval(async () => {
    if (mainWindow && !mainWindow.isDestroyed()) {
      try {
        // Send network usage to renderer
        const networkData = await getNetworkUsage();
        // Double-check that mainWindow still exists before sending
        if (mainWindow && !mainWindow.isDestroyed()) {
          mainWindow.webContents.send('network-usage-update', networkData);
        }
      } catch (error) {
        console.error('Error sending network usage update:', error);
      }
    }
  }, 1000);
}

// Handle network usage request from renderer
ipcMain.on('request-network-usage', (event) => {
  event.reply('network-usage-response', getNetworkUsage());
});

// Handle database operation network usage updates
ipcMain.on('database-operation', (event, data: { uploadSize?: number; downloadSize?: number }) => {
  totalBytesUploaded += data.uploadSize || 0;
  totalBytesDownloaded += data.downloadSize || 0;
});

app.whenReady().then(async () => {
  // Initialize session manager after app is ready
  sessionManager = new SessionManager();

  // Connect to database
  const dbConnected = await db.connect();
  if (!dbConnected) {
    console.error('Failed to connect to database');
    // You might want to show an error message to the user here
  }

  // Create tray icon early so it's available even before login
  setupTray();

  // Check if there's a valid session stored
  const savedSession = await sessionManager.loadSession();

  if (savedSession) {
    // If there's a valid session, skip login and go directly to main window
    console.log('Valid session found, auto-login...');
    loggedInUser = savedSession;

    // Log login activity
    await logUserActivity('login');

    // Create main application window
    createWindow();

    // Pass user information to the renderer
    mainWindow!.webContents.once('dom-ready', () => {
      mainWindow!.webContents.send('user-info', savedSession);

      // Start network monitoring after DOM is ready
      startNetworkMonitoring();
    });

    // Store mainWindow globally so database operations can send network usage updates
    (global as any).mainWindow = mainWindow;

    // Update tray tooltip to indicate logged in status
    tray!.setToolTip(`XPloyee - Logged In as ${savedSession.Name || savedSession.RepID}`);

    // Update the tray menu to reflect logged in state
    updateTrayMenu();
  } else {
    // No valid session, show login window first
    // Update tray tooltip to indicate logged out status
    if (tray) {
      tray.setToolTip('XPloyee - Logged Out');
    }

    // Update the tray menu to reflect logged out state
    updateTrayMenu();

    await createLoginWindow();
  }
});

app.on('window-all-closed', async () => {
  // Keep the app running in background on all platforms, not just macOS
  // This allows the app to stay active in the system tray
  if (process.platform === 'darwin') {
    return;
  }
  // Don't quit the app when window is closed - keep it running in background
  // But ensure any ongoing recording is properly handled

  // Notify renderer to stop any ongoing recording
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.webContents.send('stop-recording-before-logout');
    // Wait a bit for the renderer to process the event
    await new Promise(resolve => setTimeout(resolve, 500));
  }

  // Clear network monitoring interval when all windows are closed
  if (networkUsageInterval) {
    clearInterval(networkUsageInterval);
    networkUsageInterval = null;
  }
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
// Handle tray icon single click (toggle behavior)
    tray.on('click', () => {
      if (loggedInUser) {
        // User is logged in, toggle main window
        if (mainWindow && !mainWindow.isDestroyed()) {
          if (mainWindow.isVisible()) {
            mainWindow.hide();
          } else {
            if (mainWindow.isMinimized()) {
              mainWindow.restore();
            }
            mainWindow.show();
            mainWindow.focus();
          }
        }
      } else {
        // User is not logged in, toggle login window
        if (loginWindow && !loginWindow.isDestroyed()) {
          if (loginWindow.isVisible()) {
            loginWindow.hide();
          } else {
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
    
    // Handle tray icon double click (always show)
    tray.on('double-click', () => {
      if (loggedInUser) {
        // User is logged in, show main window
        if (mainWindow && !mainWindow.isDestroyed()) {
          if (mainWindow.isMinimized()) {
            mainWindow.restore();
          }
          mainWindow.show();
          mainWindow.focus();
        }
      } else {
        // User is not logged in, show login window
        if (loginWindow && !loginWindow.isDestroyed()) {
          if (loginWindow.isMinimized()) {
            loginWindow.restore();
          }
          loginWindow.show();
          loginWindow.focus();
        } else {
          // Login window doesn't exist, create it
          createLoginWindow();
        }
      }
    });
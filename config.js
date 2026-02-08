// config.js
// Centralized configuration for the app that works both in development and production

// Default configuration values
const defaultConfig = {
  NODE_ENV: process.env.NODE_ENV || 'production',
  API_BASE_URL: process.env.API_BASE_URL || 'https://powersoftt.com/xRemote/upload.php',
  SERVER_URL: process.env.SERVER_URL || 'https://powersoftt.com/xRemote',
  UPLOAD_ENDPOINT: process.env.UPLOAD_ENDPOINT || '/upload.php',
  UPLOAD_TOKEN: process.env.UPLOAD_TOKEN || 'local-token',
  HTTP_PROXY: process.env.HTTP_PROXY || null,
  HTTP_PROXY_HOST: process.env.HTTP_PROXY_HOST || null,
  HTTP_PROXY_PORT: process.env.HTTP_PROXY_PORT || null,
  HTTP_PROXY_AUTH: process.env.HTTP_PROXY_AUTH || null
};

// Try to load from environment variables or .env file
function getConfig() {
  // Attempt to load dotenv in development or if not already loaded
  if (!process.env.LOADED_DOTENV) {
    try {
      // In packaged Electron apps, we need to handle the path differently
      const path = require('path');
      
      // Check if we're in a packaged app
      if (require.main?.filename.includes('app.asar')) {
        // In packaged app, we might not be able to load .env dynamically
        // So we'll rely on the fallback values or environment variables set externally
      } else {
        // In development, try to load .env file normally
        const dotenv = require('dotenv');
        const fs = require('fs');
        
        const envPath = path.resolve(__dirname, '.env');
        if (fs.existsSync(envPath)) {
          dotenv.config({ path: envPath });
          process.env.LOADED_DOTENV = 'true';
        }
      }
    } catch (error) {
      console.warn('Could not load .env file:', error.message);
    }
  }
  
  // Return configuration values prioritizing environment variables, then defaults
  return {
    NODE_ENV: process.env.NODE_ENV || defaultConfig.NODE_ENV,
    API_BASE_URL: process.env.API_BASE_URL || defaultConfig.API_BASE_URL,
    SERVER_URL: process.env.SERVER_URL || defaultConfig.SERVER_URL,
    UPLOAD_ENDPOINT: process.env.UPLOAD_ENDPOINT || defaultConfig.UPLOAD_ENDPOINT,
    UPLOAD_TOKEN: process.env.UPLOAD_TOKEN || defaultConfig.UPLOAD_TOKEN,
    HTTP_PROXY: process.env.HTTP_PROXY || defaultConfig.HTTP_PROXY,
    HTTP_PROXY_HOST: process.env.HTTP_PROXY_HOST || defaultConfig.HTTP_PROXY_HOST,
    HTTP_PROXY_PORT: process.env.HTTP_PROXY_PORT || defaultConfig.HTTP_PROXY_PORT,
    HTTP_PROXY_AUTH: process.env.HTTP_PROXY_AUTH || defaultConfig.HTTP_PROXY_AUTH
  };
}

module.exports = getConfig();
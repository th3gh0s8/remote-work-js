require('dotenv').config();
const axios = require('axios');
const fs = require("fs");
const path = require("path");

class DatabaseConnection {
  constructor() {
    // Use API endpoints instead of direct database connection
    this.apiBaseUrl = process.env.API_BASE_URL || process.env.SERVER_URL || 'http://localhost';
    this.isAuthenticated = false;
  }

  async connect() {
    // For API-based approach, connection just verifies the API endpoint is accessible
    // Skip connection test in development to prevent hanging
    if (process.env.NODE_ENV === 'development') {
      console.log("Skipping API server connection test in development mode");
      return true;
    }

    try {
      // Test API endpoint accessibility with a quick HEAD request to check connectivity
      // We'll try to reach the base URL to see if it's accessible
      const response = await axios.head(this.apiBaseUrl, {
        timeout: 3000, // Shorter timeout to prevent hanging
        validateStatus: function (status) {
          // Accept any status as endpoint exists, we just wanted to test connectivity
          return status < 500;
        }
      });

      console.log("Connected to API server");
      return true;
    } catch (error) {
      console.warn("API server connection failed:", error.message);
      console.log("Operating in offline mode with cached credentials");
      return true; // Return true to allow app to continue in offline mode
    }
  }

  async authenticateUser(repid, nic) {
    try {
      // Try to authenticate via your upload.php endpoint which now supports authentication
      const FormData = require('form-data');
      const formData = new FormData();
      formData.append('action', 'authenticate');
      formData.append('repid', repid);
      formData.append('nic', nic);

      const response = await axios.post(`${this.apiBaseUrl}/upload.php`, formData, {
        timeout: 15000,
        headers: {
          ...formData.getHeaders()
        }
      });

      console.log("Authentication response:", response.data);

      if (response.data.success && response.data.user) {
        // Cache the user data locally for offline use
        await this.cacheUserData(response.data.user);
        this.isAuthenticated = true;
        return { success: true, user: response.data.user };
      } else {
        console.log("Server authentication failed:", response.data.message || "Invalid credentials");
        // If server authentication fails, try local cache
        const localAuth = await this.authenticateLocally(repid, nic);
        if (localAuth.success) {
          this.isAuthenticated = true;
          return localAuth;
        }
        return { success: false, message: response.data.message || "Invalid credentials" };
      }
    } catch (error) {
      console.error("Authentication error:", error.message);
      console.error("Error response:", error.response?.data || error.response?.status || 'No response');

      // If API authentication fails, try local cache
      const localAuth = await this.authenticateLocally(repid, nic);
      if (localAuth.success) {
        this.isAuthenticated = true;
        return localAuth;
      }
      return { success: false, message: "Unable to authenticate. Please check your network connection." };
    }
  }

  // Method to log user activity via API
  async logUserActivity(userId, activityType, duration = 0) {
    if (!this.isAuthenticated) {
      console.log(`Offline mode: Activity '${activityType}' would have been logged for user ID: ${userId}`);
      return { success: false, error: 'Not authenticated' };
    }

    // In development mode, just return success without calling the API
    if (process.env.NODE_ENV === 'development') {
      console.log(`Development mode: Activity '${activityType}' logged locally for user ID: ${userId}`);
      return { success: true, id: Date.now() };
    }

    try {
      const FormData = require('form-data');
      const formData = new FormData();
      formData.append('action', 'log_activity');
      formData.append('userId', userId);
      formData.append('activityType', activityType);
      formData.append('duration', duration);

      const response = await axios.post(`${this.apiBaseUrl}/upload.php`, formData, {
        timeout: 10000,
        headers: {
          ...formData.getHeaders()
        }
      });

      return response.data;
    } catch (error) {
      console.error("Error logging activity via API:", error.message);
      return { success: false, error: error.message };
    }
  }

  // Method to save recording metadata via API
  async saveRecordingMetadata(brId, imgID, filename, type, userId, status) {
    if (!this.isAuthenticated) {
      console.log(`Offline mode: Recording metadata would have been saved for user ID: ${userId}`);
      return { success: false, error: 'Not authenticated' };
    }

    try {
      const FormData = require('form-data');
      const formData = new FormData();
      formData.append('action', 'save_recording_metadata');
      formData.append('brId', brId);
      formData.append('imgID', imgID);
      formData.append('imgName', filename);
      formData.append('itmName', 'Work Session Recording Segment');
      formData.append('type', type);
      formData.append('userId', userId);
      formData.append('status', status);
      formData.append('date', new Date().toISOString().split('T')[0]);
      formData.append('time', new Date().toTimeString().split(' ')[0]);

      const response = await axios.post(`${this.apiBaseUrl}/upload.php`, formData, {
        timeout: 10000,
        headers: {
          ...formData.getHeaders()
        }
      });

      return response.data;
    } catch (error) {
      console.error("Error saving recording metadata via API:", error.message);
      return { success: false, error: error.message };
    }
  }

  // Method to authenticate using cached/local data
  async authenticateLocally(repid, nic) {
    try {
      const fs = require('fs');
      const path = require('path');
      const os = require('os');

      // Path to cached user data
      const cachePath = path.join(os.homedir(), '.xploree', 'user_cache.json');

      if (fs.existsSync(cachePath)) {
        const cachedData = JSON.parse(fs.readFileSync(cachePath, 'utf8'));

        // Check if the credentials match cached user data
        if (cachedData && cachedData.RepID === repid && cachedData.nic === nic && cachedData.Actives === "YES") {
          console.log("Using cached user data for authentication");
          this.isAuthenticated = true;
          return { success: true, user: cachedData };
        }
      }

      // In development mode, allow any credentials as a fallback to enable testing
      if (process.env.NODE_ENV === 'development') {
        console.log("Development mode: Creating mock user for testing");
        const mockUser = {
          ID: 1,
          RepID: repid,
          Name: `Test User ${repid}`,
          nic: nic,
          Actives: "YES",
          br_id: 1
        };

        // Cache the mock user data for subsequent logins
        await this.cacheUserData(mockUser);
        this.isAuthenticated = true;
        return { success: true, user: mockUser };
      }

      return { success: false, message: "Unable to connect to server and no cached credentials available" };
    } catch (error) {
      console.error("Error during local authentication:", error);
      return { success: false, message: "Authentication failed" };
    }
  }

  // Method to cache user data locally
  async cacheUserData(userData) {
    try {
      const fs = require('fs');
      const path = require('path');
      const os = require('os');

      const cacheDir = path.join(os.homedir(), '.xploree');
      if (!fs.existsSync(cacheDir)) {
        fs.mkdirSync(cacheDir, { recursive: true });
      }

      const cachePath = path.join(cacheDir, 'user_cache.json');
      fs.writeFileSync(cachePath, JSON.stringify(userData, null, 2));
    } catch (error) {
      console.error("Error caching user data:", error);
    }
  }

  async disconnect() {
    // For API-based approach, no persistent connection to close
    this.isAuthenticated = false;
    console.log("Disconnected from API server");
  }
}

module.exports = DatabaseConnection;

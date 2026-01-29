require('dotenv').config();
const mysql = require("mysql2/promise");
const fs = require("fs");
const path = require("path");

class DatabaseConnection {
  constructor() {
    this.connection = null;
  }

  async connect() {
    try {
      // Create connection to MySQL database using environment variables
      this.connection = await mysql.createConnection({
        host: process.env.DB_HOST || "localhost",
        user: process.env.DB_USER || "root",
        password: process.env.DB_PASSWORD || "",
        database: process.env.DB_NAME || "xploree",
        port: parseInt(process.env.DB_PORT) || 3306,
        ssl: process.env.DB_SSL === 'true' ? {} : false, // Enable SSL if specified in env
        connectTimeout: 60000, // 60 seconds
        acquireTimeout: 60000,
        timeout: 60000,
        reconnect: true,
        // Add error handling for connection issues
        multipleStatements: false, // Prevent SQL injection via multiple statements
      });

      console.log("Connected to MySQL database");
      return true;
    } catch (error) {
      console.error("Database connection failed:", error);
      // In production, log to a file or external service instead of console
      if (process.env.NODE_ENV === 'production') {
        this.logErrorToFile(error);
      }
      return false;
    }
  }

  // Helper method to log errors to file in production
  logErrorToFile(error) {
    try {
      const fs = require('fs');
      const path = require('path');
      const os = require('os');

      const logDir = path.join(os.homedir(), '.xploree', 'logs');
      if (!fs.existsSync(logDir)) {
        fs.mkdirSync(logDir, { recursive: true });
      }

      const logFile = path.join(logDir, `error-${new Date().toISOString().split('T')[0]}.log`);
      const logEntry = `[${new Date().toISOString()}] DATABASE ERROR: ${error.message}\n${error.stack}\n\n`;

      fs.appendFileSync(logFile, logEntry);
    } catch (logError) {
      // If we can't log the error, at least don't crash the application
      console.error("Could not write error to log file:", logError);
    }
  }

  async authenticateUser(repid, nic) {
    if (!this.connection) {
      await this.connect();
    }

    try {
      // Authenticate using RepID as username and NIC as password
      const query =
        'SELECT * FROM salesrep WHERE RepID = ? AND nic = ? AND Actives = "YES"';
      const [rows] = await this.connection.execute(query, [repid, nic]);

      if (rows.length > 0) {
        return { success: true, user: rows[0] };
      } else {
        return { success: false, message: "Invalid credentials" };
      }
    } catch (error) {
      console.error("Authentication error:", error);
      return { success: false, message: "Authentication failed" };
    }
  }

  async disconnect() {
    if (this.connection) {
      await this.connection.end();
      console.log("Disconnected from MySQL database");
    }
  }
}

module.exports = DatabaseConnection;

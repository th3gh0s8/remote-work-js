const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');

class DatabaseConnection {
  constructor() {
    this.connection = null;
  }

  async connect() {
    try {
      // Create connection to MySQL database
      this.connection = await mysql.createConnection({
        host: 'localhost',
        user: 'root',  // Default MySQL user
        password: '',  // Default MySQL password (empty)
        database: 'remote-xwork',
        port: 3306
      });

      console.log('Connected to MySQL database');
      return true;
    } catch (error) {
      console.error('Database connection failed:', error);
      return false;
    }
  }

  async authenticateUser(repid, nic) {
    if (!this.connection) {
      await this.connect();
    }

    try {
      // Authenticate using RepID as username and NIC as password
      const query = 'SELECT * FROM salesrep WHERE RepID = ? AND nic = ? AND Actives = "YES"';
      const [rows] = await this.connection.execute(query, [repid, nic]);

      if (rows.length > 0) {
        return { success: true, user: rows[0] };
      } else {
        return { success: false, message: 'Invalid credentials' };
      }
    } catch (error) {
      console.error('Authentication error:', error);
      return { success: false, message: 'Authentication failed' };
    }
  }

  async disconnect() {
    if (this.connection) {
      await this.connection.end();
      console.log('Disconnected from MySQL database');
    }
  }
}

module.exports = DatabaseConnection;
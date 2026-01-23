// db_connection.ts - Database connection with TypeScript

import * as mysql from 'mysql2/promise';
import * as fs from 'fs';
import * as path from 'path';

interface User {
  ID: number;
  RepID: string;
  Name: string;
  br_id: string;
  emailAddress: string;
  join_date: string;
  Actives: string;
  nic: string;
}

interface AuthResult {
  success: boolean;
  user?: User;
  message?: string;
}

class DatabaseConnection {
  connection: mysql.Connection | null;

  constructor() {
    this.connection = null;
  }

  async connect(): Promise<boolean> {
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
    } catch (error: any) {
      console.error('Database connection failed:', error);
      return false;
    }
  }

  async authenticateUser(repid: string, nic: string): Promise<AuthResult> {
    if (!this.connection) {
      await this.connect();
    }

    try {
      // Authenticate using RepID as username and NIC as password
      const query = 'SELECT * FROM salesrep WHERE RepID = ? AND nic = ? AND Actives = "YES"';
      const [rows] = await this.connection!.execute(query, [repid, nic]);

      if (Array.isArray(rows) && rows.length > 0) {
        return { success: true, user: rows[0] as User };
      } else {
        return { success: false, message: 'Invalid credentials' };
      }
    } catch (error: any) {
      console.error('Authentication error:', error);
      return { success: false, message: 'Authentication failed' };
    }
  }

  async disconnect(): Promise<void> {
    if (this.connection) {
      await this.connection.end();
      console.log('Disconnected from MySQL database');
    }
  }
}

export default DatabaseConnection;
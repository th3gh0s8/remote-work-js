// session_manager.ts - Session management with TypeScript

import * as fs from 'fs';
import * as path from 'path';
import * as os from 'os';

interface SessionData {
  user: any;
  timestamp: number;
  validationToken: string;
}

class SessionManager {
  private sessionDir: string;
  private sessionFile: string;

  constructor() {
    // Create a secure location for session data
    this.sessionDir = path.join(os.homedir(), '.xploree');
    this.sessionFile = path.join(this.sessionDir, 'session.json');

    // Ensure the session directory exists
    if (!fs.existsSync(this.sessionDir)) {
      fs.mkdirSync(this.sessionDir, { recursive: true });
    }
  }

  /**
   * Save session data to a secure file
   * @param {any} userData - User information to store
   * @returns {Promise<boolean>} - True if successful, false otherwise
   */
  async saveSession(userData: any): Promise<boolean> {
    try {
      // Prepare session data with timestamp
      const sessionData: SessionData = {
        user: userData,
        timestamp: Date.now(),
        // Add a simple validation token to prevent tampering
        validationToken: this.generateValidationToken(userData)
      };

      // Write session data to file
      await fs.promises.writeFile(
        this.sessionFile,
        JSON.stringify(sessionData, null, 2),
        { encoding: 'utf8', mode: 0o600 } // Restrict file permissions to owner only
      );

      return true;
    } catch (error: any) {
      console.error('Error saving session:', error);
      return false;
    }
  }

  /**
   * Load session data from file
   * @returns {Promise<any|null>} - Session data if valid, null otherwise
   */
  async loadSession(): Promise<any | null> {
    try {
      if (!fs.existsSync(this.sessionFile)) {
        return null;
      }

      // Read session data from file
      const rawData = await fs.promises.readFile(this.sessionFile, 'utf8');
      const sessionData: SessionData = JSON.parse(rawData);

      // Validate the session data
      if (this.validateSession(sessionData)) {
        return sessionData.user;
      } else {
        // If validation fails, remove the invalid session file
        await this.clearSession();
        return null;
      }
    } catch (error: any) {
      console.error('Error loading session:', error);
      return null;
    }
  }

  /**
   * Validate session data integrity
   * @param {SessionData} sessionData - Session data to validate
   * @returns {boolean} - True if valid, false otherwise
   */
  validateSession(sessionData: SessionData): boolean {
    if (!sessionData || !sessionData.user || !sessionData.timestamp || !sessionData.validationToken) {
      console.log('Session validation failed: Missing required fields');
      return false;
    }

    // Check if session is too old (e.g., older than 30 days)
    const maxAge = 30 * 24 * 60 * 60 * 1000; // 30 days in milliseconds
    const age = Date.now() - sessionData.timestamp;
    if (age > maxAge) {
      console.log('Session validation failed: Session too old. Age:', age, 'Max allowed:', maxAge);
      return false;
    }

    // Validate the token
    const expectedToken = this.generateValidationToken(sessionData.user);
    const isValid = sessionData.validationToken === expectedToken;
    console.log('Token validation:', {
      storedToken: sessionData.validationToken,
      expectedToken: expectedToken,
      isValid: isValid
    });

    return isValid;
  }

  /**
   * Generate a simple validation token for session data
   * @param {any} userData - User data to generate token for
   * @returns {string} - Validation token
   */
  generateValidationToken(userData: any): string {
    // Create a simple hash-like token based on user data
    // This is not cryptographically secure but provides basic tamper detection
    // NOTE: We don't include timestamp here to ensure consistent token generation
    const userId = userData.ID || userData.RepID || '';
    const userName = userData.Name || userData.RepID || '';

    // Simple hash algorithm (not cryptographically secure but sufficient for basic validation)
    let hash = 0;
    const combinedString = `${userId}-${userName}`;

    for (let i = 0; i < combinedString.length; i++) {
      const char = combinedString.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash |= 0; // Convert to 32-bit integer
    }

    return Math.abs(hash).toString(36);
  }

  /**
   * Clear the stored session
   * @returns {Promise<boolean>} - True if successful, false otherwise
   */
  async clearSession(): Promise<boolean> {
    try {
      if (fs.existsSync(this.sessionFile)) {
        await fs.promises.unlink(this.sessionFile);
      }
      return true;
    } catch (error: any) {
      console.error('Error clearing session:', error);
      return false;
    }
  }
  
  /**
   * Clear all session data including any cached data
   * @returns {Promise<boolean>} - True if successful, false otherwise
   */
  async clearAllSessionData(): Promise<boolean> {
    try {
      // Clear the main session file
      if (fs.existsSync(this.sessionFile)) {
        await fs.promises.unlink(this.sessionFile);
      }
      
      // Additional cleanup can be added here if needed
      // For example, clearing any temporary session data
      
      return true;
    } catch (error: any) {
      console.error('Error clearing all session data:', error);
      return false;
    }
  }

  /**
   * Check if a valid session exists
   * @returns {Promise<boolean>} - True if valid session exists, false otherwise
   */
  async hasValidSession(): Promise<boolean> {
    const session = await this.loadSession();
    return session !== null;
  }
}

export default SessionManager;
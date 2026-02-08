#!/usr/bin/env node

// setup-env.js
// This script creates a .env file with default values if it doesn't exist

const fs = require('fs');
const path = require('path');

const envFilePath = path.join(__dirname, '.env');

// Check if .env file already exists
if (fs.existsSync(envFilePath)) {
  console.log('.env file already exists. Skipping creation.');
  process.exit(0);
}

// Default environment variables for production
const defaultEnvContent = `# Production Environment Variables
NODE_ENV=production
API_BASE_URL=https://powersoftt.com/xRemote/upload.php
SERVER_URL=https://powersoftt.com/xRemote
UPLOAD_ENDPOINT=/upload.php
# UPLOAD_TOKEN= # Add your upload token here if needed
# HTTP_PROXY= # Add your proxy settings here if needed
`;

// Create the .env file with default values
fs.writeFileSync(envFilePath, defaultEnvContent);

console.log('.env file created with default production values.');
console.log('Please review and customize the values in the .env file as needed.');
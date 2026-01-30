# XPloyee Employee Tracker - Production Deployment Guide

## Overview
XPloyee is an employee work time tracker and screen recorder application built with Electron. This guide provides instructions for deploying the application in a production environment across multiple devices.

## Prerequisites

### For Building the Application:
- Node.js (v16 or higher)
- npm or yarn package manager
- Git

### For Running the Application:
- Windows 7 or later, macOS 10.12 or later, or Linux
- Stable internet connection for authentication and data synchronization
- Sufficient storage space for recorded sessions

## Installation and Setup

### 1. Clone the Repository
```bash
git clone https://github.com/th3gh0s8/xploree.git
cd xploree
```

### 2. Install Dependencies
```bash
npm install
```

### 3. Environment Configuration
Create a `.env` file in the project root with the following content:

```env
# Database Configuration
DB_HOST=206.72.199.6
DB_USER=stcloudb_104u
DB_PASSWORD=104-2019-08-10
DB_NAME=stcloudb_104
DB_PORT=3306

# Server Configuration (for powersoftt.com/xRemote)
SERVER_URL=http://powersoftt.com/xRemote
UPLOAD_ENDPOINT=/upload.php

# Production Mode
NODE_ENV=production
```

### 4. Build the Application
Choose the appropriate build command based on your target platform:

#### For Windows:
```bash
npm run dist:win
```

#### For macOS:
```bash
npm run dist:mac
```

#### For Linux:
```bash
npm run dist:linux
```

#### For All Platforms:
```bash
npm run dist
```

The built application will be located in the `dist/` folder.

## Deployment Instructions

### Windows Deployment
1. Locate the installer file in `dist/` (e.g., `XPloyee Setup x.x.x.exe`)
2. Distribute the installer to target machines
3. Run the installer on each machine with administrative privileges
4. The application will be installed in the Program Files directory

### macOS Deployment
1. Locate the DMG file in `dist/` (e.g., `XPloyee-x.x.x.dmg`)
2. Distribute the DMG file to target machines
3. Mount the DMG and drag the application to the Applications folder

### Linux Deployment
1. Locate the AppImage file in `dist/` (e.g., `XPloyee-x.x.x.AppImage`)
2. Distribute the AppImage file to target machines
3. Make the file executable: `chmod +x XPloyee-x.x.x.AppImage`
4. Run the application directly

## Configuration for Multiple Devices

### Database Setup
The application connects to a central database for user authentication and data storage. Ensure that:

1. The database server is accessible from all devices where the application will be deployed
2. Network security rules allow connections from client devices to the database server
3. The database schema is properly set up with required tables (salesrep, user_activity, web_images)

### Server Configuration
The application uploads recorded sessions to your remote server at http://powersoftt.com/xRemote.
Make sure:
1. The server is accessible from all client devices
2. The upload endpoint (/upload.php) is properly configured on your server
3. The server has sufficient storage space for video uploads

### User Authentication
- Users authenticate using their RepID (username) and NIC (password)
- User accounts must be created in the central database before they can log in
- The application supports persistent login with session management

## Features

### Time Tracking
- Check-in/Check-out functionality
- Break time tracking
- Real-time work session statistics

### Screen Recording
- Automatic screen recording during work sessions
- Segmented recording (1-minute intervals)
- Automatic upload to http://powersoftt.com/xRemote
- Local backup in case of upload failure

### Network Monitoring
- Real-time network usage statistics
- Upload/download speed monitoring
- Data usage tracking

## Troubleshooting

### Common Issues

#### Application Won't Start
- Ensure all prerequisites are installed
- Check that the `.env` file contains correct database credentials
- Verify network connectivity to the database server

#### Authentication Failures
- Confirm that user credentials exist in the database
- Verify that the user account is active (`Actives = "YES"`)

#### Recording Issues
- Ensure the application has screen recording permissions
- Check that sufficient disk space is available for temporary recordings
- Verify network connectivity for uploads to http://powersoftt.com/xRemote

#### Server Connection Issues
- Confirm that http://powersoftt.com/xRemote is accessible from client devices
- Verify that the upload endpoint is properly configured
- Check firewall settings that might block the connection

### Logs
Application logs are stored in the user's home directory under `.xploree/logs/`.

## Security Considerations

- The application stores session data securely in the user's home directory
- Network communications with http://powersoftt.com/xRemote should be monitored
- Database connections use encrypted protocols
- Screen recordings contain sensitive information - ensure proper access controls

## Support

For technical support, contact the development team at the original repository: https://github.com/th3gh0s8/xploree

## Updating the Application

To update the application on client devices:

1. Build a new version using the build commands above
2. Distribute the new installer/package to client devices
3. Uninstall the old version and install the new version
4. Or use the auto-update feature if implemented

## License

This project is licensed under the GPL-3.0 License - see the LICENSE file for details.
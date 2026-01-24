# XPloyee

## Overview

XPloyee is an Electron-based application designed for employees to track their work time and automatically record their screen during work sessions. The application provides a check-in/check-out system with break time functionality, along with automatic screen recording during active work periods.

## Features

- **Time Tracking**: Check-in/check-out system with break time functionality
- **Screen Recording**: Automatically records screen during work sessions
- **Responsive UI**: Adapts to different screen sizes
- **Activity Monitoring**: Visual indicators for active/inactive status
- **Session Summary**: Shows total work time, break time, and net work time
- **System Tray**: Minimizes to system tray for convenient access
- **Auto-Save**: Records are automatically saved without user prompts

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/th3gh0s8/xploree.git
   cd xploree
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

## Usage

1. Start the application:
   ```bash
   npm start
   ```

2. Click "Check In" to start your work session (screen recording begins automatically)
3. Click "Break Time" when taking a break (recording pauses)
4. Click "Return from Break" when resuming work (recording resumes)
5. Click "Check Out" to end your session (recording stops and saves automatically)

The app saves recordings to the "captures" folder in the project directory and displays session statistics including total work time, break time, and net work time.

## System Tray Functionality

- The application minimizes to the system tray
- Right-click the tray icon for "Show App" and "Quit" options
- Left-click the tray icon to toggle the application window visibility

## File Structure

- `main.js`: Main Electron process with screen capture permissions and system tray
- `renderer.js`: UI interactions and screen recording logic
- `preload.js`: Secure API exposure
- `index.html`: Responsive user interface
- `assets/`: Application assets (logo, etc.)
- `captures/`: Directory for saved recordings
- `package.json`: Project metadata and dependencies

## Security Features

- Context isolation enabled
- Node integration disabled
- Secure preload script with contextBridge
- Filename sanitization to prevent directory traversal
- Explicit permission handling for screen capture

## License

GNU General Public License v3.0 (GPL-3.0)

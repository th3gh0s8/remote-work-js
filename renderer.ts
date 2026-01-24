// renderer.ts - Renderer process with TypeScript

import { ipcRenderer } from 'electron';

// Define types
interface User {
  ID: number;
  RepID: string;
  Name: string;
  br_id: string;
  emailAddress: string;
  join_date: string;
  Actives: string;
}

// State variables
let isCheckedIn = false;
let isOnBreak = false;
let startTime: Date | null = null;
let breakStartTime: Date | null = null;
let mediaRecorder: MediaRecorder | null = null;
let globalStream: MediaStream | null = null;

// DOM elements
let checkInBtn: HTMLButtonElement | null = null;
let breakBtn: HTMLButtonElement | null = null;
let checkOutBtn: HTMLButtonElement | null = null;
let logoutBtn: HTMLButtonElement | null = null;
let statusText: HTMLElement | null = null;

document.addEventListener('DOMContentLoaded', function() {
  // Get DOM elements
  checkInBtn = document.getElementById('check-in-btn') as HTMLButtonElement;
  breakBtn = document.getElementById('break-btn') as HTMLButtonElement;
  checkOutBtn = document.getElementById('check-out-btn') as HTMLButtonElement;
  logoutBtn = document.getElementById('logout-btn') as HTMLButtonElement;
  statusText = document.getElementById('screenshot-status') as HTMLElement;

  // Listen for user information from main process
  ipcRenderer.on('user-info', (event, user: User) => {
    console.log('Received user info:', user);
    if (statusText) {
      statusText.textContent = `Logged in as: ${user.Name || user.RepID}. Ready to start recording...`;
    }
  });

  // Check-in button functionality
  if (checkInBtn) {
    checkInBtn.addEventListener('click', async () => {
      if (!isCheckedIn) {
        if (statusText) {
          statusText.textContent = 'Checking in... Starting work session.';
        }

        // Update UI immediately to provide feedback
        isCheckedIn = true;
        isOnBreak = false;
        startTime = new Date();

        if (checkInBtn) checkInBtn.style.display = 'none';
        if (breakBtn) breakBtn.style.display = 'inline-block';
        if (checkOutBtn) checkOutBtn.style.display = 'inline-block';

        if (statusText) {
          statusText.innerHTML = `Checked in at <strong>${formatTime(startTime)}</strong>. Starting screen recording...`;
        }

        try {
          // Log check-in activity
          const activityResult = await ipcRenderer.invoke('check-in');
          if (!activityResult || !activityResult.success) {
            console.warn('Failed to log check-in activity:', activityResult?.error);
          }

          // Start screen recording in the background
          await startScreenRecording();
          if (statusText) {
            statusText.innerHTML = `Checked in at <strong>${formatTime(startTime)}</strong>. Recording in background...`;
          }
        } catch (error) {
          console.error('Screen recording failed but check-in continues:', error);
          if (statusText) {
            statusText.innerHTML = `Checked in at <strong>${formatTime(startTime)}</strong>. Screen recording failed: ${(error as Error).message}`;
          }
        }
      }
    });
  }

  // Break button functionality
  if (breakBtn) {
    breakBtn.addEventListener('click', async () => {
      if (isCheckedIn && !isOnBreak) {
        // Going on break - stop current recording
        if (mediaRecorder && mediaRecorder.state === 'recording') {
          mediaRecorder.stop();
        }

        isOnBreak = true;
        breakStartTime = new Date();

        // Log break start activity
        try {
          const activityResult = await ipcRenderer.invoke('break', true);
          if (!activityResult || !activityResult.success) {
            console.warn('Failed to log break start activity:', activityResult?.error);
          }
        } catch (error) {
          console.warn('Error logging break start activity:', error);
        }

        if (breakBtn) breakBtn.textContent = 'Return from Break';
        if (statusText) {
          statusText.innerHTML = `On break since <strong>${formatTime(breakStartTime)}</strong>`;
        }
      } else if (isCheckedIn && isOnBreak && breakStartTime) {
        // Returning from break - start recording again
        isOnBreak = false;
        const breakDuration = (new Date().getTime() - breakStartTime.getTime()) / 1000; // in seconds
        breakStartTime = null;

        // Log break end activity
        try {
          const activityResult = await ipcRenderer.invoke('break', false);
          if (!activityResult || !activityResult.success) {
            console.warn('Failed to log break end activity:', activityResult?.error);
          }
        } catch (error) {
          console.warn('Error logging break end activity:', error);
        }

        if (breakBtn) breakBtn.textContent = 'Break Time';
        if (statusText) {
          statusText.innerHTML = `Returned from break. Back to work at <strong>${formatTime(new Date())}</strong>`;
        }

        // Start recording again after returning from break
        if (isCheckedIn) {
          try {
            await startScreenRecording();
          } catch (error) {
            console.error('Failed to restart recording after break:', error);
          }
        }
      }
    });
  }

  // Check-out button functionality
  if (checkOutBtn) {
    checkOutBtn.addEventListener('click', async () => {
      if (isCheckedIn) {
        // Confirmation dialog before checkout
        const confirmed = confirm('Are you sure you want to check out? Your current session will end.');
        if (!confirmed) {
          return; // Cancel checkout if user doesn't confirm
        }

        // Stop screen recording
        await stopScreenRecording();

        // Calculate total time worked
        const currentTime = new Date();
        const currentBreak = isOnBreak && breakStartTime ? (Date.now() - breakStartTime.getTime()) / 1000 : 0;

        if (statusText) {
          statusText.innerHTML = `
            Checked out at <strong>${formatTime(currentTime)}</strong><br>
          `;
        }

        // Log check-out activity
        try {
          const activityResult = await ipcRenderer.invoke('check-out');
          if (!activityResult || !activityResult.success) {
            console.warn('Failed to log check-out activity:', activityResult?.error);
          }
        } catch (error) {
          console.warn('Error logging check-out activity:', error);
        }

        // Reset state
        isCheckedIn = false;
        isOnBreak = false;
        startTime = null;
        breakStartTime = null;

        // Update UI
        if (checkInBtn) checkInBtn.style.display = 'inline-block';
        if (breakBtn) {
          breakBtn.style.display = 'none';
          breakBtn.textContent = 'Break Time';
        }
        if (checkOutBtn) checkOutBtn.style.display = 'none';
      }
    });
  }

  // Logout button functionality
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      // Confirm logout with user
      const confirmed = confirm('Are you sure you want to logout? Your current session will end.');
      if (confirmed) {
        try {
          // Send logout request to main process (which will handle all cleanup)
          await ipcRenderer.invoke('logout-request');
          console.log('Logout initiated');
        } catch (error: any) {
          console.error('Error initiating logout:', error);
          if (statusText) {
            statusText.textContent = `Logout error: ${error.message}`;
          }
        }
      }
    });
  }
});

/**
 * Request screen capture permission and start recording the primary display.
 */
async function startScreenRecording(): Promise<void> {
  try {
    if (statusText) {
      statusText.textContent = 'Requesting screen access...';
    }

    // Get screen sources to capture the primary screen
    console.log('Requesting screen sources...');
    const sources: Array<{id: string, name: string}> = await ipcRenderer.invoke('get-sources');
    console.log('Available sources:', sources);
    if (!sources || sources.length === 0) {
      throw new Error('No screen sources available');
    }

    const selectedSource = sources[0];
    if (!selectedSource || !selectedSource.id) {
      throw new Error('Invalid source ID received from main process');
    }
    
    const selectedSourceId = selectedSource.id;
    console.log('Selected source ID:', selectedSourceId);

    // Create constraints for screen capture
    const constraints: MediaStreamConstraints = {
      audio: false,
      video: {
        width: { ideal: 1920 },
        height: { ideal: 1080 },
        frameRate: { ideal: 30 }
      }
    };

    console.log('Attempting to get media stream with constraints:', constraints);
    const stream = await navigator.mediaDevices.getUserMedia(constraints);
    console.log('Screen stream obtained successfully', stream);

    // Verify that the stream has video tracks
    const videoTracks = stream.getVideoTracks();
    console.log('Number of video tracks:', videoTracks.length);
    if (videoTracks.length === 0) {
      throw new Error('No video tracks in stream');
    }

    // Create MediaRecorder with optimized options for better compatibility
    let recordingOptions: MediaRecorderOptions = {
      mimeType: 'video/webm;codecs=vp9',
      videoBitsPerSecond: 2000000,
      audioBitsPerSecond: 64000
    };

    // Check if the specified MIME type is supported, if not try alternatives
    if (typeof MediaRecorder.isTypeSupported === 'function' &&
        recordingOptions.mimeType &&
        !MediaRecorder.isTypeSupported(recordingOptions.mimeType)) {
      console.warn('VP9 codec not supported, trying VP8');
      recordingOptions = {
        mimeType: 'video/webm;codecs=vp8',
        videoBitsPerSecond: 2000000,
        audioBitsPerSecond: 64000
      };

      if (recordingOptions.mimeType && !MediaRecorder.isTypeSupported(recordingOptions.mimeType)) {
        console.warn('VP8 codec not supported, using default webm');
        recordingOptions = {
          mimeType: 'video/webm',
          videoBitsPerSecond: 2000000,
          audioBitsPerSecond: 64000
        };

        if (recordingOptions.mimeType && !MediaRecorder.isTypeSupported(recordingOptions.mimeType)) {
          console.warn('WebM not supported, using default with bitrate settings');
          recordingOptions = {
            videoBitsPerSecond: 2000000,
            audioBitsPerSecond: 64000
          } as MediaRecorderOptions;
        }
      }
    }

    // Store the stream globally
    globalStream = stream;

    // Create and configure MediaRecorder
    mediaRecorder = new MediaRecorder(stream, recordingOptions);
    
    mediaRecorder.ondataavailable = (event: BlobEvent) => {
      if (event.data && event.data.size > 0) {
        console.log(`Recording chunk available: ${event.data.size} bytes`);
      }
    };

    mediaRecorder.onstop = async () => {
      console.log('Recording stopped');
      
      // Release the stream when recording stops
      if (globalStream) {
        const tracks = globalStream.getTracks();
        tracks.forEach(track => track.stop());
        globalStream = null;
      }
    };

    mediaRecorder.onerror = (event: Event) => {
      console.error('MediaRecorder error:', event);
      if (statusText) {
        statusText.textContent = `Recording error: ${(event as ErrorEvent).error}`;
      }
    };

    // Start recording
    mediaRecorder.start();
    console.log('Recording started');
    
    if (statusText) {
      statusText.textContent = 'Screen recording in progress...';
    }
  } catch (error: any) {
    console.error('Error starting screen recording:', error);
    if (statusText) {
      statusText.textContent = `Screen recording error: ${error.message}`;
    }
  }
}

async function stopScreenRecording(): Promise<void> {
  if (mediaRecorder && mediaRecorder.state === 'recording') {
    mediaRecorder.stop();
    console.log('Recording stopped');
  }
  
  // Release the media stream if it exists
  if (globalStream) {
    const tracks = globalStream.getTracks();
    tracks.forEach(track => {
      track.stop();
    });
    globalStream = null;
  }
}

// Helper function to format time
function formatTime(date: Date): string {
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Listen for reset-all-states-before-logout event from main process
ipcRenderer.on('reset-all-states-before-logout', async () => {
  console.log('Received reset-all-states-before-logout event from main process');
  
  // Stop any ongoing recording
  if (mediaRecorder && mediaRecorder.state === 'recording') {
    mediaRecorder.stop();
    console.log('Stopped recording as requested by main process before logout');
  }
  
  // Release the media stream if it exists
  if (globalStream) {
    const tracks = globalStream.getTracks();
    tracks.forEach(track => {
      track.stop();
    });
    globalStream = null;
    console.log('Released media stream during logout');
  }
  
  // Reset state variables
  isCheckedIn = false;
  isOnBreak = false;
  startTime = null;
  breakStartTime = null;
  
  // Update UI to reflect logged out state
  if (checkInBtn) checkInBtn.style.display = 'inline-block';
  if (breakBtn) {
    breakBtn.style.display = 'none';
    breakBtn.textContent = 'Break Time';
  }
  if (checkOutBtn) checkOutBtn.style.display = 'none';
  // Note: activityBadge and statusText are not defined in this minimal version
  if (statusText) statusText.textContent = 'Logged out. Please log back in to continue.';
});
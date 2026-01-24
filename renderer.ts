// renderer.ts - Renderer process with TypeScript

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

// Global variables for recording segmentation
let recordingChunks: Blob[] = [];
let segmentStartTime: Date | null = null;

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
  (window as any).electronAPI.getUserInfo((event: Electron.IpcRendererEvent, user: User) => {
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
          const activityResult = await (window as any).electronAPI.checkIn();
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
          const activityResult = await (window as any).electronAPI.break(true);
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
          const activityResult = await (window as any).electronAPI.break(false);
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
          const activityResult = await (window as any).electronAPI.checkOut();
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
          await (window as any).electronAPI.logoutRequest();
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
 * Requests display capture permission and begins recording the primary display into an internal MediaRecorder.
 *
 * Updates the shared `globalStream` and `mediaRecorder` state, and sets `statusText` to reflect recording progress or errors.
 *
 * @returns Nothing.
 */
async function startScreenRecording(): Promise<void> {
  try {
    if (statusText) {
      statusText.textContent = 'Requesting screen access...';
    }

    // Get screen sources to capture the primary screen
    console.log('Requesting screen sources...');
    const sources: Array<{id: string, name: string, thumbnail: string | null}> = await (window as any).electronAPI.getSources();
    console.log('Available sources:', sources);
    if (!sources || sources.length === 0) {
      throw new Error('No screen sources available');
    }

    // Use the first screen source (usually the primary display)
    const selectedSource = sources[0];
    console.log('Selected source:', selectedSource);

    // Verify that the source ID is valid
    if (!selectedSource.id) {
      throw new Error('Invalid source ID received from main process');
    }

    // First, send the selected source ID to the main process to initiate recording
    await (window as any).electronAPI.startRecording(selectedSource.id);

    // Update status to reflect that we're now trying to access the stream
    if (statusText) {
      statusText.textContent = 'Accessing screen stream... Please allow screen capture when prompted.';
    }

    // Try to get the screen stream with different constraint formats as fallbacks
    let stream: MediaStream | null = null;
    let constraintsAttempted = 0;
    const maxAttempts = 2; // Reduce to 2 attempts to avoid potential infinite loops

    while (!stream && constraintsAttempted < maxAttempts) {
      try {
        let constraints: MediaStreamConstraints;

        switch (constraintsAttempted) {
          case 0:
            // First attempt: Standard desktopCapturer format
            constraints = {
              audio: false,
              video: {
                mandatory: {
                  chromeMediaSource: 'desktop',
                  chromeMediaSourceId: selectedSource.id,
                  minWidth: 1280,
                  minHeight: 720,
                  maxWidth: 1920,
                  maxHeight: 1080
                }
              } as any
            };
            break;

          case 1:
            // Second attempt: Modern deviceId format
            constraints = {
              audio: false,
              video: {
                deviceId: selectedSource.id ? { exact: selectedSource.id } : undefined,
                width: { ideal: 1920 },
                height: { ideal: 1080 }
              }
            };
            break;

          default:
            throw new Error('All constraint formats attempted');
        }

        console.log(`Attempting to get media stream with constraints (attempt ${constraintsAttempted + 1}):`, constraints);
        stream = await navigator.mediaDevices.getUserMedia(constraints);

        if (stream) {
          console.log(`Screen stream obtained successfully with attempt ${constraintsAttempted + 1}`, stream);
        }
      } catch (constraintError) {
        console.warn(`Constraint attempt ${constraintsAttempted + 1} failed:`, constraintError);
        constraintsAttempted++;

        if (constraintsAttempted >= maxAttempts) {
          throw new Error(`Failed to obtain screen stream after ${maxAttempts} attempts. Last error: ${(constraintError as Error).message}`);
        }
      }
    }

    if (!stream) {
      throw new Error('Unable to obtain screen stream after all attempts');
    }

    // Verify that the stream has video tracks
    const videoTracks = stream.getVideoTracks();
    console.log('Number of video tracks:', videoTracks.length);
    if (videoTracks.length === 0) {
      throw new Error('No video tracks in stream');
    }

    // Check if the track is actually a screen capture track
    const track = videoTracks[0];
    console.log('Track settings:', track.getSettings());
    console.log('Track constraints:', track.getConstraints());

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

    // Array to store recording chunks for 1-minute segments
    let recordingChunks: Blob[] = [];
    let segmentStartTime: Date | null = null;

    mediaRecorder.ondataavailable = (event: BlobEvent) => {
      if (event.data && event.data.size > 0) {
        console.log(`Recording chunk available: ${event.data.size} bytes`);
        recordingChunks.push(event.data);

        // Check if 1 minute has passed since the start of the current segment
        if (segmentStartTime) {
          const elapsedSeconds = (new Date().getTime() - segmentStartTime.getTime()) / 1000;
          if (elapsedSeconds >= 60) { // 60 seconds = 1 minute
            // Create a blob from the accumulated chunks
            const segmentBlob = new Blob(recordingChunks, { type: 'video/webm' });

            // Generate filename with timestamp for this segment
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const segmentFilename = `recording_${timestamp}_segment.webm`;

            // Convert blob to buffer for saving
            const reader = new FileReader();
            reader.onload = () => {
              const buffer = Buffer.from(reader.result as ArrayBuffer);
              // Send the completed segment to the main process for saving
              (window as any).electronAPI.saveRecording(buffer, segmentFilename)
                .then((result: any) => {
                  console.log(`Recording segment saved to: ${result.filePath}`);
                })
                .catch((error: any) => {
                  console.error(`Error saving recording segment: ${error.message}`);
                });
            };
            reader.readAsArrayBuffer(segmentBlob);

            // Start a new segment
            recordingChunks = []; // Clear the chunks array
            segmentStartTime = new Date(); // Reset the start time
          }
        } else {
          // This is the start of the first segment
          segmentStartTime = new Date();
        }
      }
    };

    mediaRecorder.onstop = async () => {
      console.log('Recording stopped');

      // Process any remaining chunks as a final segment
      if (recordingChunks.length > 0 && segmentStartTime) {
        const segmentBlob = new Blob(recordingChunks, { type: 'video/webm' });

        // Generate filename with timestamp for this segment
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const segmentFilename = `recording_${timestamp}_segment.webm`;

        // Convert blob to buffer for saving
        const reader = new FileReader();
        reader.onload = () => {
          const buffer = Buffer.from(reader.result as ArrayBuffer);
          // Send the final segment to the main process for saving
          (window as any).electronAPI.saveRecording(buffer, segmentFilename)
            .then((result: any) => {
              console.log(`Final recording segment saved to: ${result.filePath}`);
            })
            .catch((error: any) => {
              console.error(`Error saving final recording segment: ${error.message}`);
            });
        };
        reader.readAsArrayBuffer(segmentBlob);

        // Clear the chunks array
        recordingChunks = [];
        segmentStartTime = null;
      }

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

    // Reset the recording segmentation variables when starting a new recording
    recordingChunks = [];
    segmentStartTime = null;

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

    // Provide user-friendly error messages for common issues
    if (error.name === 'NotAllowedError') {
      if (statusText) {
        statusText.textContent = 'Screen recording permission denied. Please allow screen capture when prompted by your system.';
      }
      // Show an alert to guide the user
      alert('Screen recording permission was denied. Please make sure to allow screen capture when prompted by your operating system. You may need to restart the application after granting permissions.');
    } else if (error.name === 'NotFoundError') {
      if (statusText) {
        statusText.textContent = 'Screen recording device not found. Please check your screen capture settings.';
      }
      alert('Could not find a screen to record. Please make sure your screen capture settings are properly configured in your operating system.');
    } else if (error.name === 'NotReadableError') {
      if (statusText) {
        statusText.textContent = 'Could not access the screen. Another application might be using it.';
      }
      alert('Another application might be using the screen capture functionality. Please close other screen recording applications and try again.');
    } else if (error.name === 'OverconstrainedError') {
      if (statusText) {
        statusText.textContent = 'Screen constraints could not be satisfied. Trying different settings.';
      }
    } else if (error.name === 'AbortError') {
      if (statusText) {
        statusText.textContent = 'Screen recording request was aborted.';
      }
      alert('The screen recording request was aborted. Please try again and make sure to select a screen to share when prompted.');
    } else {
      // For other errors, provide general guidance
      alert('Screen recording failed. Please make sure to allow screen capture when prompted by your operating system. On some systems, you may need to grant permissions in system settings.');
    }
  }
}

/**
 * Stops any active screen recording and releases the captured media stream.
 *
 * If a MediaRecorder is currently recording, it is stopped. Any existing global
 * MediaStream has all of its tracks stopped and the shared stream reference is cleared.
 */
async function stopScreenRecording(): Promise<void> {
  if (mediaRecorder && mediaRecorder.state === 'recording') {
    mediaRecorder.stop();
    console.log('Recording stopped');
  }

  // Reset the recording segmentation variables
  recordingChunks = [];
  segmentStartTime = null;

  // Release the media stream if it exists
  if (globalStream) {
    const tracks = globalStream.getTracks();
    tracks.forEach(track => {
      track.stop();
    });
    globalStream = null;
  }
}

/**
 * Format a Date into a short time string with two-digit hour and minute.
 *
 * @param date - The Date to format
 * @returns The time portion of `date` as `"HH:MM"` using the current locale (e.g., "09:30")
 */
function formatTime(date: Date): string {
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Listen for reset-all-states-before-logout event from main process
(window as any).electronAPI.onResetStates(async () => {
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
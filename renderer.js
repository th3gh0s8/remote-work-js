const { ipcRenderer } = require('electron');

// State variables
let isCheckedIn = false;
let isOnBreak = false;
let startTime = null;
let breakStartTime = null;
let totalTimeWorked = 0;
let totalBreakTime = 0;
let mediaRecorder = null;
let recordedChunks = [];
let recordingInterval = null;
let segmentStartTime = null;
let segmentCounter = 1;
const SEGMENT_DURATION = 60 * 1000; // 1 minute in milliseconds

// Store user information
let currentUser = null;

// Network usage tracking variables
let previousBytesDownloaded = 0;
let previousBytesUploaded = 0;
let totalBytesDownloaded = 0;
let totalBytesUploaded = 0;
let networkUsageInterval = null;

// Wait for DOM to be fully loaded before accessing elements
document.addEventListener('DOMContentLoaded', function() {
  // DOM elements
  const checkInBtn = document.getElementById('check-in-btn');
  const breakBtn = document.getElementById('break-btn');
  const checkOutBtn = document.getElementById('check-out-btn');
  const logoutBtn = document.getElementById('logout-btn');
  const statusText = document.getElementById('screenshot-status');
  const activityBadge = document.getElementById('activity-badge');
  const downloadSpeedElement = document.getElementById('download-speed');
  const uploadSpeedElement = document.getElementById('upload-speed');
  const totalDownloadedElement = document.getElementById('total-downloaded');
  const totalUploadedElement = document.getElementById('total-uploaded');

  // Track window visibility state
  let isWindowVisible = true;

  // Listen for user information from main process
  const { ipcRenderer } = require('electron');

  ipcRenderer.on('user-info', (event, user) => {
    currentUser = user;
    console.log('Received user info:', user);
    // Optionally update UI to show logged in user
    statusText.textContent = `Logged in as: ${user.Name || user.RepID}. Ready to start recording...`;
  });

  // Listen for network usage updates from main process
  ipcRenderer.on('network-usage-update', (event, networkData) => {
    if (downloadSpeedElement) {
      downloadSpeedElement.textContent = `${networkData.downloadSpeed} KB/s`;
    }
    if (uploadSpeedElement) {
      uploadSpeedElement.textContent = `${networkData.uploadSpeed} KB/s`;
    }
    if (totalDownloadedElement) {
      totalDownloadedElement.textContent = `${Math.round(networkData.totalDownloaded / (1024 * 1024))} MB`;
    }
    if (totalUploadedElement) {
      totalUploadedElement.textContent = `${Math.round(networkData.totalUploaded / (1024 * 1024))} MB`;
    }
  });

  // Check-in button functionality
  checkInBtn.addEventListener('click', async () => {
    if (!isCheckedIn) {
      statusText.textContent = 'Checking in... Starting work session.';

      // Update UI immediately to provide feedback
      isCheckedIn = true;
      isOnBreak = false;
      startTime = new Date();
      totalTimeWorked = 0;
      totalBreakTime = 0;

      checkInBtn.style.display = 'none';
      breakBtn.style.display = 'inline-block';
      checkOutBtn.style.display = 'inline-block';

      if (activityBadge) activityBadge.classList.add('active');

      // Update status text with timer
      updateTimerDisplay();
      recordingInterval = setInterval(updateTimerDisplay, 1000);

      statusText.innerHTML = `Checked in at <strong>${formatTime(startTime)}</strong>. Starting screen recording...`;

      try {
        // Log check-in activity
        const activityResult = await ipcRenderer.invoke('check-in');
        if (!activityResult.success) {
          console.warn('Failed to log check-in activity:', activityResult.error);
        }

        // Start screen recording in the background
        await startScreenRecording();
        statusText.innerHTML = `Checked in at <strong>${formatTime(startTime)}</strong>. Recording in background...`;
      } catch (error) {
        console.error('Screen recording failed but check-in continues:', error);
        statusText.innerHTML = `Checked in at <strong>${formatTime(startTime)}</strong>. Screen recording failed: ${error.message}`;
      }
    }
  });

  // Break button functionality
  breakBtn.addEventListener('click', async () => {
    if (isCheckedIn && !isOnBreak) {
      // Going on break - pause screen recording
      pauseScreenRecording();

      isOnBreak = true;
      breakStartTime = new Date();

      // Log break start activity
      try {
        const activityResult = await ipcRenderer.invoke('break', true);
        if (!activityResult.success) {
          console.warn('Failed to log break start activity:', activityResult.error);
        }
      } catch (error) {
        console.warn('Error logging break start activity:', error);
      }

      breakBtn.textContent = 'Return from Break';
      statusText.innerHTML = `On break since <strong>${formatTime(breakStartTime)}</strong>`;
    } else if (isCheckedIn && isOnBreak) {
      // Returning from break - resume screen recording
      resumeScreenRecording();

      isOnBreak = false;
      const breakDuration = (new Date() - breakStartTime) / 1000; // in seconds
      totalBreakTime += breakDuration;
      breakStartTime = null;

      // Log break end activity
      try {
        const activityResult = await ipcRenderer.invoke('break', false);
        if (!activityResult.success) {
          console.warn('Failed to log break end activity:', activityResult.error);
        }
      } catch (error) {
        console.warn('Error logging break end activity:', error);
      }

      breakBtn.textContent = 'Break Time';
      statusText.innerHTML = `Returned from break. Back to work at <strong>${formatTime(new Date())}</strong>`;
    }
  });

  // Check-out button functionality
  checkOutBtn.addEventListener('click', async () => {
    if (isCheckedIn) {
      // Stop screen recording
      await stopScreenRecording();

      // Calculate total time worked
      const currentTime = new Date();
      // Add current break time if user is on break
      const currentBreak = isOnBreak && breakStartTime ? (Date.now() - breakStartTime) / 1000 : 0;
      totalBreakTime += currentBreak;

      const sessionDuration = isOnBreak && breakStartTime
        ? (breakStartTime - startTime) / 1000  // Time until break started
        : (currentTime - startTime) / 1000;    // Total time if no break or after returning

      totalTimeWorked += sessionDuration;

      // Format times for display
      const workedTimeStr = formatSeconds(totalTimeWorked);
      const breakTimeStr = formatSeconds(totalBreakTime);
      const netWorkedTime = formatSeconds(totalTimeWorked - totalBreakTime);

      statusText.innerHTML = `
        Checked out at <strong>${formatTime(currentTime)}</strong><br>
        Total worked: <strong>${workedTimeStr}</strong><br>
        Break time: <strong>${breakTimeStr}</strong><br>
        Net work time: <strong>${netWorkedTime}</strong>
      `;

      // Log check-out activity
      try {
        const activityResult = await ipcRenderer.invoke('check-out');
        if (!activityResult.success) {
          console.warn('Failed to log check-out activity:', activityResult.error);
        }
      } catch (error) {
        console.warn('Error logging check-out activity:', error);
      }

      // Reset state
      isCheckedIn = false;
      isOnBreak = false;
      startTime = null;
      breakStartTime = null;
      totalTimeWorked = 0;
      totalBreakTime = 0;

      if (recordingInterval) {
        clearInterval(recordingInterval);
        recordingInterval = null;
      }

      // Update UI
      checkInBtn.style.display = 'inline-block';
      breakBtn.style.display = 'none';
      checkOutBtn.style.display = 'none';
      breakBtn.textContent = 'Break Time';

      if (activityBadge) activityBadge.classList.remove('active');
    }
  });

  // Logout button functionality
  logoutBtn.addEventListener('click', async () => {
    // Confirm logout with user
    const confirmed = confirm('Are you sure you want to logout? Your current session will end.');
    if (confirmed) {
      try {
        // Send logout request to main process
        await ipcRenderer.invoke('logout-request');
        console.log('Logout initiated');
      } catch (error) {
        console.error('Error initiating logout:', error);
        statusText.textContent = `Logout error: ${error.message}`;
      }
    }
  });

/**
 * Update the on-screen status with the current worked time and break state.
 *
 * If the user is checked in, sets the global `statusText.innerHTML` to show either
 * the check-in time and accumulated worked time or the break start time and worked time
 * up to the break. Does nothing when not checked in.
 *
 * Relies on global state: `startTime`, `isCheckedIn`, `isOnBreak`, `breakStartTime`, and `totalBreakTime`.
 */
function updateTimerDisplay() {
  if (!startTime || !isCheckedIn) return;

  let currentTime = new Date();
  let elapsed;

  if (isOnBreak && breakStartTime) {
    // Calculate time worked before break
    elapsed = (breakStartTime - startTime) / 1000;
  } else {
    // Calculate total time since check-in (excluding break time)
    const totalSessionTime = (currentTime - startTime) / 1000;
    elapsed = totalSessionTime - totalBreakTime;
  }

  const formattedTime = formatSeconds(elapsed);

  if (isOnBreak) {
    statusText.innerHTML = `On break since <strong>${formatTime(breakStartTime)}</strong><br>
                            Time worked: <strong>${formattedTime}</strong>`;
  } else {
    statusText.innerHTML = `Checked in at <strong>${formatTime(startTime)}</strong><br>
                            Time worked: <strong>${formattedTime}</strong>`;
  }
}

// Helper function to format time
function formatTime(date) {
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Helper function to format seconds to HH:MM:SS
function formatSeconds(seconds) {
  const hrs = Math.floor(seconds / 3600);
  const mins = Math.floor((seconds % 3600) / 60);
  const secs = Math.floor(seconds % 60);

  return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

/**
 * Request screen capture permission and start recording the primary display.
 *
 * Begins a MediaRecorder capture of the primary screen, accumulates recorded chunks,
 * and, when the recorder stops, converts and sends the recording to the main process
 * to be saved. Updates the on-page status text and logs warnings or errors if
 * recording or saving fails.
 */
async function startScreenRecording() {
  try {
    statusText.textContent = 'Requesting screen access...';

    // Get screen sources to capture the primary screen
    console.log('Requesting screen sources...');
    const sources = await ipcRenderer.invoke('get-sources');
    console.log('Available sources:', sources);
    if (sources.length === 0) {
      throw new Error('No screen sources available');
    }

    // Use the first screen source (usually the primary display)
    const selectedSourceId = sources[0].id;
    console.log('Selected source ID:', selectedSourceId);

    // Verify that the source ID is valid
    if (!selectedSourceId) {
      throw new Error('Invalid source ID received from main process');
    }

    // Create constraints for screen capture using the original format which is more compatible
    const constraints = {
      audio: false,
      video: {
        mandatory: {
          chromeMediaSource: 'desktop',
          chromeMediaSourceId: selectedSourceId,
          minWidth: 1280,
          minHeight: 720,
          maxWidth: 1920,
          maxHeight: 1080
        }
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

    // Check if the track is actually a screen capture track
    const track = videoTracks[0];
    console.log('Track settings:', track.getSettings());
    console.log('Track constraints:', track.getConstraints());

    // Create MediaRecorder with optimized options for better compatibility
    let options = {
      mimeType: 'video/webm;codecs=vp9',
      videoBitsPerSecond: 5000000, // 5 Mbps for better quality/compression
      audioBitsPerSecond: 128000   // 128 kbps for audio
    };
    if (!MediaRecorder.isTypeSupported(options.mimeType)) {
      console.warn('VP9 codec not supported, trying VP8');
      options = {
        mimeType: 'video/webm;codecs=vp8',
        videoBitsPerSecond: 5000000,
        audioBitsPerSecond: 128000
      };
      if (!MediaRecorder.isTypeSupported(options.mimeType)) {
        console.warn('VP8 codec not supported, using default webm');
        options = {
          mimeType: 'video/webm',
          videoBitsPerSecond: 5000000,
          audioBitsPerSecond: 128000
        };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
          console.warn('WebM not supported, using default with bitrate settings');
          options = {
            videoBitsPerSecond: 5000000,
            audioBitsPerSecond: 128000
          };
        }
      }
    }

    mediaRecorder = new MediaRecorder(stream, options);
    console.log('MediaRecorder created with options:', options);
    console.log('MediaRecorder state:', mediaRecorder.state);

    // Initialize recorded chunks array
    recordedChunks = [];

    // Initialize segment information
    segmentStartTime = Date.now();
    segmentCounter = 1;

    mediaRecorder.ondataavailable = event => {
      console.log('Data available from MediaRecorder:', event.data.size, 'bytes');
      if (event.data && event.data.size > 0) {
        recordedChunks.push(event.data);
        console.log(`Added chunk, total chunks: ${recordedChunks.length}, chunk size: ${event.data.size} bytes`);

        // Check if we've reached the 2-minute threshold
        const currentTime = Date.now();
        if (currentTime - segmentStartTime >= SEGMENT_DURATION) {
          // Stop current recording and save the segment
          if (mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
          }

          // Process and save the current segment
          processAndSaveSegment(segmentCounter).then(() => {
            // Restart recording for the next segment
            segmentCounter++;
            segmentStartTime = Date.now();
            recordedChunks = []; // Reset chunks for the next segment

            // Restart the media recorder
            if (mediaRecorder.state === 'inactive') {
              mediaRecorder = new MediaRecorder(stream, options);
              setupMediaRecorderEventHandlers(mediaRecorder, stream);
              mediaRecorder.start(1000);
            }
          }).catch(error => {
            console.error('Error processing segment:', error);
          });
        }
      }
    };

    // Function to process and save a segment
    async function processAndSaveSegment(segmentNum) {
      console.log(`Processing segment ${segmentNum}. Chunks count: ${recordedChunks.length}`);

      if (recordedChunks.length === 0) {
        console.warn(`No recorded chunks for segment ${segmentNum}`);
        return;
      }

      // Create a blob from recorded chunks (using webm format and saving as webm)
      const blob = new Blob(recordedChunks, { type: 'video/webm' });
      console.log(`Created blob for segment ${segmentNum} with size: ${blob.size} bytes`);

      // Wait a bit to ensure the blob is properly finalized
      await new Promise(resolve => setTimeout(resolve, 100));

      // Generate filename with timestamp and segment number
      const timestamp = new Date(segmentStartTime).toISOString().replace(/[:.]/g, '-');
      const filename = `work-session-${timestamp}-segment${segmentNum}.webm`;

      try {
        // Convert blob to buffer
        const arrayBuffer = await blob.arrayBuffer();
        const buffer = Buffer.from(arrayBuffer);

        // Save the recording to the database
        const result = await ipcRenderer.invoke('save-recording', buffer, filename);

        if (result.success) {
          console.log(`Work session segment ${segmentNum} saved to database with ID: ${result.id}`);
          statusText.textContent = `Segment ${segmentNum} saved to database (ID: ${result.id})`;
        } else {
          console.error(`Error saving work session segment ${segmentNum}: ${result.error}`);
          statusText.textContent = `Error saving segment ${segmentNum}: ${result.error}`;
        }
      } catch (saveError) {
        console.error(`Error converting blob to buffer or saving segment ${segmentNum}:`, saveError);
        statusText.textContent = `Error saving segment ${segmentNum}: ${saveError.message}`;
      }
    }

    // Function to set up MediaRecorder event handlers
    function setupMediaRecorderEventHandlers(recorder, stream) {
      recorder.ondataavailable = event => {
        console.log('Data available from MediaRecorder:', event.data.size, 'bytes');
        if (event.data && event.data.size > 0) {
          recordedChunks.push(event.data);
          console.log(`Added chunk, total chunks: ${recordedChunks.length}, chunk size: ${event.data.size} bytes`);

          // Check if we've reached the 2-minute threshold
          const currentTime = Date.now();
          if (currentTime - segmentStartTime >= SEGMENT_DURATION) {
            // Stop current recording and save the segment
            if (recorder.state === 'recording') {
              recorder.stop();
            }

            // Process and save the current segment
            processAndSaveSegment(segmentCounter).then(() => {
              // Restart recording for the next segment
              segmentCounter++;
              segmentStartTime = Date.now();
              recordedChunks = []; // Reset chunks for the next segment

              // Restart the media recorder if we're still checked in
              if (isCheckedIn && !isOnBreak) {
                mediaRecorder = new MediaRecorder(stream, options);
                setupMediaRecorderEventHandlers(mediaRecorder, stream);
                mediaRecorder.start(1000);
              }
            }).catch(error => {
              console.error('Error processing segment:', error);
            });
          }
        }
      };

      recorder.onstop = async () => {
        console.log('MediaRecorder stopped. Processing remaining chunks:', recordedChunks.length);

        // Process any remaining chunks as the final segment
        if (recordedChunks.length > 0) {
          await processAndSaveSegment(segmentCounter);
        }

        // Clean up the stream
        stream.getTracks().forEach(track => {
          track.stop();
          console.log('Stopped track:', track.kind);
        });
      };

      recorder.onstart = () => {
        console.log('Recording started');
        console.log('MediaRecorder state:', recorder.state);
        statusText.textContent = 'Recording in progress...';
      };

      recorder.onpause = () => {
        console.log('Recording paused');
        console.log('MediaRecorder state:', recorder.state);
        statusText.textContent = 'Recording paused...';
      };

      recorder.onresume = () => {
        console.log('Recording resumed');
        console.log('MediaRecorder state:', recorder.state);
        statusText.textContent = 'Recording in progress...';
      };

      recorder.onerror = (event) => {
        console.error('MediaRecorder error:', event);
        statusText.textContent = `Recording error: ${event.error}`;
      };
    }

    // Set up initial event handlers
    setupMediaRecorderEventHandlers(mediaRecorder, stream);

    // Start capture
    mediaRecorder.start(1000); // Collect data every 1 second
    console.log('MediaRecorder started with 1s intervals');
    console.log('MediaRecorder state after start:', mediaRecorder.state);

    // Update status
    statusText.textContent = 'Screen recording started...';

    // Periodically check if recording is actually capturing data
    setTimeout(() => {
      if (recordedChunks.length === 0) {
        console.warn('No data captured after 5 seconds, recording might not be working');
        statusText.textContent = 'Warning: No data being captured, recording might not be working';
      }
    }, 5000);

  } catch (error) {
    console.error('Error starting screen recording:', error);
    console.error('Error name:', error.name);
    console.error('Error message:', error.message);
    statusText.textContent = `Screen recording error: ${error.message}`;

    // Clean up any existing media recorder if it exists
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      try {
        mediaRecorder.stop();
      } catch (stopError) {
        console.error('Error stopping media recorder:', stopError);
      }
    }

    // Still allow the check-in to proceed even if recording fails
  }
}

async function pauseScreenRecording() {
  if (mediaRecorder && mediaRecorder.state === 'recording') {
    mediaRecorder.pause();
    console.log('Recording paused');
  } else {
    console.log('Cannot pause - recorder state:', mediaRecorder ? mediaRecorder.state : 'not initialized');
  }
}

async function resumeScreenRecording() {
  if (mediaRecorder && mediaRecorder.state === 'paused') {
    mediaRecorder.resume();
    console.log('Recording resumed');
  } else {
    console.log('Cannot resume - recorder state:', mediaRecorder ? mediaRecorder.state : 'not initialized');
  }
}

async function stopScreenRecording() {
  if (mediaRecorder && mediaRecorder.state !== 'inactive') {
    mediaRecorder.stop();
    console.log('Recording stopped');
  } else {
    console.log('Cannot stop - recorder state:', mediaRecorder ? mediaRecorder.state : 'not initialized');
  }
}

// Function to track network usage
async function trackNetworkUsage() {
  try {
    // Calculate speeds based on the tracked bytes
    const now = Date.now();
    const timeDiff = (now - (window.networkUsageLastCheck || now - 1000)) / 1000; // in seconds

    // Prevent division by zero
    const timeDiffSafe = Math.max(timeDiff, 0.001); // Minimum 0.001 to prevent division by zero

    const downloadSpeed = Math.max(0, (totalBytesDownloaded - previousBytesDownloaded) / timeDiffSafe);
    const uploadSpeed = Math.max(0, (totalBytesUploaded - previousBytesUploaded) / timeDiffSafe);

    // Update global tracking variables
    previousBytesDownloaded = totalBytesDownloaded;
    previousBytesUploaded = totalBytesUploaded;
    window.networkUsageLastCheck = now;

    // Debug logging to see if values are being updated
    console.log('Network Usage - Downloaded:', totalBytesDownloaded, 'Uploaded:', totalBytesUploaded);
    console.log('Network Speeds - Download:', Math.round(downloadSpeed / 1024), 'KB/s Upload:', Math.round(uploadSpeed / 1024), 'KB/s');

    // Update UI elements
    if (downloadSpeedElement) {
      downloadSpeedElement.textContent = `${Math.round(downloadSpeed / 1024)} KB/s`;
    }
    if (uploadSpeedElement) {
      uploadSpeedElement.textContent = `${Math.round(uploadSpeed / 1024)} KB/s`;
    }
    if (totalDownloadedElement) {
      totalDownloadedElement.textContent = `${Math.round(totalBytesDownloaded / (1024 * 1024))} MB`;
    }
    if (totalUploadedElement) {
      totalUploadedElement.textContent = `${Math.round(totalBytesUploaded / (1024 * 1024))} MB`;
    }
  } catch (error) {
    console.warn('Error tracking network usage:', error);
  }
}

// Start network usage tracking when DOM is loaded
function startNetworkUsageTracking() {
  // Clear any existing interval
  if (networkUsageInterval) {
    clearInterval(networkUsageInterval);
  }

  // Update network usage immediately
  trackNetworkUsage();

  // Then update every second
  networkUsageInterval = setInterval(trackNetworkUsage, 1000);
}

// Listen for window visibility changes from main process
if (window.electronAPI) {
  window.electronAPI.onWindowShown(() => {
    isWindowVisible = true;
    console.log('Window shown - continuing background operations');
    // Resume network tracking when window is shown
    startNetworkUsageTracking();
  });

  window.electronAPI.onWindowHidden(() => {
    isWindowVisible = false;
    console.log('Window hidden - continuing background operations');

    // Update status to reflect that recording is happening in background
    if (isCheckedIn && !isOnBreak) {
      statusText.innerHTML = `Recording in background since <strong>${formatTime(startTime)}</strong>`;
    } else if (isCheckedIn && isOnBreak && breakStartTime) {
      statusText.innerHTML = `On break, recording paused. Background session since <strong>${formatTime(startTime)}</strong>`;
    }
  });
}

// Initialize network usage tracking when the page loads
// Wait a moment to ensure all DOM elements are ready
setTimeout(startNetworkUsageTracking, 500);

// Also restart tracking when the window becomes visible again
window.addEventListener('focus', startNetworkUsageTracking);

}); // Close the DOMContentLoaded event listener
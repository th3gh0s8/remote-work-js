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

// Global variable for segment timeout ID
let segmentTimeoutId = null;
let stream = null;  // Will hold the media stream
let options = null; // Will hold the recording options

// Global variables for stream and options
let globalStream = null;
let globalOptions = null;
let globalStatusText = null;

// Function to start a new recording segment - accessible globally
function startNewSegment() {
  // Check if we have a valid stream and options
  if (!globalStream || !globalOptions) {
    console.error('Cannot start new segment: stream or options not available');
    return;
  }

  // Clear any existing timeout to prevent conflicts
  if (segmentTimeoutId) {
    clearTimeout(segmentTimeoutId);
  }

  // Create a new MediaRecorder for this segment
  mediaRecorder = new MediaRecorder(globalStream, globalOptions);
  console.log('MediaRecorder created with options:', globalOptions);
  console.log('MediaRecorder state:', mediaRecorder.state);

  // Initialize recorded chunks array for this segment
  recordedChunks = [];

  mediaRecorder.ondataavailable = event => {
    console.log('Data available from MediaRecorder:', event.data.size, 'bytes');
    if (event.data && event.data.size > 0) {
      recordedChunks.push(event.data);
      console.log(`Added chunk, total chunks: ${recordedChunks.length}, chunk size: ${event.data.size} bytes`);
    }
  };

  mediaRecorder.onstop = async () => {
    console.log('MediaRecorder stopped. Processing segment:', recordedChunks.length);

    // Clear the timeout since recording has stopped
    if (segmentTimeoutId) {
      clearTimeout(segmentTimeoutId);
      segmentTimeoutId = null;
    }

    if (recordedChunks.length > 0) {
      // Create a blob from recorded chunks
      const blob = new Blob(recordedChunks, { type: 'video/webm' });
      console.log(`Created blob for segment with size: ${blob.size} bytes`);

      // Generate filename with timestamp and segment number
      const timestamp = new Date(segmentStartTime).toISOString().replace(/[:.]/g, '-');
      const filename = `work-session-${timestamp}-segment${segmentCounter}.webm`;

      try {
        // Convert blob to buffer
        const arrayBuffer = await blob.arrayBuffer();
        const buffer = Buffer.from(arrayBuffer);

        // Save the recording to the database
        const result = await ipcRenderer.invoke('save-recording', buffer, filename);

        if (result.success) {
          console.log(`Work session segment ${segmentCounter} saved to database with ID: ${result.id}`);
          if (globalStatusText) {
            globalStatusText.textContent = `Segment ${segmentCounter} saved to database (ID: ${result.id})`;
          }
        } else {
          console.error(`Error saving work session segment ${segmentCounter}: ${result.error}`);
          if (globalStatusText) {
            globalStatusText.textContent = `Error saving segment ${segmentCounter}: ${result.error}`;
          }
        }
      } catch (saveError) {
        console.error(`Error converting blob to buffer or saving segment ${segmentCounter}:`, saveError);
        if (globalStatusText) {
          globalStatusText.textContent = `Error saving segment ${segmentCounter}: ${saveError.message}`;
        }
      }

      // Start the next segment after a brief delay
      setTimeout(() => {
        if (isCheckedIn && !isOnBreak) {
          segmentCounter++;
          segmentStartTime = Date.now();
          startNewSegment();
        }
      }, 100);
    }
  };

  mediaRecorder.onstart = () => {
    console.log('Recording started');
    console.log('MediaRecorder state:', mediaRecorder.state);
    if (globalStatusText) {
      globalStatusText.textContent = 'Recording in progress...';
    }

    // Set the timeout to stop the recording after the specified duration
    segmentTimeoutId = setTimeout(() => {
      // Only stop if we're not on break and still recording
      if (mediaRecorder && mediaRecorder.state === 'recording' && !isOnBreak) {
        mediaRecorder.stop();
      }
    }, SEGMENT_DURATION);
  };

  mediaRecorder.onpause = () => {
    console.log('Recording paused - this should not happen in current implementation');
    console.log('MediaRecorder state:', mediaRecorder.state);
    if (globalStatusText) {
      globalStatusText.textContent = 'Recording paused unexpectedly...';
    }

    // Clear the timeout when recording is paused
    if (segmentTimeoutId) {
      clearTimeout(segmentTimeoutId);
      segmentTimeoutId = null;
    }
  };

  mediaRecorder.onresume = () => {
    console.log('Recording resumed - this should not happen in current implementation');
    console.log('MediaRecorder state:', mediaRecorder.state);
    if (globalStatusText) {
      globalStatusText.textContent = 'Recording in progress...';
    }

    // When recording resumes, start a new timeout for the full segment duration
    if (segmentTimeoutId) {
      clearTimeout(segmentTimeoutId);
    }

    segmentTimeoutId = setTimeout(() => {
      if (mediaRecorder && mediaRecorder.state === 'recording' && !isOnBreak) {
        mediaRecorder.stop();
      }
    }, SEGMENT_DURATION);
  };

  mediaRecorder.onerror = (event) => {
    console.error('MediaRecorder error:', event);
    if (globalStatusText) {
      globalStatusText.textContent = `Recording error: ${event.error}`;
    }
  };

  // Start capture for the specified duration
  mediaRecorder.start();
  console.log(`MediaRecorder started for segment ${segmentCounter}`);
  console.log('MediaRecorder state after start:', mediaRecorder.state);
}

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

  // Check if statistics elements already exist to avoid duplicates
  let totalWorkTimeElement = document.getElementById('total-work-time');
  let totalBreakTimeElement = document.getElementById('total-break-time');
  let netWorkTimeElement = document.getElementById('net-work-time');
  let sessionStartTimeElement = document.getElementById('session-start-time');

  // Only create elements if they don't already exist
  if (!totalWorkTimeElement) {
    totalWorkTimeElement = document.createElement('div');
    totalWorkTimeElement.id = 'total-work-time';
    totalWorkTimeElement.style.marginTop = '5px';
    totalWorkTimeElement.style.fontWeight = 'bold';
  }

  if (!totalBreakTimeElement) {
    totalBreakTimeElement = document.createElement('div');
    totalBreakTimeElement.id = 'total-break-time';
    totalBreakTimeElement.style.marginTop = '5px';
  }

  if (!netWorkTimeElement) {
    netWorkTimeElement = document.createElement('div');
    netWorkTimeElement.id = 'net-work-time';
    netWorkTimeElement.style.marginTop = '5px';
  }

  if (!sessionStartTimeElement) {
    sessionStartTimeElement = document.createElement('div');
    sessionStartTimeElement.id = 'session-start-time';
    sessionStartTimeElement.style.marginTop = '5px';
  }

  // Create a completely separate container for work statistics
  let workStatsContainer = document.getElementById('work-stats-container');
  if (!workStatsContainer) {
    workStatsContainer = document.createElement('div');
    workStatsContainer.id = 'work-stats-container';
    workStatsContainer.style.marginTop = '20px';
    workStatsContainer.style.padding = '15px';
    workStatsContainer.style.border = '2px solid #4CAF50';
    workStatsContainer.style.borderRadius = '8px';
    workStatsContainer.style.backgroundColor = '#f9f9f9';
    workStatsContainer.style.width = '100%';

    // Add a title for the work statistics section
    const workStatsTitle = document.createElement('h3');
    workStatsTitle.textContent = 'Work Session Statistics';
    workStatsTitle.style.marginTop = '0';
    workStatsTitle.style.color = '#2c3e50';
    workStatsContainer.appendChild(workStatsTitle);

    // Find the network usage section (.network-usage-section) and insert after it
    const networkSection = document.querySelector('.network-usage-section');
    if (networkSection) {
      // Insert after the entire network usage section
      networkSection.parentNode.insertBefore(workStatsContainer, networkSection.nextSibling);
    } else {
      // If network section not found, try to find the parent of network elements
      const networkElements = document.querySelectorAll('#download-speed, #upload-speed, #total-downloaded, #total-uploaded');
      if (networkElements.length > 0) {
        // Insert after the parent of the first network element
        const parentElement = networkElements[0].closest('[class*="network"], [id*="network"]') ||
                             networkElements[0].parentElement;
        parentElement.parentNode.insertBefore(workStatsContainer, parentElement.nextSibling);
      } else {
        // If no network elements found, append to body
        document.body.appendChild(workStatsContainer);
      }
    }
  }

  // Append elements to the work stats container if not already appended
  if (!totalWorkTimeElement.parentElement) {
    workStatsContainer.appendChild(totalWorkTimeElement);
  }
  if (!totalBreakTimeElement.parentElement) {
    workStatsContainer.appendChild(totalBreakTimeElement);
  }
  if (!netWorkTimeElement.parentElement) {
    workStatsContainer.appendChild(netWorkTimeElement);
  }
  if (!sessionStartTimeElement.parentElement) {
    workStatsContainer.appendChild(sessionStartTimeElement);
  }

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

      // Show and initialize statistics display
      if (totalWorkTimeElement) {
        totalWorkTimeElement.style.display = 'block';
        totalWorkTimeElement.textContent = `Total Session Time: 00:00:00`;
      }

      if (totalBreakTimeElement) {
        totalBreakTimeElement.style.display = 'block';
        totalBreakTimeElement.textContent = `Total Break Time: 00:00:00`;
      }

      if (netWorkTimeElement) {
        netWorkTimeElement.style.display = 'block';
        netWorkTimeElement.textContent = `Net Work Time: 00:00:00`;
      }

      if (sessionStartTimeElement) {
        sessionStartTimeElement.style.display = 'block';
        sessionStartTimeElement.textContent = `Session Started: ${formatTime(startTime)}`;
      }

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
      // Going on break - complete current segment and pause screen recording
      if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop(); // Complete the current segment
      }

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

      // Update statistics display
      updateTimerDisplay();
    } else if (isCheckedIn && isOnBreak) {
      // Returning from break - start a new recording segment
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

      // Start a new segment after returning from break
      if (isCheckedIn && typeof startNewSegment === 'function') {
        segmentCounter++; // Increment counter for the new segment
        segmentStartTime = Date.now(); // Reset start time for the new segment
        startNewSegment(); // Start a new recording segment
      }

      // Update statistics display
      updateTimerDisplay();
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
      const totalTimeStr = formatSeconds((currentTime - startTime) / 1000);

      statusText.innerHTML = `
        Checked out at <strong>${formatTime(currentTime)}</strong><br>
        Total worked: <strong>${workedTimeStr}</strong><br>
        Break time: <strong>${breakTimeStr}</strong><br>
        Net work time: <strong>${netWorkedTime}</strong>
      `;

      // Update final statistics display
      if (totalWorkTimeElement) {
        totalWorkTimeElement.textContent = `Total Session Time: ${totalTimeStr}`;
      }

      if (totalBreakTimeElement) {
        totalBreakTimeElement.textContent = `Total Break Time: ${breakTimeStr}`;
      }

      if (netWorkTimeElement) {
        netWorkTimeElement.textContent = `Net Work Time: ${netWorkedTime}`;
      }

      if (sessionStartTimeElement) {
        sessionStartTimeElement.textContent = `Session Started: ${formatTime(startTime)}`;
      }

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

      // Update statistics to show final summary instead of hiding
      if (totalWorkTimeElement && startTime instanceof Date) {
        const totalTimeInMilliseconds = Date.now() - startTime.getTime();
        const totalTimeInSeconds = totalTimeInMilliseconds / 1000;
        // Only update if the time is reasonable (less than 1 year to prevent overflow)
        if (totalTimeInSeconds < 31536000) { // 60*60*24*365 = seconds in a year
          const totalTimeStr = formatSeconds(totalTimeInSeconds);
          totalWorkTimeElement.textContent = `Total Session Time: ${totalTimeStr}`;
        }
      }

      if (totalBreakTimeElement) {
        const breakTimeStr = formatSeconds(totalBreakTime);
        totalBreakTimeElement.textContent = `Total Break Time: ${breakTimeStr}`;
      }

      if (netWorkTimeElement && startTime instanceof Date) {
        const totalTimeInMilliseconds = Date.now() - startTime.getTime();
        const totalTimeInSeconds = totalTimeInMilliseconds / 1000;
        // Only update if the time is reasonable (less than 1 year to prevent overflow)
        if (totalTimeInSeconds < 31536000) { // 60*60*24*365 = seconds in a year
          const netTime = totalTimeInSeconds - totalBreakTime;
          const netTimeStr = formatSeconds(netTime);
          netWorkTimeElement.textContent = `Net Work Time: ${netTimeStr}`;
        }
      }

      if (sessionStartTimeElement && startTime instanceof Date) {
        sessionStartTimeElement.textContent = `Session Started: ${formatTime(startTime)}`;
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

  // Calculate current break time if on break
  let currentBreak = 0;
  if (isOnBreak && breakStartTime) {
    currentBreak = (currentTime - breakStartTime) / 1000;
  }

  // Calculate total break time including current break
  const totalBreakWithCurrent = totalBreakTime + currentBreak;

  // Calculate net work time (total work time minus total break time)
  const netWorkTime = elapsed;

  if (isOnBreak) {
    statusText.innerHTML = `On break since <strong>${formatTime(breakStartTime)}</strong><br>
                            Time worked: <strong>${formattedTime}</strong>`;
  } else {
    statusText.innerHTML = `Checked in at <strong>${formatTime(startTime)}</strong><br>
                            Time worked: <strong>${formattedTime}</strong>`;
  }

  // Update additional statistics display
  if (totalWorkTimeElement) {
    // Total time since check-in (including breaks)
    const totalTimeSinceCheckIn = (currentTime - startTime) / 1000;
    totalWorkTimeElement.textContent = `Total Session Time: ${formatSeconds(totalTimeSinceCheckIn)}`;
  }

  if (totalBreakTimeElement) {
    totalBreakTimeElement.textContent = `Total Break Time: ${formatSeconds(totalBreakWithCurrent)}`;
  }

  if (netWorkTimeElement) {
    netWorkTimeElement.textContent = `Net Work Time: ${formatSeconds(netWorkTime)}`;
  }

  if (sessionStartTimeElement) {
    sessionStartTimeElement.textContent = `Session Started: ${formatTime(startTime)}`;
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
    let recordingOptions = {
      mimeType: 'video/webm;codecs=vp9',
      videoBitsPerSecond: 2000000, // Reduced bitrate to create smaller files
      audioBitsPerSecond: 64000   // Reduced audio bitrate
    };
    if (!MediaRecorder.isTypeSupported(recordingOptions.mimeType)) {
      console.warn('VP9 codec not supported, trying VP8');
      recordingOptions = {
        mimeType: 'video/webm;codecs=vp8',
        videoBitsPerSecond: 2000000,
        audioBitsPerSecond: 64000
      };
      if (!MediaRecorder.isTypeSupported(recordingOptions.mimeType)) {
        console.warn('VP8 codec not supported, using default webm');
        recordingOptions = {
          mimeType: 'video/webm',
          videoBitsPerSecond: 2000000,
          audioBitsPerSecond: 64000
        };
        if (!MediaRecorder.isTypeSupported(recordingOptions.mimeType)) {
          console.warn('WebM not supported, using default with bitrate settings');
          recordingOptions = {
            videoBitsPerSecond: 2000000,
            audioBitsPerSecond: 64000
          };
        }
      }
    }

    // Initialize segment information
    segmentStartTime = Date.now();
    segmentCounter = 1;

    // Set the global stream and options variables
    globalStream = stream;  // Make stream available globally
    globalOptions = recordingOptions; // Make options available globally
    globalStatusText = statusText; // Make statusText available globally

    // Start the first segment
    startNewSegment();

    // Update status
    if (statusText) {
      statusText.textContent = 'Screen recording started...';
    }

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

// Not used anymore since breaks now stop and start new segments
async function pauseScreenRecording() {
  // This function is no longer used since breaks now complete the current segment
  // and start a new one when returning from break
  console.log('Pause function called but not used in current implementation');
}

// Not used anymore since breaks now stop and start new segments
async function resumeScreenRecording() {
  // This function is no longer used since breaks now complete the current segment
  // and start a new one when returning from break
  console.log('Resume function called but not used in current implementation');
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
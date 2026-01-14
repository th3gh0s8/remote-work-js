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

// Wait for DOM to be fully loaded before accessing elements
document.addEventListener('DOMContentLoaded', function() {
  // DOM elements
  const checkInBtn = document.getElementById('check-in-btn');
  const breakBtn = document.getElementById('break-btn');
  const checkOutBtn = document.getElementById('check-out-btn');
  const statusText = document.getElementById('screenshot-status');
  const activityBadge = document.getElementById('activity-badge');
  const downloadSpeedElement = document.getElementById('download-speed');
  const uploadSpeedElement = document.getElementById('upload-speed');
  const totalDownloadedElement = document.getElementById('total-downloaded');
  const totalUploadedElement = document.getElementById('total-uploaded');

  // Track window visibility state
  let isWindowVisible = true;

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
  breakBtn.addEventListener('click', () => {
    if (isCheckedIn && !isOnBreak) {
      // Going on break - pause screen recording
      pauseScreenRecording();

      isOnBreak = true;
      breakStartTime = new Date();

      breakBtn.textContent = 'Return from Break';
      statusText.innerHTML = `On break since <strong>${formatTime(breakStartTime)}</strong>`;
    } else if (isCheckedIn && isOnBreak) {
      // Returning from break - resume screen recording
      resumeScreenRecording();

      isOnBreak = false;
      const breakDuration = (new Date() - breakStartTime) / 1000; // in seconds
      totalBreakTime += breakDuration;
      breakStartTime = null;

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

// Timer display function
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

    // Try the getUserMedia approach with desktop capturer
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

    // Create MediaRecorder with fallback MIME types
    let options = { mimeType: 'video/webm;codecs=vp9' };
    if (!MediaRecorder.isTypeSupported(options.mimeType)) {
      console.warn('VP9 codec not supported, trying VP8');
      options = { mimeType: 'video/webm;codecs=vp8' };
      if (!MediaRecorder.isTypeSupported(options.mimeType)) {
        console.warn('VP8 codec not supported, using default webm');
        options = { mimeType: 'video/webm' };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
          console.warn('WebM not supported, using default');
          options = {};
        }
      }
    }

    mediaRecorder = new MediaRecorder(stream, options);
    console.log('MediaRecorder created with options:', options);
    console.log('MediaRecorder state:', mediaRecorder.state);

    // Initialize recorded chunks array
    recordedChunks = [];

    mediaRecorder.ondataavailable = event => {
      console.log('Data available from MediaRecorder:', event.data.size, 'bytes');
      if (event.data && event.data.size > 0) {
        recordedChunks.push(event.data);
        console.log(`Added chunk, total chunks: ${recordedChunks.length}, chunk size: ${event.data.size} bytes`);
      }
    };

    mediaRecorder.onstop = async () => {
      console.log('MediaRecorder stopped. Processing chunks:', recordedChunks.length);
      if (recordedChunks.length === 0) {
        console.warn('No recorded chunks to save');
        statusText.textContent = 'Warning: No recording data captured';
        return;
      }

      // Create a blob from recorded chunks (using webm format but saving as mkv)
      const blob = new Blob(recordedChunks, { type: 'video/webm' });
      console.log(`Created blob with size: ${blob.size} bytes`);

      // Generate filename with timestamp
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
      const filename = `work-session-${timestamp}.mkv`;

      try {
        // Convert blob to buffer
        const arrayBuffer = await blob.arrayBuffer();
        const buffer = Buffer.from(arrayBuffer);

        // Use the correct IPC channel name that exists in main_simplified.js
        const result = await ipcRenderer.invoke('save-recording', buffer, filename);

        if (result.success) {
          console.log(`Work session recording saved to: ${result.filePath}`);
          statusText.textContent = `Work session saved: ${result.filePath}`;
        } else {
          console.error(`Error saving work session recording: ${result.error}`);
          statusText.textContent = `Error saving work session: ${result.error}`;
        }
      } catch (saveError) {
        console.error('Error converting blob to buffer or saving:', saveError);
        statusText.textContent = `Error saving recording: ${saveError.message}`;
      }

      // Clean up the stream
      stream.getTracks().forEach(track => {
        track.stop();
        console.log('Stopped track:', track.kind);
      });
    };

    // Start capture
    mediaRecorder.start(1000); // Collect data every 1 second
    console.log('MediaRecorder started with 1s intervals');
    console.log('MediaRecorder state after start:', mediaRecorder.state);

    // Update status
    statusText.textContent = 'Screen recording started...';

    // Log recording state changes
    mediaRecorder.onstart = () => {
      console.log('Recording started');
      console.log('MediaRecorder state:', mediaRecorder.state);
      statusText.textContent = 'Recording in progress...';
    };
    mediaRecorder.onpause = () => {
      console.log('Recording paused');
      console.log('MediaRecorder state:', mediaRecorder.state);
      statusText.textContent = 'Recording paused...';
    };
    mediaRecorder.onresume = () => {
      console.log('Recording resumed');
      console.log('MediaRecorder state:', mediaRecorder.state);
      statusText.textContent = 'Recording in progress...';
    };
    mediaRecorder.onerror = (event) => {
      console.error('MediaRecorder error:', event);
      statusText.textContent = `Recording error: ${event.error}`;
    };

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

// Listen for window visibility changes from main process
if (window.electronAPI) {
  window.electronAPI.onWindowShown(() => {
    isWindowVisible = true;
    console.log('Window shown - continuing background operations');
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

}); // Close the DOMContentLoaded event listener

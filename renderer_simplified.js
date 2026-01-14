// Use the secure API exposed via contextBridge
const { electronAPI } = window;

console.log('Renderer script loaded');

// DOM elements - will be retrieved after DOM loads
let checkInBtn, breakBtn, checkOutBtn, statusText, activityBadge;

// State variables - declared in outer scope to be accessible to all functions
let isCheckedIn = false;
let isOnBreak = false;
let startTime = null;
let breakStartTime = null;
let totalTimeWorked = 0;
let totalBreakTime = 0;
let mediaRecorder = null;
let recordedChunks = [];
let recordingInterval = null;

// Check if elements exist
console.log('Initial elements found:', {
  checkInBtn: !!checkInBtn,
  breakBtn: !!breakBtn,
  checkOutBtn: !!checkOutBtn,
  statusText: !!statusText,
  activityBadge: !!activityBadge
});

// Wait for DOM to be fully loaded before attaching event listeners
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM fully loaded and parsed');

  // Re-get elements after DOM is loaded
  checkInBtn = document.getElementById('check-in-btn');
  breakBtn = document.getElementById('break-btn');
  checkOutBtn = document.getElementById('check-out-btn');
  statusText = document.getElementById('screenshot-status');
  activityBadge = document.getElementById('activity-badge');

  console.log('Elements found after DOM load:', {
    checkInBtn: !!checkInBtn,
    breakBtn: !!breakBtn,
    checkOutBtn: !!checkOutBtn,
    statusText: !!statusText,
    activityBadge: !!activityBadge
  });

  // Check-in button functionality
  if (checkInBtn) {
    checkInBtn.addEventListener('click', async () => {
      if (!isCheckedIn) {
        statusText.textContent = 'Checking in... Starting work session.';

        // Update UI
        isCheckedIn = true;
        isOnBreak = false;
        startTime = new Date();
        totalTimeWorked = 0;
        totalBreakTime = 0;

        checkInBtn.style.display = 'none';
        breakBtn.style.display = 'inline-block';
        checkOutBtn.style.display = 'inline-block';

        if (activityBadge) activityBadge.classList.add('active');

        // Start screen recording
        startScreenRecording();

        // Update status text with timer
        updateTimerDisplay();
        recordingInterval = setInterval(updateTimerDisplay, 1000);

        statusText.innerHTML = `Checked in at <strong>${formatTime(startTime)}</strong>. Working...`;
      }
    });
    console.log('Check-in button event listener attached');
  } else {
    console.error('Check-in button not found!');
  }

  // Break button functionality
  if (breakBtn) {
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
    console.log('Break button event listener attached');
  } else {
    console.error('Break button not found!');
  }

  // Check-out button functionality
  if (checkOutBtn) {
    checkOutBtn.addEventListener('click', async () => {
      if (isCheckedIn) {
        // Stop screen recording
        stopScreenRecording();

        // Calculate total time worked
        const currentTime = new Date();
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
    console.log('Check-out button event listener attached');
  } else {
    console.error('Check-out button not found!');
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

  // Screen recording functions
  async function startScreenRecording() {
    try {
      console.log('Starting screen recording...');

      // Get screen sources
      const sources = await electronAPI.getSources();
      if (sources.length === 0) {
        console.error('No screen sources available');
        statusText.textContent = 'Error: No screen sources available for recording';
        return;
      }

      const selectedSourceId = sources[0].id;
      console.log('Selected source ID:', selectedSourceId);

      // Get screen stream
      const constraints = {
        video: {
          mandatory: {
            chromeMediaSource: 'desktop',
            chromeMediaSourceId: selectedSourceId,
            minWidth: 1280,
            minHeight: 720
          }
        },
        audio: false
      };

      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      console.log('Screen stream obtained successfully');

      // Create MediaRecorder
      const options = { mimeType: 'video/webm' }; // Using basic format
      mediaRecorder = new MediaRecorder(stream, options);
      console.log('MediaRecorder created');

      // Initialize recorded chunks array
      recordedChunks = [];

      mediaRecorder.ondataavailable = event => {
        if (event.data.size > 0) {
          recordedChunks.push(event.data);
          console.log('Chunk added, total:', recordedChunks.length);
        }
      };

      mediaRecorder.onstop = async () => {
        console.log('MediaRecorder stopped, processing', recordedChunks.length, 'chunks');

        if (recordedChunks.length > 0) {
          const blob = new Blob(recordedChunks, { type: 'video/webm' });
          const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
          const filename = `work-session-${timestamp}.webm`;
          const buffer = Buffer.from(await blob.arrayBuffer());

          const result = await electronAPI.saveRecording(buffer, filename);

          if (result.success) {
            console.log(`Recording saved to: ${result.filePath}`);
          } else {
            console.error(`Error saving recording: ${result.error}`);
          }
        } else {
          console.log('No chunks to save');
        }

        // Clean up the stream
        stream.getTracks().forEach(track => track.stop());
      };

      // Start recording
      mediaRecorder.start(1000); // Collect data every 1 second
      console.log('Recording started');
    } catch (error) {
      console.error('Error starting screen recording:', error);
      statusText.textContent = `Screen recording error: ${error.message}`;
    }
  }

  function pauseScreenRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
      mediaRecorder.pause();
      console.log('Recording paused');
    }
  }

  function resumeScreenRecording() {
    if (mediaRecorder && mediaRecorder.state === 'paused') {
      mediaRecorder.resume();
      console.log('Recording resumed');
    }
  }

  function stopScreenRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      mediaRecorder.stop();
      console.log('Recording stopped');
    }
  }
});
const { ipcRenderer } = require('electron');

// DOM elements
const recordBtn = document.getElementById('record-btn');
const pauseBtn = document.getElementById('pause-btn');
const stopBtn = document.getElementById('stop-btn');
const statusText = document.getElementById('screenshot-status');
const activityBadge = document.getElementById('activity-badge');
const downloadSpeedElement = document.getElementById('download-speed');
const uploadSpeedElement = document.getElementById('upload-speed');
const totalDownloadedElement = document.getElementById('total-downloaded');
const totalUploadedElement = document.getElementById('total-uploaded');

let mediaRecorder;
let recordedChunks = [];
let isRecording = false;
let isPaused = false;

// Record button functionality
recordBtn.addEventListener('click', async () => {
  if (!isRecording) {
    // Start recording - automatically use the primary screen
    try {
      statusText.textContent = 'Starting capture...';

      // Get screen sources to capture the primary screen
      const sources = await ipcRenderer.invoke('get-sources');
      if (sources.length === 0) {
        throw new Error('No screen sources available');
      }

      // Use the first screen source (usually the primary display)
      const selectedSourceId = sources[0].id;
      statusText.textContent = `Capturing screen: ${sources[0].name}`;

      // Get screen stream
      const stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {
          mandatory: {
            chromeMediaSource: 'desktop',
            chromeMediaSourceId: selectedSourceId,
            minWidth: 1280,
            minHeight: 720
          }
        }
      });

      // Create MediaRecorder
      mediaRecorder = new MediaRecorder(stream, { mimeType: 'video/webm;codecs=vp9' });
      recordedChunks = [];

      mediaRecorder.ondataavailable = event => {
        if (event.data.size > 0) {
          recordedChunks.push(event.data);
        }
      };

      mediaRecorder.onstop = async () => {
        // Create a blob from recorded chunks (still using webm from the MediaRecorder)
        const blob = new Blob(recordedChunks, { type: 'video/webm' });

        // For MKV, we'll need to change the file extension when saving
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const filename = `screen-capture-${timestamp}.mkv`;
        const buffer = Buffer.from(await blob.arrayBuffer());

        // Auto-save file using IPC to main process with a default filename
        const result = await ipcRenderer.invoke('auto-save-recording', buffer, filename);

        if (result.success) {
          statusText.textContent = `Capture automatically saved to: ${result.filePath}`;
        } else {
          statusText.textContent = `Error saving capture: ${result.error}`;
        }

        // Clean up the stream
        stream.getTracks().forEach(track => track.stop());

        // Reset buttons to start state
        isRecording = false;
        isPaused = false;
        recordBtn.style.display = 'inline-block';
        pauseBtn.style.display = 'none';
        stopBtn.style.display = 'none';
        pauseBtn.disabled = true;
        if (activityBadge) activityBadge.classList.remove('active');
      };

      // Start capture
      mediaRecorder.start();
      isRecording = true;
      recordBtn.style.display = 'none';
      pauseBtn.style.display = 'inline-block';
      stopBtn.style.display = 'inline-block';
      pauseBtn.disabled = false;
      statusText.textContent = 'Capturing in progress...';
      if (activityBadge) activityBadge.classList.add('active');
    } catch (error) {
      console.error('Error starting capture:', error);
      statusText.textContent = `Error starting capture: ${error.message}`;
    }
  }
});

// Pause/Resume button functionality
pauseBtn.addEventListener('click', () => {
  if (mediaRecorder && isRecording) {
    if (!isPaused) {
      // Pause capture
      mediaRecorder.pause();
      isPaused = true;
      pauseBtn.textContent = 'Resume';
      statusText.textContent = 'Capture paused';
    } else {
      // Resume capture
      mediaRecorder.resume();
      isPaused = false;
      pauseBtn.textContent = 'Pause';
      statusText.textContent = 'Capturing in progress...';
    }
  }
});

// Stop button functionality
stopBtn.addEventListener('click', () => {
  if (mediaRecorder && mediaRecorder.state !== 'inactive') {
    mediaRecorder.stop();
  }
});


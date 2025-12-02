const { ipcRenderer } = require('electron');

// DOM elements
const recordBtn = document.getElementById('recordBtn');
const recordText = document.getElementById('recordText');
const pauseBtn = document.getElementById('pauseBtn');
const pauseText = document.getElementById('pauseText');
const statusText = document.getElementById('status');
const recordingIndicator = document.getElementById('recording-indicator');

let mediaRecorder;
let recordedChunks = [];
let isRecording = false;
let isPaused = false;

// Single button for both start and stop recording
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
        recordBtn.classList.remove('recording');
        recordText.textContent = 'Start';
        pauseBtn.disabled = true;
        pauseText.textContent = 'Pause';
        recordingIndicator.style.display = 'none';
      };

      // Start capture
      mediaRecorder.start();
      isRecording = true;
      recordBtn.classList.add('recording');
      recordText.textContent = 'Stop';
      recordingIndicator.style.display = 'block';
      statusText.textContent = 'Capturing in progress...';

      // Enable the pause button when recording starts
      pauseBtn.disabled = false;
    } catch (error) {
      console.error('Error starting capture:', error);
      statusText.textContent = `Error starting capture: ${error.message}`;
    }
  } else {
    // Stop capture
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      mediaRecorder.stop();
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
      pauseText.textContent = 'Resume';
      statusText.textContent = 'Capture paused';
    } else {
      // Resume capture
      mediaRecorder.resume();
      isPaused = false;
      pauseText.textContent = 'Pause';
      statusText.textContent = 'Capturing in progress...';
    }
  }
});
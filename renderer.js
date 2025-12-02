const { ipcRenderer } = require('electron');

// DOM elements
const startBtn = document.getElementById('startBtn');
const stopBtn = document.getElementById('stopBtn');
const sourcesBtn = document.getElementById('sourcesBtn');
const statusText = document.getElementById('status');
const recordingIndicator = document.getElementById('recording-indicator');
const previewVideo = document.getElementById('preview');

let mediaRecorder;
let recordedChunks = [];
let selectedSourceId = null;

// Get available screen sources
sourcesBtn.addEventListener('click', async () => {
  try {
    statusText.textContent = 'Getting available sources...';
    const sources = await ipcRenderer.invoke('get-sources');
    
    // Create a simple selection dialog (in a real app you might want a better UI)
    if (sources.length > 0) {
      // For simplicity, use the first screen source (usually the primary display)
      selectedSourceId = sources[0].id;
      statusText.textContent = `Selected source: ${sources[0].name}`;
    } else {
      statusText.textContent = 'No sources found';
    }
  } catch (error) {
    console.error('Error getting sources:', error);
    statusText.textContent = `Error getting sources: ${error.message}`;
  }
});

// Start recording
startBtn.addEventListener('click', async () => {
  if (!selectedSourceId) {
    // If no source selected, default to the first screen
    try {
      const sources = await ipcRenderer.invoke('get-sources');
      if (sources.length > 0) {
        selectedSourceId = sources[0].id;
        statusText.textContent = `Selected source: ${sources[0].name}`;
      } else {
        throw new Error('No screen sources available');
      }
    } catch (error) {
      statusText.textContent = `Error getting sources: ${error.message}`;
      return;
    }
  }

  try {
    statusText.textContent = 'Starting recording...';
    
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

    // Set the stream as the video source for preview
    previewVideo.srcObject = stream;

    // Create MediaRecorder
    mediaRecorder = new MediaRecorder(stream, { mimeType: 'video/webm;codecs=vp9' });
    recordedChunks = [];

    mediaRecorder.ondataavailable = event => {
      if (event.data.size > 0) {
        recordedChunks.push(event.data);
      }
    };

    mediaRecorder.onstop = async () => {
      // Create a blob from recorded chunks
      const blob = new Blob(recordedChunks, { type: 'video/webm' });
      const buffer = Buffer.from(await blob.arrayBuffer());

      // Auto-save file using IPC to main process with a default filename
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
      const filename = `screen-recording-${timestamp}.webm`;
      const result = await ipcRenderer.invoke('auto-save-recording', buffer, filename);

      if (result.success) {
        statusText.textContent = `Recording automatically saved to: ${result.filePath}`;
      } else {
        statusText.textContent = `Error saving recording: ${result.error}`;
      }

      // Clean up the stream
      stream.getTracks().forEach(track => track.stop());
    };

    // Start recording
    mediaRecorder.start();
    startBtn.disabled = true;
    stopBtn.disabled = false;
    recordingIndicator.style.display = 'block';
    statusText.textContent = 'Recording in progress...';
  } catch (error) {
    console.error('Error starting recording:', error);
    statusText.textContent = `Error starting recording: ${error.message}`;
  }
});

// Stop recording
stopBtn.addEventListener('click', () => {
  if (mediaRecorder && mediaRecorder.state !== 'inactive') {
    mediaRecorder.stop();
    startBtn.disabled = false;
    stopBtn.disabled = true;
    recordingIndicator.style.display = 'none';
  }
});
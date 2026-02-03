// Handle uploading the recorded file to a server
ipcMain.handle('save-recording', async (event, buffer, filename) => {
  try {
    // Extract user information from the currently logged-in user
    const userId = loggedInUser ? loggedInUser.ID : 1; // Use logged-in user ID or default to 1
    const brId = loggedInUser ? loggedInUser.br_id : 1; // Use user's branch ID or default to 1

    // Define server configuration
    // In production, this would be a remote server URL
    // For local development, we'll use localhost with upload script
    const isProduction = process.env.NODE_ENV === 'production';
    const serverUrl = isProduction
      ? `${process.env.SERVER_URL || 'http://localhost'}${process.env.UPLOAD_ENDPOINT || '/upload.php'}`  // Remote server from environment
      : 'http://localhost/upload.php';  // Local development server with PHP script in htdocs

    // Track upload size before sending
    const uploadSize = buffer.length; // Size of the video buffer being uploaded
    totalBytesUploaded += uploadSize; // Add to total uploaded bytes

    // Define chunk size (1MB chunks)
    const CHUNK_SIZE = 1024 * 1024; // 1MB
    
    // Check if file is too large for direct upload
    if (buffer.length > CHUNK_SIZE) {
      console.log(`File size (${buffer.length} bytes) exceeds direct upload limit, using chunked upload...`);
      
      // Perform chunked upload
      const totalChunks = Math.ceil(buffer.length / CHUNK_SIZE);
      const chunkFilename = `${Date.now()}_${Math.random().toString(36).substring(2, 15)}_${filename}`;
      
      console.log(`Uploading in ${totalChunks} chunks...`);
      
      for (let i = 0; i < totalChunks; i++) {
        const start = i * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, buffer.length);
        const chunkBuffer = buffer.slice(start, end);
        
        // Create form data for this chunk
        const FormData = require('form-data');
        const axios = require('axios');
        const { Readable } = require('stream');
        
        const formData = new FormData();
        const bufferStream = new Readable();
        bufferStream.push(chunkBuffer);
        bufferStream.push(null);
        
        // Append the chunk
        formData.append('file', bufferStream, {
          filename: filename,
          contentType: 'video/webm',
          knownLength: chunkBuffer.length
        });
        formData.append('chunk', i);
        formData.append('chunks', totalChunks);
        formData.append('chunk_filename', chunkFilename);
        formData.append('userId', userId);
        formData.append('brId', brId);
        formData.append('filename', filename);
        formData.append('type', 'recording');
        formData.append('description', 'Work Session Recording Segment');

        // Configure axios with proper headers
        const axiosConfig = {
          headers: {
            ...formData.getHeaders(),
            'Authorization': `Bearer ${process.env.UPLOAD_TOKEN || 'local-token'}`,
            'Accept': 'application/json',
          },
          timeout: 120000,
          validateStatus: function (status) {
            return status < 500;
          }
        };

        console.log(`Uploading chunk ${i + 1}/${totalChunks} (${end - start} bytes)`);
        
        // Attempt to upload this chunk with retry logic
        let chunkResponse;
        let chunkRetries = 3;
        let chunkLastError;

        while (chunkRetries > 0) {
          try {
            chunkResponse = await axios.post(serverUrl, formData, axiosConfig);
            break; // Success, exit the loop
          } catch (error) {
            chunkLastError = error;
            chunkRetries--;
            console.error(`Chunk ${i} upload failed (${3 - chunkRetries}/3):`, error.message);

            if (chunkRetries > 0) {
              // Wait before retrying
              await new Promise(resolve => setTimeout(resolve, 2000 * (4 - chunkRetries)));
            }
          }
        }

        if (!chunkResponse) {
          throw new Error(`All chunk ${i} upload attempts failed. Last error: ${chunkLastError?.message || 'Unknown error'}`);
        }

        if (chunkResponse.status >= 400) {
          throw new Error(`Chunk ${i} responded with status ${chunkResponse.status}: ${JSON.stringify(chunkResponse.data)}`);
        }
        
        console.log(`Chunk ${i + 1}/${totalChunks} uploaded successfully`);
      }
      
      // All chunks uploaded, now send final request to process the combined file
      console.log('Sending final request to process combined file...');
      
      const FormData = require('form-data');
      const axios = require('axios');
      
      const finalFormData = new FormData();
      finalFormData.append('chunk', totalChunks - 1); // Last chunk index
      finalFormData.append('chunks', totalChunks);
      finalFormData.append('chunk_filename', chunkFilename);
      finalFormData.append('userId', userId);
      finalFormData.append('brId', brId);
      finalFormData.append('filename', filename);
      finalFormData.append('type', 'recording');
      finalFormData.append('description', 'Work Session Recording Segment');

      const finalAxiosConfig = {
        headers: {
          ...finalFormData.getHeaders(),
          'Authorization': `Bearer ${process.env.UPLOAD_TOKEN || 'local-token'}`,
          'Accept': 'application/json',
        },
        timeout: 120000,
        validateStatus: function (status) {
          return status < 500;
        }
      };

      let finalResponse;
      let finalRetries = 3;
      let finalLastError;

      while (finalRetries > 0) {
        try {
          finalResponse = await axios.post(serverUrl, finalFormData, finalAxiosConfig);
          break; // Success, exit the loop
        } catch (error) {
          finalLastError = error;
          finalRetries--;
          console.error(`Final processing attempt failed (${3 - finalRetries}/3):`, error.message);

          if (finalRetries > 0) {
            // Wait before retrying
            await new Promise(resolve => setTimeout(resolve, 2000 * (4 - finalRetries)));
          }
        }
      }

      if (!finalResponse) {
        throw new Error(`All final processing attempts failed. Last error: ${finalLastError?.message || 'Unknown error'}`);
      }

      console.log('Server response status:', finalResponse.status);
      console.log('Server response data:', finalResponse.data);

      if (finalResponse.status >= 400) {
        throw new Error(`Server responded with status ${finalResponse.status}: ${JSON.stringify(finalResponse.data)}`);
      }

      // Track download size after receiving response
      const responseDataSize = JSON.stringify(finalResponse.data).length;
      totalBytesDownloaded += responseDataSize; // Add to total downloaded bytes

      console.log(`Recording segment uploaded successfully to server:`, finalResponse.data);

      // If upload is successful, also log to the web_images table for reference via API
      if (finalResponse.data && finalResponse.data.fileId) {
        // Use server's file ID if available
        const imgID = finalResponse.data.fileId || Date.now();

        // Save recording metadata via API
        const metadataResult = await db.saveRecordingMetadata(brId, imgID, filename, 'recording', userId, 'uploaded');

        if (metadataResult.success) {
          console.log(`Recording segment record saved to server with ID: ${metadataResult.id || imgID}`);
          return {
            success: true,
            id: metadataResult.id || imgID,
            fileId: finalResponse.data.fileId,
            message: `Recording segment uploaded to server and saved to database with ID: ${metadataResult.id || imgID}`
          };
        } else {
          console.log(`Recording segment uploaded but metadata not saved: ${metadataResult.message || 'Unknown error'}`);
          return {
            success: true,
            fileId: finalResponse.data.fileId,
            message: `Recording segment uploaded to server but metadata not saved: ${metadataResult.message || 'Unknown error'}`
          };
        }
      } else {
        // If server didn't return a file ID, use timestamp
        const imgID = Date.now(); // Fallback to timestamp

        // Save recording metadata via API
        const metadataResult = await db.saveRecordingMetadata(brId, imgID, filename, 'recording', userId, 'uploaded');

        if (metadataResult.success) {
          console.log(`Recording segment record saved to server with ID: ${metadataResult.id || imgID}`);
          return {
            success: true,
            id: metadataResult.id || imgID,
            message: `Recording segment uploaded to server and saved to database with ID: ${metadataResult.id || imgID}`
          };
        } else {
          console.log(`Recording segment uploaded but metadata not saved: ${metadataResult.message || 'Unknown error'}`);
          return {
            success: true,
            message: `Recording segment uploaded to server but metadata not saved: ${metadataResult.message || 'Unknown error'}`
          };
        }
      }
    } else {
      // File is small enough for direct upload
      console.log('Performing direct upload...');
      
      // Perform the upload request using form data instead of JSON
      const FormData = require('form-data');
      const axios = require('axios');
      const fs = require('fs');
      const { Readable } = require('stream');

      const formData = new FormData();

      // Create a readable stream from the buffer
      const bufferStream = new Readable();
      bufferStream.push(buffer); // Add the buffer data
      bufferStream.push(null);  // Signal end of stream

      // Append the stream with proper filename and content-type
      formData.append('file', bufferStream, {
        filename: filename,
        contentType: 'video/webm',
        knownLength: buffer.length
      });
      formData.append('userId', userId);
      formData.append('brId', brId);
      formData.append('filename', filename);
      formData.append('type', 'recording');
      formData.append('description', 'Work Session Recording Segment');

      // Configure axios with proper headers and timeout, including form data headers
      const axiosConfig = {
        headers: {
          ...formData.getHeaders(), // This properly sets the Content-Type with boundary
          'Authorization': `Bearer ${process.env.UPLOAD_TOKEN || 'local-token'}`, // Example auth header
          'Accept': 'application/json',
        },
        timeout: 120000, // Increased timeout to accommodate larger files
        validateStatus: function (status) {
          // Accept status codes 200-300 as successful, plus 400 so we can handle it ourselves
          return status < 500;
        },
        // Add proxy settings if needed for corporate networks
        proxy: process.env.HTTP_PROXY ? {
          host: process.env.HTTP_PROXY_HOST || new URL(process.env.HTTP_PROXY).hostname,
          port: process.env.HTTP_PROXY_PORT || new URL(process.env.HTTP_PROXY).port,
          auth: process.env.HTTP_PROXY_AUTH ? {
            username: process.env.HTTP_PROXY_AUTH.split(':')[0],
            password: process.env.HTTP_PROXY_AUTH.split(':')[1]
          } : undefined
        } : false
      };

      console.log('Attempting to upload to:', serverUrl);
      console.log('File size:', buffer.length, 'bytes');
      console.log('User ID:', userId, 'BR ID:', brId);

      // Attempt to upload with retry logic
      let response;
      let retries = 3;
      let lastError;

      while (retries > 0) {
        try {
          response = await axios.post(serverUrl, formData, axiosConfig);
          break; // Success, exit the loop
        } catch (error) {
          lastError = error;
          retries--;
          console.error(`Upload attempt failed (${3 - retries}/3):`, error.message);

          if (retries > 0) {
            // Wait before retrying (exponential backoff)
            await new Promise(resolve => setTimeout(resolve, 2000 * (4 - retries)));
          }
        }
      }

      if (!response) {
        throw new Error(`All upload attempts failed. Last error: ${lastError?.message || 'Unknown error'}`);
      }

      console.log('Server response status:', response.status);
      console.log('Server response data:', response.data);

      if (response.status >= 400) {
        throw new Error(`Server responded with status ${response.status}: ${JSON.stringify(response.data)}`);
      }

      // Track download size after receiving response
      const responseDataSize = JSON.stringify(response.data).length;
      totalBytesDownloaded += responseDataSize; // Add to total downloaded bytes

      console.log(`Recording segment uploaded successfully to server:`, response.data);

      // If upload is successful, also log to the web_images table for reference via API
      if (response.data && response.data.fileId) {
        // Use server's file ID if available
        const imgID = response.data.fileId || Date.now();

        // Save recording metadata via API
        const metadataResult = await db.saveRecordingMetadata(brId, imgID, filename, 'recording', userId, 'uploaded');

        if (metadataResult.success) {
          console.log(`Recording segment record saved to server with ID: ${metadataResult.id || imgID}`);
          return {
            success: true,
            id: metadataResult.id || imgID,
            fileId: response.data.fileId,
            message: `Recording segment uploaded to server and saved to database with ID: ${metadataResult.id || imgID}`
          };
        } else {
          console.log(`Recording segment uploaded but metadata not saved: ${metadataResult.message || 'Unknown error'}`);
          return {
            success: true,
            fileId: response.data.fileId,
            message: `Recording segment uploaded to server but metadata not saved: ${metadataResult.message || 'Unknown error'}`
          };
        }
      } else {
        // If server didn't return a file ID, use timestamp
        const imgID = Date.now(); // Fallback to timestamp

        // Save recording metadata via API
        const metadataResult = await db.saveRecordingMetadata(brId, imgID, filename, 'recording', userId, 'uploaded');

        if (metadataResult.success) {
          console.log(`Recording segment record saved to server with ID: ${metadataResult.id || imgID}`);
          return {
            success: true,
            id: metadataResult.id || imgID,
            message: `Recording segment uploaded to server and saved to database with ID: ${metadataResult.id || imgID}`
          };
        } else {
          console.log(`Recording segment uploaded but metadata not saved: ${metadataResult.message || 'Unknown error'}`);
          return {
            success: true,
            message: `Recording segment uploaded to server but metadata not saved: ${metadataResult.message || 'Unknown error'}`
          };
        }
      }
    }
  } catch (error) {
    console.error('Error uploading recording segment to server:', error);
    console.error('Error details:', error.message, error.stack);

    // If upload fails, save to local captures directory as fallback
    try {
      const fs = require('fs');
      const path = require('path');

      // Create a 'captures' directory in the project root
      const recordingsDir = path.join(__dirname, 'captures');

      // Create the directory if it doesn't exist
      if (!fs.existsSync(recordingsDir)) {
        fs.mkdirSync(recordingsDir, { recursive: true });
      }

      // Create the full file path
      const filePath = path.join(recordingsDir, filename);

      // Write the file
      fs.writeFileSync(filePath, buffer);

      // Also log to the database with 'local' status
      const userId = loggedInUser ? loggedInUser.ID : 1;
      const brId = loggedInUser ? loggedInUser.br_id : 1;

      const currentDate = new Date().toISOString().split('T')[0];
      const currentTime = new Date().toTimeString().split(' ')[0];

      const query = `
        INSERT INTO web_images
        (br_id, imgID, imgName, itmName, type, user_id, date, time, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      `;

      const imgID = Date.now();

      const [result] = await db.connection.execute(query, [
        brId,           // br_id
        imgID,          // imgID
        filename,       // imgName
        'Work Session Recording Segment', // itmName
        'recording',    // type
        userId,         // user_id
        currentDate,    // date
        currentTime,    // time
        'local-fallback' // status indicating it was saved locally due to upload failure
      ]);

      return {
        success: false,
        id: result.insertId,
        error: error.message,
        message: `Upload failed, saved locally instead. Error: ${error.message}`
      };
    } catch (fallbackError) {
      console.error('Fallback save also failed:', fallbackError);
      return {
        success: false,
        error: error.message,
        fallbackError: fallbackError.message,
        message: `Upload failed and local fallback also failed. Upload error: ${error.message}, Fallback error: ${fallbackError.message}`
      };
    }
  }
});
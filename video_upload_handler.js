const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');
const url = require('url');

// Function to upload a single file
async function uploadFile(filePath, serverUrl, userId) {
  return new Promise((resolve, reject) => {
    const stats = fs.statSync(filePath);
    const fileSize = stats.size;
    
    // Prepare form data for upload
    const boundary = '----formdata-unique-' + Date.now();
    let body = '';
    
    // Add file field
    body += '--' + boundary + '\r\n';
    body += 'Content-Disposition: form-data; name="file"; filename="' + path.basename(filePath) + '"\r\n';
    body += 'Content-Type: video/webm\r\n\r\n';
    
    const head = Buffer.from(body, 'utf-8');
    const tail = Buffer.from('\r\n--' + boundary + '--\r\n', 'utf-8');
    
    const totalSize = head.length + fileSize + tail.length;
    
    const parsedUrl = url.parse(serverUrl);
    
    const options = {
      hostname: parsedUrl.hostname,
      port: parsedUrl.port || (parsedUrl.protocol === 'https:' ? 443 : 80),
      path: parsedUrl.path,
      method: 'POST',
      headers: {
        'Content-Type': 'multipart/form-data; boundary=' + boundary,
        'Content-Length': totalSize,
        'User-Agent': 'XPloyee Background Upload Handler'
      }
    };
    
    const req = (parsedUrl.protocol === 'https:' ? https : http).request(options, (res) => {
      let responseData = '';
      
      res.on('data', (chunk) => {
        responseData += chunk;
      });
      
      res.on('end', () => {
        try {
          const response = JSON.parse(responseData);
          if (response.success) {
            console.log(`Successfully uploaded ${path.basename(filePath)}`);
            // Remove the file after successful upload
            fs.unlinkSync(filePath);
            resolve(true);
          } else {
            console.error(`Failed to upload ${path.basename(filePath)}:`, response.error || response.message);
            resolve(false);
          }
        } catch (e) {
          console.error(`Error parsing response for ${path.basename(filePath)}:`, e.message);
          console.error('Response data:', responseData);
          resolve(false);
        }
      });
    });
    
    req.on('error', (e) => {
      console.error(`Request error for ${path.basename(filePath)}:`, e.message);
      reject(e);
    });
    
    // Write the form data
    req.write(head);
    
    // Stream the file
    const readStream = fs.createReadStream(filePath);
    readStream.pipe(req, { end: false });
    
    readStream.on('end', () => {
      req.write(tail);
      req.end();
    });
  });
}

// Function to get all video files in a directory
function getVideoFiles(dir) {
  const files = fs.readdirSync(dir);
  return files.filter(file => path.extname(file).toLowerCase() === '.webm');
}

// Main function to process pending uploads
async function processPendingUploads() {
  console.log('Starting background video upload process...');
  
  // Get data from command-line arguments
  let userId = null;
  let uploadDir = path.join(__dirname, 'uploads');
  let serverUrl = 'https://powersoftt.com/xRemote/upload.php'; // Default URL
  
  // Parse command-line arguments
  const args = process.argv.slice(2);
  for (let i = 0; i < args.length; i += 2) {
    const arg = args[i];
    const value = args[i + 1];
    
    if (arg === '--userId' && value !== undefined) {
      userId = value;
    } else if (arg === '--uploadDir' && value !== undefined) {
      uploadDir = value;
    } else if (arg === '--serverUrl' && value !== undefined) {
      serverUrl = value;
    }
  }
  
  try {
    // Check if upload directory exists
    if (!fs.existsSync(uploadDir)) {
      console.log('Upload directory does not exist:', uploadDir);
      return;
    }
    
    // Get all video files to upload
    const videoFiles = getVideoFiles(uploadDir);
    
    if (videoFiles.length === 0) {
      console.log('No video files to upload.');
      return;
    }
    
    console.log(`Found ${videoFiles.length} video files to upload.`);
    
    // Upload each file
    for (const file of videoFiles) {
      const filePath = path.join(uploadDir, file);
      
      try {
        console.log(`Uploading ${file}...`);
        const success = await uploadFile(filePath, serverUrl, userId);
        
        if (success) {
          console.log(`Completed upload of ${file}`);
        } else {
          console.log(`Failed to upload ${file}, will retry later`);
          // Could implement retry logic here if needed
        }
      } catch (error) {
        console.error(`Error uploading ${file}:`, error.message);
      }
    }
    
    console.log('Background video upload process completed.');
  } catch (error) {
    console.error('Error in background video upload process:', error.message);
  }
}

// Run the upload process
processPendingUploads()
  .then(() => {
    console.log('Video upload process finished.');
    process.exit(0);
  })
  .catch((error) => {
    console.error('Video upload process failed:', error.message);
    process.exit(1);
  });
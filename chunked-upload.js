/**
 * Chunked upload function to handle large files that exceed server limits
 */
async function uploadInChunks(fileBuffer, filename, serverUrl, userId, brId, chunkSize = 1024 * 1024) { // 1MB chunks by default
    const totalSize = fileBuffer.length;
    const chunks = Math.ceil(totalSize / chunkSize);
    
    console.log(`Starting chunked upload: ${totalSize} bytes in ${chunks} chunks`);
    
    // Generate a unique identifier for this file upload
    const chunkFilename = `${Date.now()}_${Math.random().toString(36).substring(2, 15)}_${filename}`;
    
    for (let i = 0; i < chunks; i++) {
        const start = i * chunkSize;
        const end = Math.min(start + chunkSize, totalSize);
        const chunkBuffer = fileBuffer.slice(start, end);
        
        // Create form data for this chunk
        const formData = new FormData();
        const blob = new Blob([chunkBuffer], { type: 'video/webm' }); // Assuming webm, adjust as needed
        
        formData.append('file', blob, filename);
        formData.append('chunk', i);
        formData.append('chunks', chunks);
        formData.append('chunk_filename', chunkFilename);
        formData.append('userId', userId);
        formData.append('brId', brId);
        formData.append('filename', filename);
        formData.append('type', 'recording');
        formData.append('description', 'Work Session Recording Segment');
        
        console.log(`Uploading chunk ${i + 1}/${chunks} (${end - start} bytes)`);
        
        try {
            const response = await fetch(serverUrl, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`Chunk ${i} upload failed: ${response.status} ${response.statusText}`);
            }
            
            const result = await response.json();
            if (!result.success && !result.message?.includes('saved')) {
                throw new Error(`Chunk ${i} upload failed: ${JSON.stringify(result)}`);
            }
            
            console.log(`Chunk ${i + 1}/${chunks} uploaded successfully`);
        } catch (error) {
            console.error(`Error uploading chunk ${i}:`, error);
            throw error;
        }
    }
    
    console.log('All chunks uploaded successfully');
    
    // At this point, the server should have combined all chunks
    // We need to trigger the final processing step
    const finalFormData = new FormData();
    finalFormData.append('chunk', chunks - 1); // Indicate this is the last chunk
    finalFormData.append('chunks', chunks);
    finalFormData.append('chunk_filename', chunkFilename);
    finalFormData.append('userId', userId);
    finalFormData.append('brId', brId);
    finalFormData.append('filename', filename);
    finalFormData.append('type', 'recording');
    finalFormData.append('description', 'Work Session Recording Segment');
    
    const finalResponse = await fetch(serverUrl, {
        method: 'POST',
        body: finalFormData
    });
    
    if (!finalResponse.ok) {
        throw new Error(`Final processing failed: ${finalResponse.status} ${finalResponse.statusText}`);
    }
    
    return await finalResponse.json();
}

module.exports = { uploadInChunks };
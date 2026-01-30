<?php 
session_start();
// It is critical to have authentication here. This is just a placeholder example.
// if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
//     die('Access Denied. This tool is for administrators only.');
// }

include('db.php'); 

// Check if the DB connection from db.php was successful
if (!$conn) {
    die('Database connection failed. Please check db.php and server logs.');
}

?>
<html>
    <head>
        <title>xPower Q - Database Editor</title>
        <style>
            body { font-family: sans-serif; margin: 20px; background-color: #f4f4f9; color: #333; }
            h1 { color: #555; }
            .saveBtn { margin-top: 10px; width: 200px; height: 40px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
            .saveBtn:hover { background-color: #0056b3; }
            textarea { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-family: monospace; }
            table { border-collapse: collapse; margin-top: 20px; width: 100%; background-color: white; box-shadow: 0 2px 3px rgba(0,0,0,0.1); }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            th { background-color: #4CAF50; color: white; }
            .error { color: #D8000C; background-color: #FFD2D2; border: 1px solid #D8000C; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
            .success { color: #4F8A10; background-color: #DFF2BF; border: 1px solid #4F8A10; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
            .security-warning { color: #9F6000; background-color: #FEEFB3; border: 1px solid #9F6000; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        </style>
    </head>
    
    <body>
        <h1>Simple DB Editor</h1>
        <p class="security-warning"><strong>Warning:</strong> This tool is a major security risk. It should be deleted from a public server immediately after use.</p>
        
        <?php
        $textQuery = '';
        if (isset($_POST['btn_save'])) {
            $textQuery = $_POST['textquery'];
            
            // Use the correct connection variable $conn from db.php
            $sql_runQry = $conn->query($textQuery);
            
            // Add error handling for the query
            if ($sql_runQry === false) {
                echo '<div class="error"><strong>Query Failed:</strong><br>' . htmlspecialchars($conn->error) . '</div>';
            } elseif ($sql_runQry === true) {
                 // Handle successful non-SELECT queries (e.g., UPDATE, INSERT, DELETE)
                echo '<div class="success">Query executed successfully. ' . $conn->affected_rows . ' rows affected.</div>';
            } else {
                // Handle successful SELECT query
                echo '<div class="success">'.$sql_runQry->num_rows.' rows found.</div>';
                echo '<table><thead><tr>';
                
                $fieldArray = [];
                while ($fieldinfo = $sql_runQry->fetch_field()) {
                   array_push($fieldArray, $fieldinfo->name);
                    echo '<th>' . htmlspecialchars($fieldinfo->name) . '</th>';
                }
                echo '</tr></thead><tbody>';
                
                while ($runQry = $sql_runQry->fetch_assoc()) {
                    echo '<tr>';
                    foreach ($fieldArray as $fieldNm) {
                        // Use htmlspecialchars to prevent XSS from database content
                        echo '<td>' . htmlspecialchars($runQry[$fieldNm] ?? 'NULL') . '</td>';
                    }
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
                $sql_runQry->free(); // Free result set
            }
        }
        ?>

        <!-- The action attribute should be empty to post to the current page -->
        <form action="" method="POST" style="width: 100%; margin-top: 25px;">
            <label for="textquery">Write your query here:</label>
            <textarea id="textquery" rows="15" name="textquery"><?php echo htmlspecialchars($textQuery); ?></textarea>
            
            <input type="submit" class="saveBtn" name="btn_save" value="RUN QUERY" />
        </form>
    </body>
</html>
<?php
// Close the connection at the end of the script
if ($conn) {
    $conn->close();
}
?>
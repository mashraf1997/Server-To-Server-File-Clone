<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download File with Auto-Resume</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .progress-container {
            width: 100%;
            background: #f3f3f3;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-bar {
            height: 30px;
            width: 0;
            background: #4caf50;
            text-align: center;
            line-height: 30px;
            color: white;
            transition: width 0.5s;
        }
        .message {
            margin-top: 10px;
        }
        form {
            margin-bottom: 20px;
        }
        input[type="text"] {
            width: 80%;
            padding: 10px;
            margin-right: 10px;
        }
        input[type="submit"] {
            padding: 10px 20px;
        }
    </style>
</head>
<body>

<h1>Download File with Auto-Resume</h1>

<!-- Form to input the URL -->
<form method="POST" action="">
    <input type="text" name="file_url" placeholder="Enter file URL here..." required>
    <input type="submit" value="Download">
</form>

<div class="progress-container">
    <div id="progress-bar" class="progress-bar">0%</div>
</div>
<div id="message" class="message"></div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['file_url'])) {
    ini_set('max_execution_time', 0);  // Unlimited execution time
    ini_set('memory_limit', '2G');  // Increase memory limit to avoid running out of memory

    /* Get the URL from the form */
    $remote_file_url = filter_var(trim($_POST['file_url']), FILTER_SANITIZE_URL);

    /* Validate the URL */
    if (!filter_var($remote_file_url, FILTER_VALIDATE_URL)) {
        echo "<script>document.getElementById('message').innerText = 'Invalid URL!';</script>";
    } else {
        /* Extract file name from the URL */
        $local_file = basename($remote_file_url);

        /* Check if file already exists and get its size for resuming */
        $local_file_size = 0;
        if (file_exists($local_file)) {
            $local_file_size = filesize($local_file);
        }

        /* Open file in append mode if it exists, or create it if not */
        $fp = fopen($local_file, 'a+'); // Open in append mode to resume

        if (!$fp) {
            die("Failed to open local file for writing.");
        }

        /* Initialize cURL */
        $ch = curl_init();

        /* Set cURL options */
        curl_setopt($ch, CURLOPT_URL, $remote_file_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Return the transfer as a string
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // Follow redirects
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024 * 1024); // Buffer size to 1MB

        /* Set the Range header if the file already exists */
        if ($local_file_size > 0) {
            curl_setopt($ch, CURLOPT_RANGE, $local_file_size . '-');
        }

        /* Execute the cURL session */
        $file_data = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "<script>document.getElementById('message').innerText = 'Error: " . curl_error($ch) . "';</script>";
        } else {
            /* Check the Content-Length header to determine the total file size */
            $total_size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) + $local_file_size;

            /* Write the file in chunks */
            $chunk_size = 1024 * 1024; // 1MB chunk size
            $bytes_written = 0;

            while ($bytes_written < strlen($file_data)) {
                $chunk = substr($file_data, $bytes_written, $chunk_size);
                fwrite($fp, $chunk);
                $bytes_written += $chunk_size;

                /* Calculate the progress */
                $progress = (($bytes_written + $local_file_size) / $total_size) * 100;

                /* Update progress bar */
                echo "<script>
                    document.getElementById('progress-bar').style.width = '$progress%';
                    document.getElementById('progress-bar').innerText = '" . round($progress, 2) . "%';
                    document.getElementById('message').innerText = 'Downloading...';
                    </script>";
                flush();
            }

            echo "<script>document.getElementById('message').innerText = 'Download completed successfully!';</script>";
        }

        /* Close cURL and file handler */
        curl_close($ch);
        fclose($fp);
    }
}
?>

</body>
</html>

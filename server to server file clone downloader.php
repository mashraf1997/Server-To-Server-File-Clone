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
/*
 * Security model for a server-side downloader
 * -------------------------------------------
 * A tool that fetches a user-supplied URL is a classic SSRF and RCE risk.
 * The defenses below are deliberate; do not remove them:
 *
 *  1. Only http/https are allowed (no file://, gopher://, ftp://, ...),
 *     for the initial request AND for redirects.
 *  2. The target host is resolved and rejected if it points at a private,
 *     loopback, link-local or otherwise reserved IP address, so the server
 *     cannot be tricked into fetching internal services or cloud metadata
 *     (e.g. 169.254.169.254).
 *  3. Downloads are written into a dedicated ./downloads directory, never
 *     the web root, and the filename is sanitized. Extensions that a web
 *     server might execute (php, phtml, cgi, ...) are neutralized so a
 *     downloaded file can never become executable code on this host.
 *  4. Errors are logged, not printed, so internal paths don't leak.
 */

/** Reject URLs whose host resolves to a non-public IP address (SSRF guard). */
function host_is_public($host)
{
    $ips = array();
    $records = @dns_get_record($host, DNS_A + DNS_AAAA);
    if ($records) {
        foreach ($records as $r) {
            if (isset($r['ip'])) { $ips[] = $r['ip']; }
            if (isset($r['ipv6'])) { $ips[] = $r['ipv6']; }
        }
    }
    // Also handle the case where the host is already a literal IP.
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;
    }
    if (empty($ips)) {
        return false; // cannot resolve -> refuse
    }
    foreach ($ips as $ip) {
        // NO_PRIV_RANGE and NO_RES_RANGE reject 10/8, 172.16/12, 192.168/16,
        // 127/8, 169.254/16, ::1, fc00::/7, etc.
        if (!filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            return false;
        }
    }
    return true;
}

/** Turn a remote URL into a safe local filename inside the downloads dir. */
function safe_local_name($url)
{
    $name = basename((string) parse_url($url, PHP_URL_PATH));
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $name);
    $name = ltrim($name, '.');                 // no leading dots / hidden files
    if ($name === '') {
        $name = 'download-' . date('Ymd-His');
    }
    // Neutralize extensions the web server might execute.
    $dangerous = array('php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
                       'pht', 'cgi', 'pl', 'py', 'sh', 'htaccess');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, $dangerous, true)) {
        $name .= '.txt';
    }
    return $name;
}

function fail($message)
{
    echo "<script>document.getElementById('message').innerText = "
        . json_encode($message) . ";</script>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['file_url'])) {
    // Log errors instead of displaying them (avoid leaking server paths).
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
    ini_set('max_execution_time', 0);
    ini_set('memory_limit', '1024M');

    $remote_file_url = filter_var(trim($_POST['file_url']), FILTER_SANITIZE_URL);

    $scheme = strtolower((string) parse_url($remote_file_url, PHP_URL_SCHEME));
    $host   = parse_url($remote_file_url, PHP_URL_HOST);

    if (!filter_var($remote_file_url, FILTER_VALIDATE_URL) ||
        !in_array($scheme, array('http', 'https'), true) ||
        !$host) {
        fail('Invalid URL. Only http:// and https:// links are allowed.');
    } elseif (!host_is_public($host)) {
        fail('This host is not allowed.');
    } else {
        // Store downloads outside the web root's script directory.
        $download_dir = __DIR__ . DIRECTORY_SEPARATOR . 'downloads';
        if (!is_dir($download_dir) && !mkdir($download_dir, 0755, true) && !is_dir($download_dir)) {
            die('Could not create the downloads directory.');
        }
        if (!is_writable($download_dir)) {
            die('The downloads directory is not writable.');
        }

        $local_file = $download_dir . DIRECTORY_SEPARATOR . safe_local_name($remote_file_url);

        // Resume support: continue from the size already on disk.
        $local_file_size = file_exists($local_file) ? filesize($local_file) : 0;

        $fp = fopen($local_file, 'a+');
        if (!$fp) {
            die('Failed to open local file for writing.');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remote_file_url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        // Restrict protocols for both the request and any redirects.
        if (defined('CURLPROTO_HTTP')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);      // no total timeout (large files)
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 128 * 1024);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);

        if ($local_file_size > 0) {
            curl_setopt($ch, CURLOPT_RANGE, $local_file_size . '-');
        }

        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION,
            function ($resource, $download_size, $downloaded) use ($local_file_size) {
                if ($download_size > 0) {
                    $downloaded += $local_file_size;
                    $total_size = $download_size + $local_file_size;
                    $progress = ($downloaded / $total_size) * 100;
                    echo "<script>
                        document.getElementById('progress-bar').style.width = '" . (float) $progress . "%';
                        document.getElementById('progress-bar').innerText = '" . round($progress, 2) . "%';
                        document.getElementById('message').innerText = 'Downloading...';
                        </script>";
                    flush();
                }
            }
        );

        curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('Downloader cURL error: ' . curl_error($ch));
            fail('Download failed. Please check the URL and try again.');
        } else {
            fail('Download completed successfully!');
        }

        curl_close($ch);
        fclose($fp);
    }
}
?>

</body>
</html>

# File Downloader with Auto-Resume

This project is a simple web-based file downloader built with HTML, CSS, and PHP. It allows users to download files from a given URL and supports resuming downloads if they are interrupted. The progress of the download is displayed on a progress bar in real-time.

## Features

- **Auto-Resume**: If the download is interrupted, it will resume from where it left off.
- **Progress Bar**: Displays the percentage of the file that has been downloaded.
- **User Input**: Users can enter the file URL to initiate the download.
- **PHP Backend**: Uses cURL to handle the download process and stream the file in chunks.

## Technologies Used

- **HTML**: For the structure of the web page.
- **CSS**: For styling the progress bar and layout.
- **PHP**: For handling file download and auto-resume functionality.
- **cURL**: To fetch files from remote URLs and handle partial downloads.

## Installation

To use this downloader, follow these steps:

1. Clone the repository:
   git clone https://github.com/mashraf1997/Server-To-Server-File-Clone.git
2. Place the project in your web server's root directory (e.g., `/var/www/html` for Apache).
3. Ensure PHP is installed and configured on your server.
4. Grant write permissions to the web server user on the directory to allow file downloads.

## Usage

1. Open the application in a web browser.
2. Enter the URL of the file you wish to download in the input field.
3. Click on "Download" to start downloading the file.
4. The progress bar will show the download status, and the file will be saved on the server.

## Requirements

- PHP 7.0 or higher
- A web server like Apache or Nginx
- cURL extension enabled in PHP

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.

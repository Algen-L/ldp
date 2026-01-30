<?php
/**
 * Global Error and Exception Handler
 * Logs errors to a file and displays user-friendly messages.
 */

// Error logging configuration
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

// Ensure stats/logs directory exists
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

/**
 * Custom Error Handler
 */
function customErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return false;
    }

    $message = "Error: [$errno] $errstr in $errfile on line $errline";
    error_log($message);

    // If it's a fatal error, we might want to stop execution
    switch ($errno) {
        case E_USER_ERROR:
            echo "<b>My ERROR</b> [$errno] $errstr<br />\n";
            echo "  Fatal error on line $errline in file $errfile";
            echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
            echo "Aborting...<br />\n";
            exit(1);
            break;

        case E_USER_WARNING:
        case E_WARNING:
            // error_log("WARNING: $message");
            break;

        case E_USER_NOTICE:
        case E_NOTICE:
            // error_log("NOTICE: $message");
            break;

        default:
            // error_log("Unknown error type: [$errno] $errstr");
            break;
    }

    /* Don't execute PHP internal error handler */
    return true;
}

/**
 * Custom Exception Handler
 */
function customExceptionHandler($exception)
{
    $message = "Uncaught Exception: " . $exception->getMessage() .
        " in " . $exception->getFile() .
        " on line " . $exception->getLine() .
        "\nStack trace: " . $exception->getTraceAsString();

    error_log($message);

    // Display a user-friendly error page or message
    // Check if we are in an API request (AJAX)
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'An internal server error occurred.',
            'debug' => $exception->getMessage() // Remove 'debug' in production
        ]);
    } else {
        // Simple error page output
        if (!headers_sent()) {
            http_response_code(500);
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>System Error</title>
            <style>
                body {
                    font-family: -apple-system, sans-serif;
                    background: #f8fafc;
                    color: #334155;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    margin: 0;
                }

                .error-container {
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                    max-width: 500px;
                    text-align: center;
                }

                h1 {
                    color: #ef4444;
                    margin-top: 0;
                }

                p {
                    line-height: 1.5;
                }

                .btn {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 20px;
                    background: #0f4c75;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: 600;
                }
            </style>
        </head>

        <body>
            <div class="error-container">
                <h1>Oops! Something went wrong.</h1>
                <p>We've logged the error and our team has been notified. Please try again later.</p>
                <a href="/ldp/index.php" class="btn">Return Home</a>
            </div>
        </body>

        </html>
        <?php
    }
    exit;
}

// Set global handlers
set_error_handler("customErrorHandler");
set_exception_handler("customExceptionHandler");

// Handle fatal errors that bypass set_error_handler
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $message = "Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
        error_log($message);
        // We can't really show a pretty page here easily if headers are sent, but we can try
        if (!headers_sent()) {
            http_response_code(500);
            echo "<h1>System Fatal Error</h1><p>Please contact the administrator.</p>";
        }
    }
});
?>
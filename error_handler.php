<?php
// error_handler.php – Global Exception & Error Catcher
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // Convert errors into Exceptions to handle them uniformly
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

function customExceptionHandler($exception) {
    // Clean any partial HTML that was already output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log the error securely on the server (doesn't show to user)
    error_log("CampusFind Error: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());

    // Display the Beautiful Error Page
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>CampusFind – System Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            body { background: linear-gradient(-45deg, #0F172A, #1E293B, #312E81, #0F172A); background-size: 400% 400%; animation: gradientShift 15s ease infinite; color: white; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
            @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
            .glass-card { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 40px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); text-align: center; max-width: 500px; width: 90%; }
            .error-icon { font-size: 4rem; color: #EF4444; margin-bottom: 20px; animation: float 3s ease-in-out infinite; }
            @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
            .btn-glass { background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 12px; padding: 12px 24px; color: #A5B4FC; font-weight: 600; text-decoration: none; transition: all 0.3s; display: inline-block; margin-top: 20px; }
            .btn-glass:hover { background: rgba(99, 102, 241, 0.4); color: white; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="glass-card">
            <i class="bi bi-exclamation-triangle-fill error-icon"></i>
            <h2 class="fw-bold mb-3">Oops! Something went wrong.</h2>
            <p class="text-white-50 mb-4">Our servers encountered an unexpected issue. The error has been logged and our admins have been notified.</p>
            <a href="index.php" class="btn-glass"><i class="bi bi-house-door-fill me-2"></i> Return to Home</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Register the handlers
set_error_handler("customErrorHandler");
set_exception_handler("customExceptionHandler");
?>
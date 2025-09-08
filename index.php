<?php

/******************************************************************
 *  Google-AI-Studio image-to-image  (handles UPLOAD_ERR_INI_SIZE)
 ******************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_FILES['image'], $_POST['api_key'], $_POST['model'])) {

    header('Content-Type: application/json');
    error_reporting(E_ALL);
    ini_set('display_errors', 0);

    /* ---------- NEW: bump limits for this request only ---------- */
    ini_set('upload_max_filesize', '50M');
    ini_set('post_max_size', '50M');
    ini_set('max_input_time', '300');
    ini_set('max_execution_time', '300');

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR])) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'PHP fatal error', 'details' => $err]);
        }
    });

    $apiKey    = $_POST['api_key'];
    $model     = $_POST['model'];
    $creativity= (int)($_POST['creativity'] ?? 3);
    $file      = $_FILES['image'];

    /* ---------- human-readable upload errors ---------- */
    $uploadErrors = [
        1 => 'File is larger than upload_max_filesize (check PHP settings).',
        2 => 'File is larger than the form limit.',
        3 => 'File was only partially uploaded.',
        4 => 'No file was selected.',
        6 => 'Missing temporary folder on server.',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension blocked the upload.',
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $uploadErrors[$file['error']] ?? 'Unknown upload error.',
            'details' => ['raw_files' => $_FILES]
        ]);
        exit;
    }

    if (empty($apiKey) || empty($model)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'API key or model missing.']);
        exit;
    }

    /* ---------- everything below this line stays untouched ---------- */
    $mimeType     = $file['type'];
    $base64Image  = base64_encode(file_get_contents($file['tmp_name']));

   $prompts = [
        1 => "Subtly enhance the image. Focus on minor adjustments like lighting, contrast, and sharpness. Keep the original subject and composition.",
        2 => "Improve the image with noticeable enhancements. Adjust colors to be more vibrant and improve details. The overall scene should remain the same.",
        3 => "Apply creative filters and effects. The image should be clearly transformed but the original subject should be recognizable.",
        4 => "Reimagine the image with a different style. The core subject might be the same, but the artistic interpretation should be significantly different (e.g., painterly, abstract).",
        5 => "Generate a completely new and highly creative image based on the input. The original image should serve as a loose inspiration for a fantastical or surreal scene."
    ];

    $prompt = $prompts[$creativity] ?? $prompts[3];

    $data = [
        'contents' => [[
            'role' => 'user',
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]]
            ]
        ]]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/{$model}:generateContent?key={$apiKey}";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 180
    ]);

    curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
    curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 120);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        http_response_code($httpCode ?: 500);
        echo json_encode([
            'success' => false,
            'message' => 'Gemini API error',
            'details' => [
                'http_status' => $httpCode,
                'curl_error'  => $curlErr,
                'raw_response'=> $response
            ]
        ]);
        exit;
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON from Gemini', 'details' => $response]);
        exit;
    }

    $generatedImageData = null;
    if (isset($result['candidates'][0]['content']['parts'])) {
        foreach ($result['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData']['data'])) {
                $generatedImageData = $part['inlineData']['data'];
                break;
            }
        }
    }

    if ($generatedImageData) {
        echo json_encode([
            'success'   => true,
            'original'  => "data:{$mimeType};base64,{$base64Image}",
            'enhanced'  => "data:image/png;base64,{$generatedImageData}",
            'creativity'=> $creativity
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No enhanced image found in Gemini response',
            'details' => $result
        ]);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Image Enhancer - Transform Photos with Artificial Intelligence</title>
    <meta name="description" content="Enhance and transform your images using advanced AI technology. Upload any photo and let our artificial intelligence create stunning visual improvements automatically.">
    
    <!-- TailwindCSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        'primary-hover': '#5855eb'
                    }
                }
            }
        }
    </script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-color: #6366f1;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --border-light: #e2e8f0;
            --border-focus: #6366f1;
            --success-color: #10b981;
            --error-color: #ef4444;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.5;
        }

        /* Header */
        .app-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow-md);
            position: relative;
            z-index: 100;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .header-subtitle {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-top: 0.25rem;
        }
        
        .get-key-btn {
            margin-left: 0.5rem;
            padding: 0.25rem 0.6rem;
            background: #2575fc;
            color: #fff !important;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-block;
        }

        .settings-btn {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            backdrop-filter: blur(10px);
        }

/* Add this CSS to your existing <style> section, near the .settings-btn styles */

        .github-btn {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            backdrop-filter: blur(10px);
            text-decoration: none;
        }

        .github-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
            color: white;
        }

        .settings-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        /* Main Layout */
        .app-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 1.5rem;
            min-height: calc(100vh - 80px);
        }

        /* Main Content */
        .main-content {
            background: var(--bg-white);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .upload-section {
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid var(--border-light);
        }

        .upload-zone {
            width: 100%;
            max-width: 500px;
            height: 240px;
            margin: 0 auto;
            border: 2px dashed var(--border-light);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--bg-light);
            position: relative;
        }

        .upload-zone:hover, .upload-zone.drag-active {
            border-color: var(--accent-color);
            background: rgba(99, 102, 241, 0.05);
        }

        .upload-icon {
            width: 40px;
            height: 40px;
            margin-bottom: 1rem;
            opacity: 0.6;
        }

        .upload-text {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .upload-subtext {
            font-size: 0.875rem;
            color: var(--text-light);
        }

        .upload-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .image-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            object-fit: cover;
        }

        /* Controls */
        .controls-section {
            padding: 1.5rem 2rem;
        }

        .creativity-control {
            margin-bottom: 1.5rem;
        }

        .creativity-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
            display: block;
        }

        .creativity-slider-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .creativity-value {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--accent-color);
            min-width: 24px;
            text-align: center;
        }

        .creativity-slider {
            flex: 1;
            -webkit-appearance: none;
            height: 6px;
            border-radius: 3px;
            background: var(--border-light);
            outline: none;
            cursor: pointer;
        }

        .creativity-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--accent-color);
            cursor: pointer;
            box-shadow: var(--shadow-md);
            transition: all 0.2s;
        }

        .creativity-slider::-webkit-slider-thumb:hover {
            transform: scale(1.1);
        }

        .creativity-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-light);
            font-weight: 500;
        }

        .enhance-btn {
            width: 100%;
            padding: 0.75rem 1.5rem;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .enhance-btn:hover:not(:disabled) {
            background: #5855eb;
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .enhance-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Results */
        .results-section {
            padding: 2rem;
            border-top: 1px solid var(--border-light);
        }

        .results-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .image-section {
            text-align: center;
        }

        .image-section h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
        }

        .image-container {
            position: relative;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            overflow: hidden;
            background: var(--bg-light);
            aspect-ratio: 4/3;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .comparison-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .download-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            opacity: 0;
        }

        .image-container:hover .download-btn {
            opacity: 1;
        }

        .download-btn:hover {
            background: rgba(0, 0, 0, 0.9);
        }

        .results-empty {
            text-align: center;
            color: var(--text-light);
            padding: 3rem 1rem;
        }

        .results-empty svg {
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* History Panel */
        .history-panel {
            background: var(--bg-white);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .history-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-light);
            background: var(--bg-light);
        }

        .history-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
        }

        .clear-history-btn {
            padding: 0.5rem 0.75rem;
            background: var(--error-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .clear-history-btn:hover {
            background: #dc2626;
        }

        .history-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 0.75rem;
        }

        .history-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--bg-white);
        }

        .history-item:hover {
            border-color: var(--accent-color);
            box-shadow: var(--shadow-sm);
        }

        .history-thumbnails {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .history-thumbnail {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid var(--border-light);
        }

        .arrow-icon {
            font-size: 12px;
            color: var(--text-light);
        }

        .history-info {
            flex: 1;
            min-width: 0;
        }

        .history-creativity {
            font-size: 0.75rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .history-timestamp {
            font-size: 0.625rem;
            color: var(--text-light);
        }

        .empty-history {
            text-align: center;
            color: var(--text-light);
            font-size: 0.875rem;
            padding: 2rem 1rem;
        }

        /* Settings Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-white);
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            box-shadow: var(--shadow-xl);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn-primary {
            width: 100%;
            padding: 0.75rem 1.5rem;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: #5855eb;
        }

        /* Loading States */
        .loading-spinner {
            display: none;
            width: 24px;
            height: 24px;
            border: 2px solid var(--border-light);
            border-top: 2px solid var(--accent-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        .loading-spinner.active {
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Notifications */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            animation: slideInRight 0.3s ease-out;
            max-width: 320px;
            word-wrap: break-word;
        }

        .notification-error {
            background: var(--error-color);
        }

        .notification-success {
            background: var(--success-color);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .app-container {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1rem;
            }

            .sidebar {
                order: -1;
            }

            .history-list {
                max-height: 200px;
            }
        }

        @media (max-width: 768px) {
            .app-header {
                padding: 1rem;
            }

            .header-title {
                font-size: 1.25rem;
            }

            .upload-section {
                padding: 1.5rem 1rem;
            }

            .upload-zone {
                height: 180px;
            }

            .controls-section {
                padding: 1rem;
            }

            .results-section {
                padding: 1rem;
            }

            .comparison-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .modal-content {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <!-- Header -->
    <header class="app-header">
        <div class="header-content">
            <div>
                <h1 class="header-title">Universal Image Enhancer</h1>
                <p class="header-subtitle">Let AI creatively enhance your images</p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <a href="https://github.com/hemangjoshi37a/Universal_Image_Enhancer" 
                target="_blank" 
                rel="noopener noreferrer"
                class="github-btn" 
                title="View on GitHub">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                </a>
                <button class="settings-btn" id="settings-btn" title="Settings">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="app-container">
        <!-- Main Content -->
        <main class="main-content">
            <!-- Upload Section -->
            <section class="upload-section">
                <div class="upload-zone" id="upload-zone">
                    <input type="file" class="upload-input" id="image-input" accept="image/*" aria-label="Upload image">
                    <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"></path>
                    </svg>
                    <div class="upload-text">Drop your image here</div>
                    <div class="upload-subtext">or click to browse • JPEG, PNG, GIF, WebP</div>
                </div>
            </section>

            <!-- Controls Section -->
            <section class="controls-section">
                <div class="creativity-control">
                    <label class="creativity-label" for="creativity-slider">Enhancement Level</label>
                    <div class="creativity-slider-container">
                        <span class="creativity-value">1</span>
                        <input type="range" class="creativity-slider" id="creativity-slider" min="1" max="5" value="3" aria-label="Enhancement level">
                        <span class="creativity-value">5</span>
                        <span class="creativity-value" id="creativity-display">3</span>
                    </div>
                    <div class="creativity-labels">
                        <span>Subtle</span>
                        <span>Enhance</span>
                        <span>Creative</span>
                        <span>Artistic</span>
                        <span>Fantasy</span>
                    </div>
                </div>
                
                <button class="enhance-btn" id="enhance-btn" disabled>
                    <div class="loading-spinner" id="loading-spinner"></div>
                    <span id="enhance-btn-text">Select Image to Enhance</span>
                </button>
            </section>

            <!-- Results Section -->
            <section class="results-section" id="results-section">
                <div class="results-empty" id="results-empty">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.5">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="9" cy="9" r="2"></circle>
                        <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
                    </svg>
                    <p>Your enhanced images will appear here</p>
                </div>
                
                <div class="comparison-container" id="comparison-container" style="display: none;">
                    <h2 class="results-title">Comparison</h2>
                    <div class="comparison-grid">
                        <div class="image-section">
                            <h4>Original</h4>
                            <div class="image-container">
                                <img class="comparison-image" id="original-image" alt="Original image">
                            </div>
                        </div>
                        
                        <div class="image-section">
                            <h4>Enhanced</h4>
                            <div class="image-container">
                                <img class="comparison-image" id="enhanced-image" alt="Enhanced image">
                                <button class="download-btn" id="download-btn" title="Download enhanced image">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- History Panel -->
            <div class="history-panel">
                <div class="history-header">
                    <h2 class="history-title">Enhancement History</h2>
                    <button class="clear-history-btn" id="clear-history-btn">Clear All</button>
                </div>
                <div class="history-list" id="history-list">
                    <div class="empty-history">No enhancements yet</div>
                </div>
            </div>
        </aside>
    </div>

    <!-- Settings Modal -->
    <div class="modal" id="settings-modal">
        <div class="modal-content">
            <h2 class="modal-title">API Settings</h2>
            
                        
            <div class="form-group">
                <label for="modal-api-key">
                    Gemini API Key
                    <a  href="https://aistudio.google.com/apikey"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="get-key-btn">
                        Get API Key →
                    </a>
                </label>
                <input type="password" id="modal-api-key" placeholder="Enter your Gemini API Key">
            </div>
                        
            <div class="form-group">
                <label class="form-label" for="modal-model">Select Model</label>
                <select class="form-input" id="modal-model">
                    <option value="">Loading models...</option>
                </select>
            </div>
            <button class="btn-primary" id="save-settings-btn">Save Settings</button>
        </div>
    </div>

    <script>
        class ImageEnhancer {
            constructor() {
                this.initElements();
                this.initEventListeners();
                this.loadSettings();
                this.loadHistory();
                this.currentFile = null;
            }

            initElements() {
                // Main elements
                this.uploadZone = document.getElementById('upload-zone');
                this.imageInput = document.getElementById('image-input');
                this.creativitySlider = document.getElementById('creativity-slider');
                this.creativityDisplay = document.getElementById('creativity-display');
                this.enhanceBtn = document.getElementById('enhance-btn');
                this.enhanceBtnText = document.getElementById('enhance-btn-text');
                this.loadingSpinner = document.getElementById('loading-spinner');
                
                // Results elements
                this.resultsSection = document.getElementById('results-section');
                this.resultsEmpty = document.getElementById('results-empty');
                this.comparisonContainer = document.getElementById('comparison-container');
                this.originalImage = document.getElementById('original-image');
                this.enhancedImage = document.getElementById('enhanced-image');
                this.downloadBtn = document.getElementById('download-btn');
                
                // History elements
                this.historyList = document.getElementById('history-list');
                this.clearHistoryBtn = document.getElementById('clear-history-btn');
                
                // Settings elements
                this.settingsBtn = document.getElementById('settings-btn');
                this.settingsModal = document.getElementById('settings-modal');
                this.modalApiKey = document.getElementById('modal-api-key');
                this.modalModel = document.getElementById('modal-model');
                this.saveSettingsBtn = document.getElementById('save-settings-btn');
                
                // Verify critical elements exist
                const criticalElements = [
                    'uploadZone', 'imageInput', 'creativitySlider', 'enhanceBtn',
                    'settingsBtn', 'settingsModal', 'modalApiKey', 'modalModel'
                ];
                
                for (const element of criticalElements) {
                    if (!this[element]) {
                        console.error(`Critical element missing: ${element}`);
                        this.showError(`Application error: Missing ${element}. Please refresh the page.`);
                    }
                }
                
                // State
                this.currentHistory = [];
                this.isProcessing = false;
            }

            initEventListeners() {
                // Upload zone events with null checks
                if (this.uploadZone) {
                    this.uploadZone.addEventListener('click', (e) => {
                        if (e.target === this.uploadZone || e.target.closest('.upload-icon, .upload-text, .upload-subtext')) {
                            if (this.imageInput) this.imageInput.click();
                        }
                    });
                    this.uploadZone.addEventListener('dragover', this.handleDragOver.bind(this));
                    this.uploadZone.addEventListener('dragleave', this.handleDragLeave.bind(this));
                    this.uploadZone.addEventListener('drop', this.handleDrop.bind(this));
                }
                
                // File input
                if (this.imageInput) {
                    this.imageInput.addEventListener('change', this.handleFileSelect.bind(this));
                }
                
                // Creativity slider
                if (this.creativitySlider) {
                    this.creativitySlider.addEventListener('input', this.updateCreativityDisplay.bind(this));
                }
                
                // Enhance button
                if (this.enhanceBtn) {
                    this.enhanceBtn.addEventListener('click', this.enhanceImage.bind(this));
                }
                
                // Settings modal
                if (this.settingsBtn) {
                    this.settingsBtn.addEventListener('click', this.openSettings.bind(this));
                }
                if (this.saveSettingsBtn) {
                    this.saveSettingsBtn.addEventListener('click', this.saveSettings.bind(this));
                }
                if (this.settingsModal) {
                    this.settingsModal.addEventListener('click', this.closeModalOnBackdrop.bind(this));
                }
                if (this.modalApiKey) {
                    this.modalApiKey.addEventListener('input', this.debounce(this.fetchModels.bind(this), 500));
                }
                
                // History
                if (this.clearHistoryBtn) {
                    this.clearHistoryBtn.addEventListener('click', this.clearHistory.bind(this));
                }
                
                // Download
                if (this.downloadBtn) {
                    this.downloadBtn.addEventListener('click', this.downloadImage.bind(this));
                }
                
                // Keyboard shortcuts
                document.addEventListener('keydown', this.handleKeydown.bind(this));
            }

            debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            handleDragOver(e) {
                e.preventDefault();
                this.uploadZone.classList.add('drag-active');
            }

            handleDragLeave(e) {
                e.preventDefault();
                if (!this.uploadZone.contains(e.relatedTarget)) {
                    this.uploadZone.classList.remove('drag-active');
                }
            }

            handleDrop(e) {
                e.preventDefault();
                this.uploadZone.classList.remove('drag-active');
                const files = e.dataTransfer.files;
                if (files.length > 0 && files[0].type.startsWith('image/')) {
                    this.selectFile(files[0]);
                }
            }

            handleFileSelect(e) {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    this.selectFile(file);
                }
            }

            selectFile(file) {
                this.currentFile = file;
                this.updateUploadZonePreview(file);
                this.enhanceBtn.disabled = false;
                this.enhanceBtnText.textContent = 'Enhance Image';
            }

            updateUploadZonePreview(file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.uploadZone.innerHTML = `
                        <input type="file" class="upload-input" accept="image/*" aria-label="Upload image">
                        <img src="${e.target.result}" class="image-preview" alt="Selected image preview">
                        <div class="upload-text">Click to select different image</div>
                        <div class="upload-subtext">or drag and drop a new one</div>
                    `;
                    
                    // Re-attach event listener to new input
                    const newInput = this.uploadZone.querySelector('.upload-input');
                    newInput.addEventListener('change', this.handleFileSelect.bind(this));
                };
                reader.readAsDataURL(file);
            }

            updateCreativityDisplay() {
                this.creativityDisplay.textContent = this.creativitySlider.value;
            }





            async enhanceImage() {
    if (this.isProcessing || !this.currentFile) return;

    const apiKey = localStorage.getItem('geminiApiKey');
    const model = localStorage.getItem('geminiModel');

    if (!apiKey || !model) {
        this.showError('Please configure your API key and model in settings.');
        this.openSettings();
        return;
    }

    this.setProcessing(true);

    try {
        const formData = new FormData();
        formData.append('image', this.currentFile);
        formData.append('api_key', apiKey);
        formData.append('model', model);
        formData.append('creativity', this.creativitySlider.value);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        /* ---------- NEW: dump every non-200 body to console ---------- */
        if (!response.ok) {
            const clone = response.clone();          // fetch body can only be read once
            try {
                const details = await clone.json();
                console.error('PHP/ Gemini rejection →', details);   // ← this prints in Chrome console
            } catch (_) {
                console.error('Non-JSON error body →', await clone.text());
            }
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            this.showResults(result);
            this.addToHistory(result);
            this.showSuccess('Image enhanced successfully!');
        } else {
            this.showError(result.message || 'Enhancement failed. Please try again.');
            console.error('API Error:', result);
        }
    } catch (error) {
        console.error('Network Error:', error);
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            this.showError('Network connection failed. Please check your internet connection.');
        } else if (error.message.includes('HTTP error')) {
            this.showError(`Server error: ${error.message}`);
        } else {
            this.showError('An unexpected error occurred. Please try again.');
        }
    } finally {
        this.setProcessing(false);
    }
}



            setProcessing(processing) {
                this.isProcessing = processing;
                this.enhanceBtn.disabled = processing;
                this.loadingSpinner.classList.toggle('active', processing);
                
                if (processing) {
                    this.enhanceBtnText.textContent = 'Enhancing...';
                } else {
                    this.enhanceBtnText.textContent = this.currentFile ? 'Enhance Image' : 'Select Image to Enhance';
                }
            }

            showResults(result) {
                this.resultsEmpty.style.display = 'none';
                this.comparisonContainer.style.display = 'block';
                
                this.originalImage.src = result.original;
                this.enhancedImage.src = result.enhanced;
                this.enhancedImage.dataset.downloadUrl = result.enhanced;
            }

/* ---------- history entry (SMALL) ---------- */
addToHistory(result) {
    const thumb = (src, w = 200) => {               // tiny data-URL
        const img = new Image();
        img.src = src;
        return new Promise(res => {
            img.onload = () => {
                const c = document.createElement('canvas'),
                      ctx = c.getContext('2d');
                c.width = w;
                c.height = w * img.height / img.width;
                ctx.drawImage(img, 0, 0, c.width, c.height);
                res(c.toDataURL('image/jpeg', 0.6));
            };
        });
    };

    Promise.all([thumb(result.original), thumb(result.enhanced)])
        .then(([origThumb, enhThumb]) => {
            const item = {
                id: Date.now(),
                originalThumb: origThumb,
                enhancedThumb: enhThumb,
                originalFull: result.original,   // keep full URL
                enhancedFull: result.enhanced,
                creativity: result.creativity,
                timestamp: new Date().toISOString()
            };

            this.currentHistory.unshift(item);
            if (this.currentHistory.length > 30) this.currentHistory = this.currentHistory.slice(0, 30);

            try {
                localStorage.setItem('imageHistory', JSON.stringify(this.currentHistory));
                this.renderHistory();
            } catch (_) {
                console.warn('History quota full – dropping oldest');
                this.currentHistory.pop();
                localStorage.setItem('imageHistory', JSON.stringify(this.currentHistory));
                this.renderHistory();
            }
        });
}



            loadHistory() {
                try {
                    const saved = localStorage.getItem('imageHistory');
                    this.currentHistory = saved ? JSON.parse(saved) : [];
                    this.renderHistory();
                } catch (error) {
                    console.error('Error loading history:', error);
                    this.currentHistory = [];
                    this.renderHistory();
                }
            }

/* ---------- render history (uses thumbs) ---------- */
renderHistory() {
    if (this.currentHistory.length === 0) {
        this.historyList.innerHTML = '<div class="empty-history">No enhancements yet</div>';
        return;
    }

    this.historyList.innerHTML = this.currentHistory.map(item => `
        <article class="history-item" data-id="${item.id}" tabindex="0" role="button">
            <div class="history-thumbnails">
                <img src="${item.originalThumb}" class="history-thumbnail" alt="Original">
                <span class="arrow-icon">→</span>
                <img src="${item.enhancedThumb}" class="history-thumbnail" alt="Enhanced">
            </div>
            <div class="history-info">
                <div class="history-creativity">Level ${item.creativity}</div>
                <div class="history-timestamp">${this.formatDate(new Date(item.timestamp))}</div>
            </div>
        </article>
    `).join('');

    this.historyList.querySelectorAll('.history-item').forEach(el => {
        const click = () => {
            const it = this.currentHistory.find(h => h.id == el.dataset.id);
            if (it) {
                this.showResults({
                    original: it.originalFull,
                    enhanced: it.enhancedFull,
                    creativity: it.creativity
                });
            }
        };
        el.addEventListener('click', click);
        el.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); click(); }
        });
    });
}

            clearHistory() {
                if (this.currentHistory.length === 0) {
                    this.showError('History is already empty.');
                    return;
                }
                
                if (confirm('Are you sure you want to clear all enhancement history? This cannot be undone.')) {
                    this.currentHistory = [];
                    try {
                        localStorage.removeItem('imageHistory');
                        this.renderHistory();
                        this.showSuccess('History cleared successfully.');
                    } catch (error) {
                        console.error('Error clearing history:', error);
                        this.showError('Could not clear history.');
                    }
                }
            }

            formatDate(date) {
                const now = new Date();
                const diffMs = now - date;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);

                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return `${diffMins}m ago`;
                if (diffHours < 24) return `${diffHours}h ago`;
                if (diffDays < 7) return `${diffDays}d ago`;
                return date.toLocaleDateString();
            }

            openSettings() {
                if (this.settingsModal) {
                    this.settingsModal.classList.add('active');
                }
                if (this.modalApiKey && this.modalApiKey.value) {
                    this.fetchModels();
                }
            }

            closeSettings() {
                this.settingsModal.classList.remove('active');
            }

            closeModalOnBackdrop(e) {
                if (e.target === this.settingsModal) {
                    this.closeSettings();
                }
            }

async fetchModels() {
    if (!this.modalApiKey || !this.modalModel) {
        console.error('Modal elements not found for fetching models');
        this.showError('Settings modal not properly initialized. Please refresh the page.');
        return;
    }

    const apiKey = this.modalApiKey.value.trim();
    if (!apiKey) {
        this.modalModel.innerHTML = '<option value="">Enter API key first</option>';
        this.showError('Please enter your API key before loading models.');
        return;
    }

    this.modalModel.innerHTML = '<option value="">Loading models…</option>';
    this.showNotification('Loading available models...', 'success');

    try {
        const res = await fetch(`https://generativelanguage.googleapis.com/v1beta/models?key=${apiKey}`);
        const data = await res.json();

        if (!res.ok) {
            const errorMsg = data.error?.message || `HTTP ${res.status}`;
            throw new Error(errorMsg);
        }

        const current = localStorage.getItem('geminiModel');
        this.modalModel.innerHTML = '';

        // list every model that can generateContent
        let list = (data.models || []).filter(m =>
            m.supportedGenerationMethods?.includes('generateContent')
        );

        // prefer models whose name contains "vision"
        const vision = list.filter(m => /vision/i.test(m.name));
        if (vision.length) list = vision;

        if (!list.length) {
            this.modalModel.innerHTML = '<option value="">No compatible models</option>';
            this.showError('No compatible models found for your API key. Please check your key permissions.');
            return;
        }

        list.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.name;
            opt.textContent = m.displayName || m.name;
            if (m.name === current) opt.selected = true;
            this.modalModel.appendChild(opt);
        });
        
        this.showNotification(`Successfully loaded ${list.length} models`, 'success');
        console.log(`Models loaded successfully:`, list.map(m => m.displayName || m.name));
        
    } catch (err) {
        console.error('Model fetch error:', err);
        this.modalModel.innerHTML = `<option value="">Error: ${err.message}</option>`;
        
        // Detailed error notifications
        if (err.message.includes('API key')) {
            this.showError('Invalid API key. Please check your Gemini API key.');
        } else if (err.message.includes('403')) {
            this.showError('API key access denied. Please check your key permissions.');
        } else if (err.message.includes('NetworkError') || err.message.includes('fetch')) {
            this.showError('Network error. Please check your internet connection and try again.');
        } else {
            this.showError(`Failed to load models: ${err.message}`);
        }
    }
}
            loadSettings() {
                const apiKey = localStorage.getItem('geminiApiKey');
                const model = localStorage.getItem('geminiModel');
                const creativity = localStorage.getItem('defaultCreativity');

                if (apiKey && this.modalApiKey) {
                    this.modalApiKey.value = apiKey;
                }
                
                if (creativity && creativity >= 1 && creativity <= 5) {
                    if (this.creativitySlider) {
                        this.creativitySlider.value = creativity;
                        this.updateCreativityDisplay();
                    }
                }
            }

            saveSettings() {
                const apiKey = this.modalApiKey.value.trim();
                const model = this.modalModel.value;

                if (!apiKey) {
                    this.showError('Please enter your API key.');
                    this.modalApiKey.focus();
                    return;
                }

                if (!model) {
                    this.showError('Please select a model.');
                    return;
                }

                try {
                    localStorage.setItem('geminiApiKey', apiKey);
                    localStorage.setItem('geminiModel', model);
                    localStorage.setItem('defaultCreativity', this.creativitySlider.value);
                    
                    this.closeSettings();
                    this.showSuccess('Settings saved successfully!');
                } catch (error) {
                    console.error('Error saving settings:', error);
                    this.showError('Could not save settings. Storage may be full.');
                }
            }

            downloadImage() {
                const enhancedSrc = this.enhancedImage.src;
                if (!enhancedSrc) {
                    this.showError('No enhanced image to download.');
                    return;
                }

                try {
                    const link = document.createElement('a');
                    link.href = enhancedSrc;
                    link.download = `enhanced-image-${Date.now()}.png`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    this.showSuccess('Image download started.');
                } catch (error) {
                    console.error('Download error:', error);
                    this.showError('Could not download image.');
                }
            }

            handleKeydown(e) {
                if (e.key === 'Escape') {
                    this.closeSettings();
                } else if (e.key === 'Enter' && this.settingsModal.classList.contains('active')) {
                    e.preventDefault();
                    this.saveSettings();
                } else if ((e.ctrlKey || e.metaKey) && e.key === 's' && this.enhancedImage.src) {
                    e.preventDefault();
                    this.downloadImage();
                }
            }

            showError(message) {
                this.showNotification(message, 'error');
            }

            showSuccess(message) {
                this.showNotification(message, 'success');
            }

            showNotification(message, type) {
                // Remove existing notifications
                const existing = document.querySelectorAll('.notification');
                existing.forEach(n => n.remove());

                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.textContent = message;
                notification.setAttribute('role', 'alert');
                notification.setAttribute('aria-live', 'polite');
                
                document.body.appendChild(notification);

                // Auto remove after 5 seconds
                const timeoutId = setTimeout(() => {
                    if (notification.parentNode) {
                        notification.style.animation = 'slideOutRight 0.3s ease-out';
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notification.remove();
                            }
                        }, 300);
                    }
                }, 5000);

                // Allow manual dismissal
                notification.addEventListener('click', () => {
                    clearTimeout(timeoutId);
                    notification.remove();
                });
            }
        }

        // Initialize the application
        document.addEventListener('DOMContentLoaded', () => {
            new ImageEnhancer();
            if (window.location.protocol === 'file:') {
                alert('This application requires a PHP server to run correctly. Please run `php -S localhost:8000` in your terminal and open http://localhost:8000.');
            }
        });
        
        
    </script>
        
        
</body>
</html>
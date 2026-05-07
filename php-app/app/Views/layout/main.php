<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Connect IT'; ?></title>
    <link rel="stylesheet" href="/php-app/public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <?php include_once __DIR__ . "/sidebar.php"; ?>
        
        <main class="main-content">
            <header class="top-nav">
                <!-- Navigation components can go here -->
            </header>
            
            <div class="container">
                {{content}}
            </div>
        </main>
    </div>
    
    <script src="/php-app/public/js/app.js"></script>
</body>
</html>

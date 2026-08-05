<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Durin's Forge</title>
    
    <!-- Vite Assets -->
    <?= App\Infrastructure\View\Vite::tags('resources/js/app.js') ?>

</head>
<body class="bg-gray-100 font-sans antialiased">
    <div id="app" data-page='<?= $page ?>'></div>
</body>
</html>

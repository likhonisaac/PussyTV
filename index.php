<?php
// Proxy function for handling cross-origin media requests
function proxyRequest($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($http_code === 200) {
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: $content_type");
        echo $data;
    } else {
        http_response_code($http_code);
        echo "Error: Unable to fetch the video.";
    }
    exit;
}

// If the proxy is triggered
if (isset($_GET['proxy']) && isset($_GET['url'])) {
    proxyRequest(urldecode($_GET['url']));
}

// Load the M3U playlist file
$m3uUrl = "https://raw.githubusercontent.com/iptv2024/Vod/72e4af1b320ba276d13c5a295c05944c0d14bd32/13.m3u";
$m3uData = file_get_contents($m3uUrl);

if (!$m3uData) {
    die("Failed to load M3U data.");
}

// Parse M3U content
$lines = explode("\n", $m3uData);
$channels = [];
foreach ($lines as $index => $line) {
    if (preg_match('/#EXTINF:-1 group-title="(.+?)" tvg-logo="(.+?)",(.+)\n(.+)/', $line . "\n" . ($lines[$index + 1] ?? ''), $match)) {
        $channels[] = [
            'group' => $match[1],
            'logo' => $match[2],
            'title' => $match[3],
            'url' => $match[4],
        ];
    }
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = 12;
$totalItems = count($channels);
$totalPages = ceil($totalItems / $pageSize);
$startIndex = ($page - 1) * $pageSize;
$paginatedChannels = array_slice($channels, $startIndex, $pageSize);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Advanced IPTV Player with universal streaming support">
    <meta name="keywords" content="IPTV, Streaming, HLS, Video Player">
    <meta name="author" content="Rekt Developers">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.0.4/video-js.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.0.4/video.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/videojs-contrib-hls@5.15.0/videojs-contrib-hls.min.js"></script>
    <title>Advanced IPTV Player</title>
</head>

<body class="bg-gray-900 text-white">

    <!-- Header -->
    <header class="bg-gray-800 py-6 text-center shadow-md">
        <h1 class="text-4xl font-bold text-green-400">Advanced IPTV Player</h1>
    </header>

    <!-- Main Content -->
    <div class="container mx-auto py-8">
        <!-- Channels -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($paginatedChannels as $channel): ?>
                <div class="card bg-gray-800 shadow-lg rounded-lg overflow-hidden">
                    <img class="w-full h-36 object-cover" src="<?= htmlspecialchars($channel['logo']) ?>" alt="<?= htmlspecialchars($channel['title']) ?>">
                    <div class="p-4">
                        <h2 class="font-bold text-lg mb-2"><?= htmlspecialchars($channel['title']) ?></h2>
                        <button onclick="playVideo('<?= htmlspecialchars($channel['title']) ?>', '<?= urlencode($channel['url']) ?>')"
                            class="btn bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded w-full">
                            Play Now
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center space-x-2 mt-8">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="btn bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Previous
                </a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="btn <?= $i === $page ? 'bg-green-500' : 'bg-gray-700 hover:bg-gray-600' ?> text-white px-4 py-2 rounded">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="btn bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Video Modal -->
    <div id="video-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-70 flex justify-center items-center">
        <div class="bg-gray-800 rounded-lg shadow-lg p-6 max-w-lg w-full">
            <h2 id="video-title" class="text-xl font-bold text-white mb-4"></h2>
            <video id="video-player" class="video-js vjs-default-skin w-full h-64" controls autoplay preload="auto"></video>
            <button onclick="closeVideo()" class="btn bg-red-500 hover:bg-red-600 text-white px-4 py-2 mt-4 w-full">
                Close
            </button>
        </div>
    </div>

    <script>
        function playVideo(title, url) {
            const videoTitle = document.getElementById('video-title');
            const videoPlayer = document.getElementById('video-player');
            const modal = document.getElementById('video-modal');

            videoTitle.textContent = title;
            const player = videojs(videoPlayer);
            player.src({ src: `?proxy=1&url=${url}`, type: "application/x-mpegURL" });

            modal.classList.remove('hidden');
        }

        function closeVideo() {
            const modal = document.getElementById('video-modal');
            const videoPlayer = document.getElementById('video-player');

            const player = videojs(videoPlayer);
            player.dispose();

            modal.classList.add('hidden');
        }
    </script>
</body>

</html>
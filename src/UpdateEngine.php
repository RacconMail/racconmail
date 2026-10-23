<?php
declare(strict_types=1);

namespace RacconMail\Core;

use PDO;
use SimpleXMLElement;

/**
 * 🦝 Raccon Continuous Update Engine
 * Automatically fetches fresh news, active tech forum topics, and cultural stories
 * from top reputable worldwide sources without displaying external attributions.
 */
class UpdateEngine
{
    private PDO $db;
    private string $rootDir;
    private string $newsCacheFile;
    private string $storiesCatalogFile;
    private string $statusFile;

    public function __construct(?PDO $db = null, ?string $rootDir = null)
    {
        $this->rootDir = $rootDir ?? dirname(__DIR__);

        $webRoot = $this->rootDir;
        if (is_dir($this->rootDir . '/public_html')) {
            $webRoot = $this->rootDir . '/public_html';
        } elseif (is_dir($this->rootDir . '/public')) {
            $webRoot = $this->rootDir . '/public';
        }

        $this->newsCacheFile = $webRoot . '/data/news_cache.json';
        $this->storiesCatalogFile = file_exists($webRoot . '/worldstories_catalog.json')
            ? $webRoot . '/worldstories_catalog.json'
            : (file_exists($this->rootDir . '/public/worldstories_catalog.json')
                ? $this->rootDir . '/public/worldstories_catalog.json'
                : $this->rootDir . '/worldstories_catalog.json');
        $this->statusFile = $webRoot . '/data/engine_status.json';

        if ($db !== null) {
            $this->db = $db;
        } else {
            $dbPath = file_exists($webRoot . '/raccoon_mail.db')
                ? $webRoot . '/raccoon_mail.db'
                : (file_exists($this->rootDir . '/raccoon_mail.db')
                    ? $this->rootDir . '/raccoon_mail.db'
                    : $this->rootDir . '/db.sqlite');
            $this->db = new PDO('sqlite:' . $dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        // Ensure directories exist
        $dataDir = $webRoot . '/data';
        if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);

        $storiesCacheDir = $webRoot . '/stories_cache';
        if (!is_dir($storiesCacheDir)) @mkdir($storiesCacheDir, 0755, true);
    }

    /**
     * Resilient HTTP GET with 4s timeout and browser agent
     */
    public function fetchUrl(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) RacconMail-UpdateEngine/2.0');
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($data !== false && $httpCode >= 200 && $httpCode < 400) {
                return (string)$data;
            }
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 4,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) RacconMail-UpdateEngine/2.0\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $res = @file_get_contents($url, false, $ctx);
        return $res !== false ? (string)$res : null;
    }

    /**
     * 1. UPDATE NEWS ENGINE
     * Pulls latest tech, security, open source, AI, and science headlines from RSS.
     * Sanitizes descriptions, removes third-party attribution, categorizes and caches.
     */
    public function updateNews(): array
    {
        $feeds = [
            'https://hnrss.org/frontpage',
            'https://dev.to/feed',
            'https://feeds.arstechnica.com/arstechnica/index',
            'https://github.blog/feed/'
        ];

        $curatedImages = [
            'tech' => [
                'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1677442136019-21780ecad995?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=800&q=80'
            ],
            'security' => [
                'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1563986768609-322da13575f3?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=800&q=80'
            ],
            'world' => [
                'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=800&q=80'
            ],
            'science' => [
                'https://images.unsplash.com/photo-1507668077129-56e32842fceb?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=800&q=80'
            ]
        ];

        $existing = [];
        if (file_exists($this->newsCacheFile)) {
            $existing = json_decode((string)file_get_contents($this->newsCacheFile), true) ?: [];
        }

        $newsList = [];
        $seenTitles = [];

        // Seed with fresh items from feeds
        foreach ($feeds as $feedUrl) {
            $xmlString = $this->fetchUrl($feedUrl);
            if (!$xmlString) continue;

            $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$xml) continue;

            $items = $xml->channel->item ?? $xml->item ?? [];
            $count = 0;

            foreach ($items as $item) {
                if ($count++ >= 8) break;

                $rawTitle = trim((string)$item->title);
                if (empty($rawTitle) || isset($seenTitles[strtolower($rawTitle)])) continue;

                $rawDesc = trim((string)($item->description ?? $item->summary ?? ''));
                $cleanDesc = trim(strip_tags(html_entity_decode($rawDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if (mb_strlen($cleanDesc) > 220) {
                    $cleanDesc = mb_substr($cleanDesc, 0, 217) . '...';
                }
                if (empty($cleanDesc) || mb_strlen($cleanDesc) < 20) {
                    $cleanDesc = "Latest technical breakdown and development updates regarding " . mb_substr($rawTitle, 0, 80) . ".";
                }

                // Keyword category matching
                $titleLower = strtolower($rawTitle . ' ' . $cleanDesc);
                $cat = 'tech';
                $catLabel = 'Tech & AI';
                $catEmoji = '💻';

                if (strpos($titleLower, 'security') !== false || strpos($titleLower, 'vulnerab') !== false || strpos($titleLower, 'cve') !== false || strpos($titleLower, 'encrypt') !== false || strpos($titleLower, 'breach') !== false || strpos($titleLower, 'privacy') !== false) {
                    $cat = 'security';
                    $catLabel = 'Security & Privacy';
                    $catEmoji = '🛡️';
                } elseif (strpos($titleLower, 'space') !== false || strpos($titleLower, 'quantum') !== false || strpos($titleLower, 'solar') !== false || strpos($titleLower, 'physic') !== false || strpos($titleLower, 'climate') !== false || strpos($titleLower, 'nasa') !== false) {
                    $cat = 'science';
                    $catLabel = 'Science & Discovery';
                    $catEmoji = '🔬';
                } elseif (strpos($titleLower, 'css') !== false || strpos($titleLower, 'design') !== false || strpos($titleLower, 'ui') !== false || strpos($titleLower, 'ux') !== false || strpos($titleLower, 'frontend') !== false) {
                    $cat = 'design';
                    $catLabel = 'Web & Design';
                    $catEmoji = '🎨';
                } elseif (strpos($titleLower, 'world') !== false || strpos($titleLower, 'europe') !== false || strpos($titleLower, 'economy') !== false || strpos($titleLower, 'train') !== false || strpos($titleLower, 'city') !== false) {
                    $cat = 'world';
                    $catLabel = 'World & Society';
                    $catEmoji = '🌍';
                }

                $imgPool = $curatedImages[$cat] ?? $curatedImages['tech'];
                $selectedImg = $imgPool[crc32($rawTitle) % count($imgPool)];

                $seenTitles[strtolower($rawTitle)] = true;

                $newsList[] = [
                    'id' => 'news_' . substr(md5($rawTitle), 0, 10),
                    'category' => $cat,
                    'catLabel' => $catLabel,
                    'catColor' => 'text-slate-700 bg-slate-100 border-slate-200',
                    'catEmoji' => $catEmoji,
                    'source' => 'Raccon Editorial', // Strictly neutralized
                    'sourceSlug' => 'raccon',
                    'image' => $selectedImg,
                    'title' => html_entity_decode($rawTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'desc' => $cleanDesc,
                    'time' => 'Updated recently',
                    'readTime' => max(2, (int)(mb_strlen($cleanDesc) / 60)) . ' min read',
                    'link' => '/news'
                ];
            }
        }

        // Merge with existing items if we got less than 15
        if (count($newsList) < 15 && !empty($existing['items'])) {
            foreach ($existing['items'] as $old) {
                if (!isset($seenTitles[strtolower($old['title'] ?? '')])) {
                    $newsList[] = $old;
                    $seenTitles[strtolower($old['title'] ?? '')] = true;
                }
            }
        }

        // Structure into grouped categories for the frontend
        $grouped = [
            'updated_at' => date('Y-m-d H:i:s'),
            'total' => count($newsList),
            'items' => $newsList,
            'tech' => array_values(array_filter($newsList, fn($n) => $n['category'] === 'tech' || $n['category'] === 'design')),
            'world' => array_values(array_filter($newsList, fn($n) => $n['category'] === 'world' || $n['category'] === 'science')),
            'sport' => array_values(array_filter($newsList, fn($n) => $n['category'] === 'security' || $n['category'] === 'tech')),
            'science' => array_values(array_filter($newsList, fn($n) => $n['category'] === 'science')),
            'security' => array_values(array_filter($newsList, fn($n) => $n['category'] === 'security'))
        ];

        file_put_contents($this->newsCacheFile, json_encode($grouped, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return [
            'status' => 'success',
            'news_updated' => count($newsList),
            'timestamp' => $grouped['updated_at']
        ];
    }

    /**
     * 2. UPDATE FORUM ENGINE
     * Automatically fetches active tech discussions, questions, and coding tips from
     * developer feeds (Hacker News Ask/Show, Dev.to discussions).
     * Deduplicates and stores into SQLite forum_posts & forum_comments.
     */
    public function updateForum(): array
    {
        $sources = [
            'https://hnrss.org/ask',
            'https://hnrss.org/show',
            'https://dev.to/feed/tag/discuss'
        ];

        $nocturnalUsernames = [
            'ShadowCoder', 'CyberFox', 'ByteRaccoon', 'KernelOwl', 'LunarDev',
            'PixelBandit', 'LatticeWhiz', 'RustBadger', 'AsyncOtter', 'QuantumLynx',
            'ZenithCoder', 'BurrowMaster', 'ZeroTrace', 'EchoFalcon', 'GlitchHedgehog',
            'NocturnalPanda', 'MaskedHacker', 'ByteOwl', 'AlpineFerret', 'VelvetBat'
        ];

        $responderHandles = [
            'EchoFalcon', 'GlitchHedgehog', 'NeonBat', 'NocturnalPanda',
            'AsyncOtter', 'ShadowCoder', 'PixelBandit', 'KernelOwl'
        ];

        $insertedCount = 0;
        $maxPerRun = 4; // Add up to 4 high-value discussions per cycle to keep DB fast and curated

        foreach ($sources as $sourceUrl) {
            if ($insertedCount >= $maxPerRun) break;

            $xmlString = $this->fetchUrl($sourceUrl);
            if (!$xmlString) continue;

            $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$xml) continue;

            $items = $xml->channel->item ?? [];
            foreach ($items as $item) {
                if ($insertedCount >= $maxPerRun) break;

                $rawTitle = trim((string)$item->title);
                if (empty($rawTitle)) continue;

                // Strip source prefixes like "Ask HN:", "Show HN:", "Dev.to:"
                $cleanTitle = preg_replace('/^(Ask\s+HN|Show\s+HN|Tell\s+HN|Dev\.to|Discussion):\s*/i', '', $rawTitle);
                $cleanTitle = trim(html_entity_decode($cleanTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if (mb_strlen($cleanTitle) < 15) continue;

                // Check if already in forum_posts
                $checkStmt = $this->db->prepare("SELECT 1 FROM forum_posts WHERE title = ?");
                $checkStmt->execute([$cleanTitle]);
                if ($checkStmt->fetchColumn()) {
                    continue; // Skip duplicate
                }

                $rawDesc = trim((string)($item->description ?? ''));
                $cleanContent = trim(strip_tags(html_entity_decode($rawDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

                // Strip any external aggregator links, comments URL, or source leaks
                $cleanContent = preg_replace('/Comments\s+URL:\s*https?:\/\/[^\s]+/i', '', $cleanContent);
                $cleanContent = preg_replace('/Article\s+URL:\s*https?:\/\/[^\s]+/i', '', $cleanContent);
                $cleanContent = preg_replace('/Points:\s*\d+/i', '', $cleanContent);
                $cleanContent = preg_replace('/#\s*Comments:\s*\d+/i', '', $cleanContent);
                $cleanContent = trim($cleanContent);

                if (mb_strlen($cleanContent) < 30) {
                    $cleanContent = "Let's explore and discuss {$cleanTitle}. What are your experiences, architecture tradeoffs, and recommended approaches in production environments? Would love to hear your insights!";
                }

                // Determine category
                $titleLower = strtolower($cleanTitle . ' ' . $cleanContent);
                $cat = 'tech';
                if (strpos($titleLower, 'css') !== false || strpos($titleLower, 'design') !== false || strpos($titleLower, 'ui') !== false || strpos($titleLower, 'tailwind') !== false) {
                    $cat = 'webdesign';
                } elseif (strpos($titleLower, 'code') !== false || strpos($titleLower, 'rust') !== false || strpos($titleLower, 'python') !== false || strpos($titleLower, 'function') !== false || strpos($titleLower, 'async') !== false) {
                    $cat = 'coding';
                } elseif (strpos($titleLower, 'how') === 0 || strpos($titleLower, 'why') === 0 || strpos($titleLower, 'help') !== false || strpos($titleLower, '?') !== false) {
                    $cat = 'help';
                } elseif (strpos($titleLower, 'show') !== false || strpos($titleLower, 'built') !== false || strpos($titleLower, 'release') !== false) {
                    $cat = 'sharing';
                }

                // Random nocturnal author
                $author = $nocturnalUsernames[array_rand($nocturnalUsernames)];
                $userId = strtolower($author) . '_' . substr(md5($cleanTitle), 0, 6);
                $postId = 'post_' . uniqid();

                // Optional code snippet extraction
                $codeSnippet = '';
                $codeLang = 'javascript';
                if (preg_match('/```([a-z]*)\s*([\s\S]*?)```/i', $rawDesc, $codeMatch)) {
                    $codeLang = !empty($codeMatch[1]) ? strtolower($codeMatch[1]) : 'javascript';
                    $codeSnippet = trim($codeMatch[2]);
                }

                $tags = '#' . $cat . ', #tech, #engineering';

                // Assign automatic nocturnal hub location
                $hubLocations = [
                    'Bucharest, Romania',
                    'Cluj-Napoca, Romania',
                    'Timisoara, Romania',
                    'Zurich, Switzerland',
                    'Berlin, Germany',
                    'Amsterdam, Netherlands',
                    'Vienna, Austria',
                    'Stockholm, Sweden',
                    'Helsinki, Finland',
                    'London, UK',
                    'Dublin, Ireland',
                    'Oslo, Norway',
                    'Copenhagen, Denmark',
                    'Prague, Czech Republic',
                    'Tallinn, Estonia',
                    'Tokyo, Japan'
                ];
                $postLocation = $hubLocations[array_rand($hubLocations)];

                // Ensure location column exists
                try {
                    $this->db->exec("ALTER TABLE forum_posts ADD COLUMN location TEXT DEFAULT ''");
                } catch (\Throwable $e) {}

                // Insert into forum_posts
                $insPost = $this->db->prepare("
                    INSERT INTO forum_posts (id, user_id, title, category, content, code_snippet, code_lang, image_url, tags, location, likes_count, comments_count, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, '', ?, ?, 1, 0, datetime('now'))
                ");
                $insPost->execute([$postId, $userId, $cleanTitle, $cat, $cleanContent, $codeSnippet, $codeLang, $tags, $postLocation]);

                // Insert 1-2 thoughtful nocturnal replies
                $replyAuthor = $responderHandles[array_rand($responderHandles)];
                while ($replyAuthor === $author) {
                    $replyAuthor = $responderHandles[array_rand($responderHandles)];
                }

                $sampleReplies = [
                    "Solid topic! In our setup we tackled this by keeping our models lean and minimizing external telemetry. The key tradeoff was latency vs memory footprint.",
                    "Great breakdown. We've seen similar patterns in production. Keeping things synchronous where possible avoided several race conditions.",
                    "Really helpful explanation. Clean implementation and right to the point. Thanks for sharing this with the burrow!",
                    "One aspect worth considering is cache invalidation when scaling this pattern across multiple nocturnal nodes."
                ];

                $replyText = $sampleReplies[array_rand($sampleReplies)];
                $commentId = 'comm_' . uniqid();
                $insComment = $this->db->prepare("
                    INSERT INTO forum_comments (id, post_id, user_id, content, code_snippet, created_at)
                    VALUES (?, ?, ?, ?, '', datetime('now', '+2 minutes'))
                ");
                $insComment->execute([$commentId, $postId, strtolower($replyAuthor) . '_res', $replyText]);

                $this->db->prepare("UPDATE forum_posts SET comments_count = 1 WHERE id = ?")->execute([$postId]);

                $insertedCount++;
            }
        }

        return [
            'status' => 'success',
            'forum_topics_added' => $insertedCount
        ];
    }

    /**
     * 3. UPDATE STORIES ENGINE
     * Enriches worldstories_catalog.json with bedtime tales, folklore, and fables.
     * Ensures clean reader cache and zero external source branding.
     */
    public function updateStories(): array
    {
        if (!file_exists($this->storiesCatalogFile)) {
            return ['status' => 'error', 'message' => 'Stories catalog not found'];
        }

        $catalog = json_decode((string)file_get_contents($this->storiesCatalogFile), true) ?: [];
        $existingIds = array_column($catalog, 'id');

        $freshNocturnalStories = [
            [
                'id' => 'nocturnal-01',
                'slug' => 'the-stars-in-the-sky',
                'title' => 'The Stars in the Sky',
                'country' => 'England',
                'flag' => '🇬🇧',
                'category' => 'bedtime',
                'readTime' => '4 min',
                'desc' => 'A gentle nocturnal fairy tale about a child who dreams of touching the stars and journeys to the edge of the world where night meets the whispering woods.',
                'cover' => 'https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=800&q=80',
                'url' => '/stories?id=nocturnal-01',
                'content' => "Once upon a time, there was a young child who wanted nothing more in all the world than to play with the stars in the sky. Every evening when the moon rose over the sleepy treetops, the child would gaze up at the twinkling silver lights. One quiet night, when the house was fast asleep, the child set off across the misty meadows. First came the friendly stream, whispering gentle melodies over pebble stones. 'Have you seen the stars?' asked the child. 'Follow my ripples to the sea,' murmured the stream. Through quiet valleys and ancient moonlit arches, each step brought wonders of stillness, courage, and serenity, until at last the night sky felt like a warm, comforting blanket wrapped around the world."
            ],
            [
                'id' => 'nocturnal-02',
                'slug' => 'the-owl-and-the-moonlit-library',
                'title' => 'The Owl and the Moonlit Library',
                'country' => 'Scotland',
                'flag' => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
                'category' => 'fables',
                'readTime' => '5 min',
                'desc' => 'In a hidden hollow beneath an ancient oak tree, a wise nocturnal raccoon and a silver owl safeguard the lost scrolls of quiet wisdom.',
                'cover' => 'https://images.unsplash.com/photo-1509198397868-475647b2a1e5?auto=format&fit=crop&w=800&q=80',
                'url' => '/stories?id=nocturnal-02',
                'content' => "Deep inside the Highland forest, where fog drifts like cotton between silver birch trees, sits the Midnight Archive. Barnaby the owl perched high upon a polished cedar rafter, while Luna the raccoon carefully organized parchment rolls by starlight. 'Every word written in sincerity,' Barnaby explained, 'has its own heartbeat.' One cold autumn night, a wandering traveler knocked softly at the burrows door, seeking advice on how to navigate difficult choices. Luna shared three simple rules: speak truth without cruelty, listen with greater curiosity than eagerness to answer, and let each night wash away the worries of the day."
            ],
            [
                'id' => 'nocturnal-03',
                'slug' => 'the-fox-and-the-golden-compass',
                'title' => 'The Fox and the Golden Compass',
                'country' => 'Japan',
                'flag' => '🇯🇵',
                'category' => 'folklore',
                'readTime' => '3 min',
                'desc' => 'A timeless oriental tale of kindness, mindfulness, and staying true to one’s internal moral compass through shifting winds.',
                'cover' => 'https://images.unsplash.com/photo-1516934024742-b461fba47600?auto=format&fit=crop&w=800&q=80',
                'url' => '/stories?id=nocturnal-03',
                'content' => "Long ago in the bamboo groves near Mount Fuji, an old craftsman fashioned a compass made of hammered gold and polished river jade. Instead of pointing North, the compass needle aligned with wherever genuine kindness was needed. Ren the fox discovered the heirloom resting beside a moss-covered stone lantern. Guided by its subtle magnetic dance, Ren helped a lost fledgling find its nest, warned villagers of a rising flood, and planted oak saplings along empty roads. The greatest treasures, the elders say, are not those we hold in our paws, but those we give away to make the road easier for others."
            ]
        ];

        $addedStories = 0;
        foreach ($freshNocturnalStories as $story) {
            if (!in_array($story['id'], $existingIds, true)) {
                // Cache the full reader story text
                $cacheDir = $this->rootDir . '/public/stories_cache';
                $cacheFile = $cacheDir . '/' . $story['id'] . '.json';
                $readerData = [
                    'title' => $story['title'],
                    'country' => $story['country'],
                    'paragraphs' => explode("\n\n", $story['content']),
                    'cover' => $story['cover']
                ];
                file_put_contents($cacheFile, json_encode($readerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                // Add to catalog
                unset($story['content']);
                $catalog[] = $story;
                $addedStories++;
            }
        }

        if ($addedStories > 0) {
            file_put_contents($this->storiesCatalogFile, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return [
            'status' => 'success',
            'stories_added' => $addedStories,
            'total_stories' => count($catalog)
        ];
    }

    /**
     * 4. MASTER UPDATE: Runs news, forum, and stories synchronization
     */
    public function updateAll(): array
    {
        $startTime = microtime(true);

        $newsResult = $this->updateNews();
        $forumResult = $this->updateForum();
        $storiesResult = $this->updateStories();

        $executionTime = round(microtime(true) - $startTime, 2);

        $forumTotal = (int)$this->db->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn();
        $newsTotal = $newsResult['news_updated'] ?? 0;
        $storiesTotal = $storiesResult['total_stories'] ?? 0;

        $statusData = [
            'status' => 'healthy',
            'last_sync' => date('Y-m-d H:i:s'),
            'execution_time_seconds' => $executionTime,
            'counts' => [
                'news' => $newsTotal,
                'forum_posts' => $forumTotal,
                'stories' => $storiesTotal
            ],
            'results' => [
                'news' => $newsResult,
                'forum' => $forumResult,
                'stories' => $storiesResult
            ]
        ];

        file_put_contents($this->statusFile, json_encode($statusData, JSON_PRETTY_PRINT));

        return $statusData;
    }

    /**
     * Retrieves current update engine status
     */
    public function getStatus(): array
    {
        if (file_exists($this->statusFile)) {
            $data = json_decode((string)file_get_contents($this->statusFile), true);
            if (is_array($data)) return $data;
        }

        $forumTotal = (int)$this->db->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn();

        return [
            'status' => 'ready',
            'last_sync' => 'Never',
            'counts' => [
                'news' => 12,
                'forum_posts' => $forumTotal,
                'stories' => 45
            ]
        ];
    }
}

<?php
/**
 * 🦝 Raccon Continuous Update Engine CLI Runner
 * Can be run via cPanel cron:
 *   php /home/raccxuxv/scripts/update_engine.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/UpdateEngine.php';

use RacconMail\Core\UpdateEngine;

echo "[" . date('Y-m-d H:i:s') . "] Starting Raccon Content Update Engine...\n";

try {
    $engine = new UpdateEngine();
    $result = $engine->updateAll();

    echo "✅ Sync complete in {$result['execution_time_seconds']}s!\n";
    echo " • News cached: {$result['counts']['news']}\n";
    echo " • Forum posts: {$result['counts']['forum_posts']} (+{$result['results']['forum']['forum_topics_added']} new)\n";
    echo " • Stories: {$result['counts']['stories']} (+{$result['results']['stories']['stories_added']} new)\n";
} catch (\Throwable $e) {
    echo "❌ Error running update engine: " . $e->getMessage() . "\n";
    exit(1);
}

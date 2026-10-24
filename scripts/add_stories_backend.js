const fs = require('fs');
const path = require('path');

const apiPath = path.join(__dirname, '..', 'public', 'api.php');
let api = fs.readFileSync(apiPath, 'utf8');

// 1. Update isPublicRoute to include community/stories
if (!api.includes('community/stories')) {
  api = api.replace(
    "|| ($route === 'story' && $method === 'GET')",
    "|| ($route === 'story' && $method === 'GET')\n    || ($route === 'community/stories' && $method === 'GET')\n    || (preg_match('#^community/stories/([^/]+)$#', $route) && $method === 'GET')\n    || (preg_match('#^community/stories/([^/]+)/comments$#', $route) && $method === 'GET')"
  );
  console.log('Added community/stories to isPublicRoute!');
}

// 2. Inject Stories Backend Section before "// Default 404"
const storiesBackendCode = `
    // ==========================================
    // 13. RACCON STORIES LIBRARY & STORY WALL API
    // ==========================================

    // Ensure stories tables exist
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS community_stories (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            title TEXT NOT NULL,
            category TEXT NOT NULL DEFAULT 'fantasy',
            format_status TEXT DEFAULT 'ongoing',
            target_audience TEXT DEFAULT 'general',
            summary TEXT DEFAULT '',
            content TEXT NOT NULL,
            cover_image TEXT DEFAULT '',
            tags TEXT DEFAULT '',
            word_count INTEGER DEFAULT 0,
            reading_time INTEGER DEFAULT 5,
            views_count INTEGER DEFAULT 0,
            likes_count INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            is_featured INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_stories_category ON community_stories(category, created_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_stories_format ON community_stories(format_status, created_at)");

        $db->exec("CREATE TABLE IF NOT EXISTS story_comments (
            id TEXT PRIMARY KEY,
            story_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_story_comments_story ON story_comments(story_id, created_at)");

        $db->exec("CREATE TABLE IF NOT EXISTS story_likes (
            story_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (story_id, user_id)
        )");

        // Seed initial stories if empty
        $storyCount = (int)$db->query("SELECT COUNT(*) FROM community_stories")->fetchColumn();
        if ($storyCount === 0) {
            $seedAuthor = 'usr_admin_master';
            $seedStories = [
                [
                    'id' => 'story_romance_midnight',
                    'title' => 'Midnight Under the Constellations',
                    'category' => 'romance',
                    'format_status' => 'completed',
                    'target_audience' => 'na',
                    'summary' => 'An unexpected rainy evening in Prague brings two strangers into an antique bookstore, sparking an intimate connection that neither anticipated.',
                    'content' => "The rain in Prague does not fall so much as it suspends itself in the lantern-lit air, blurring the cobblestones into mirrors of liquid amber.\\n\\nClara had pushed through the worn oak door of *Knihkupectví U Sovy* seeking nothing more ambitious than shelter for her soaked wool coat. What she found instead was a man sitting beside a tower of vintage leather-bound atlases, a cup of steaming Earl Grey resting precariously on an unfolded map of 18th-century Bohemia.\\n\\n'The roof leaks on the east wall,' he remarked without looking up, his voice low and seasoned with a quiet amusement. 'If you stand by the poetry section, you will remain dry.'\\n\\nClara shook out her umbrella, stepping closer to the warm radius of the brass desk lamp. 'And what if I prefer maps to poetry?'\\n\\nThe man finally looked up. His eyes were the color of slate after a squall, sharp with intelligence yet unexpectedly warm. 'Then you are welcome to help me find a town that ceased to exist two hundred years ago.'\\n\\nThat single rain-drenched sentence began the three hours that altered Clara's entire trajectory. They traced phantom borders with fountain pens, argued over the translation of forgotten Latin marginalia, and discovered that both of them had fled the same crowded tech conference downtown in search of silence.\\n\\nWhen the church bells of Saint Nicholas chimed midnight across the square, the rain had ceased, but neither of them made a move toward the door. Some meetings are chance; others feel as though the ink had dried on the parchment centuries before.",
                    'tags' => 'romance,contemporary,prague,bookstore,strangers',
                    'words' => 2480,
                    'read_time' => 8,
                    'likes' => 38,
                    'comments' => 6
                ],
                [
                    'id' => 'story_fantasy_runes',
                    'title' => 'The Weaver of Silver Runes',
                    'category' => 'fantasy',
                    'format_status' => 'ongoing',
                    'target_audience' => 'ya',
                    'summary' => 'In the floating citadels of Aeloria, magic is woven into silver silk. When apprentice Lyra accidentally unravels a forbidden ancient glyph, she awakens a world long forgotten.',
                    'content' => "The silk spindles in the Grand Loom tower spun with the sound of distant flutes. In Aeloria, magic was not spoken or scribed; it was spun.\\n\\nLyra possessed fingers that moved faster than the elder weavers liked. While others measured their tension by the hourglass, she followed the hum beneath the silver threads—the subtle harmonic vibration that indicated the presence of stored kinetic sorcery.\\n\\n'Tension too loose on the warp, Lyra,' Master Corvus warned, gliding past her loom in his embroidered robes. 'A loose thread in a warding cloak invites a puncture.'\\n\\n'Yes, Master,' she murmured. But as soon as Corvus turned the corner, Lyra focused on the dark thread she had discovered nestled at the bottom of the raw cocoon cache. It was not silver. It was deep void-indigo, cold to the touch and shimmering with an iridescent sheen that defied the tower's skylights.\\n\\nWhen her shuttle guided the indigo filament across the silver matrix, the loom did not click. It resonated. A ripple of low, bell-like frequency washed outward, causing the crystal lanterns across the balcony to flicker and extinguish.\\n\\nIn the center of the woven tapestry, a rune formed itself—not one from the High Guild lexicon, but an archaic spiral symbol that matched the carvings on the submerged monoliths beneath the cloud barrier.\\n\\nThen, the tapestry whispered her name.",
                    'tags' => 'fantasy,magic,highfantasy,runes,adventure',
                    'words' => 3120,
                    'read_time' => 11,
                    'likes' => 54,
                    'comments' => 9
                ],
                [
                    'id' => 'story_scifi_void',
                    'title' => 'Echoes of the Void Terminal',
                    'category' => 'scifi',
                    'format_status' => 'completed',
                    'target_audience' => 'general',
                    'summary' => 'Deep aboard orbital relay station Kepler-9, an archivist detects a repeating mathematical signal emanating from a ghost ship that was declared lost two centuries ago.',
                    'content' => "The station chronometer ticked over to 03:00 UTC. At three in the morning ship-time, the Kepler-9 relay is the quietest place in the Kuiper belt.\\n\\nDr. Ronald Hayes dipped his synthetic biscuits into chicory broth and adjusted the sub-millimeter array. Most incoming packets were mundane: quantum handshake pings from the Jovian mining rigs, automated telemetry from helium-3 freighters, compressed diplomatic archives heading towards Mars.\\n\\nThen the buffer lit up in amber.\\n\\nA transmission was hitting the dipole antenna at 1420.405 MHz—the hydrogen line. It carried an antiquated carrier modulation: 8-bit binary parity framing with cyclic redundancy checks that had been deprecated in 2180.\\n\\nHayes set down his cup. He ran the checksum against the historical registry.\\n\\n*REGISTRY MATCH: USCSS ARTEMIS V. DECOMMISSIONED / LOST TRANSIT: 2194.*\\n\\n'Two hundred and thirty years,' Hayes whispered into the empty comms deck. The ship had disappeared while charting the Oort boundary before warp-folding was perfected.\\n\\nThe signal was not a distress call. It was a navigation coordinate stream followed by six words encoded in raw ASCII:\\n\\n*WE FOUND THE EDGE. IT SINGS.*",
                    'tags' => 'scifi,space,mystery,cyberpunk,signals',
                    'words' => 2950,
                    'read_time' => 10,
                    'likes' => 67,
                    'comments' => 12
                ],
                [
                    'id' => 'story_litrpg_raccoon',
                    'title' => 'Level 1 Raccoon: Dungeon Glitch',
                    'category' => 'litrpg',
                    'format_status' => 'ongoing',
                    'target_audience' => 'ya',
                    'summary' => 'Reincarnated inside the VR-MMORPG Aetherium Online not as a chosen hero, but as a Level 1 Nocturnal Raccoon with an infinite scavenger inventory!',
                    'content' => "[SYSTEM ALERT: Player soul migration complete.]\\n[Race Assigned: Forest Raccoon (F-Rank Common Critter)]\\n[HP: 25/25 | MP: 10/10 | Agility: 18 | Strength: 3 | Luck: ???]\\n\\nI blinked. My hands were small, gray, and distinctly equipped with five dextrous little black claws. A fluffy ringed tail twitched behind me.\\n\\n'Wait,' I tried to yell. All that came out was a high-pitched, indignant chittering sound.\\n\\nI was sitting in the underbrush outside the starter village of Oakdale. A party of four Level 1 novice human adventurers was marching past, clad in dented iron chestplates and carrying wooden training swords.\\n\\n'Look, a wild raccoon,' the mage shouted. 'Free 5 XP!'\\n\\nThe warrior swung his wooden sword at me. Instinct took over: I scurried up an elder oak at forty miles an hour, reached out with my left paw, and accidentally touched the warrior's leather pouch.\\n\\n[INHERENT SKILL ACTIVATED: SCAVENGE INFINITE (EX-RANK)]\\n[Acquired: 15x Copper Coins]\\n[Acquired: 1x Health Potion (Grade D)]\\n[Acquired: 1x [Glitch Item] Key to the Forgotten Sub-Vault]\\n\\nThe warrior looked down at his empty belt. 'Hey... where did all my loot go?!'\\n\\nI sat on the branch above, unscrewed the health potion with my paws, and took a satisfied sip. Being a low-level dungeon critter was going to be hilarious.",
                    'tags' => 'litrpg,gamelit,comedy,isekai,adventure,raccoon',
                    'words' => 2100,
                    'read_time' => 7,
                    'likes' => 92,
                    'comments' => 18
                ],
                [
                    'id' => 'story_ceo_proposition',
                    'title' => 'The Executive Proposition',
                    'category' => 'billionaire',
                    'format_status' => 'ongoing',
                    'target_audience' => 'na',
                    'summary' => 'Software engineer Mia thought she was interviewing for a standard developer role at Apex Global, until CEO Julian Thorne offered her an unconventional merger of lives.',
                    'content' => "The 54th floor of the Apex Tower overlooked the entirety of Manhattan, an ocean of glittering spires wrapped in evening smog.\\n\\nMia adjusted the cuffs of her modest blazer, clutching her portfolio folder. She had prepared for algorithmic challenges, distributed systems design, and database indexing. She had not prepared for Julian Thorne to dismiss his entire panel of five senior executives with a simple wave of his hand.\\n\\n'Leave us,' he had said. His voice was effortless authority, framed by a charcoal bespoke suit and eyes that took in everything without revealing a particle of thought.\\n\\nWhen the heavy walnut doors clicked shut, Thorne stood up from the glass table and walked to the window.\\n\\n'Your GitHub commits on the cryptographic envelope repository were fascinating, Ms. Vance,' he said quietly. 'You write code like someone who trusts no one.'\\n\\n'In security architecture, trust is a vulnerability,' Mia replied, holding her chin steady.\\n\\nA faint, intriguing smile curved his mouth. 'Precisely. Which is why I do not need you to write backend microservices for Apex.' He turned, leaning back against the sill. 'I need someone whose privacy protocols cannot be bought, coerced, or breached. I need a counterfeit fiancé for the upcoming Tokyo conglomerate merger, and in return, Apex will grant full equity funding to your open-source foundation.'\\n\\nMia stared at him. 'You're insane, Mr. Thorne.'\\n\\n'Perhaps,' he stepped forward, his cologne carrying notes of cedar and rain. 'But check your inbox. The preliminary draft is already sealed.'",
                    'tags' => 'billionaire,romance,ceo,office,drama',
                    'words' => 2650,
                    'read_time' => 9,
                    'likes' => 74,
                    'comments' => 15
                ],
                [
                    'id' => 'story_werewolf_lunar',
                    'title' => 'Lunar Eclipse of the Silver Ridge',
                    'category' => 'paranormal',
                    'format_status' => 'ongoing',
                    'target_audience' => 'ya',
                    'summary' => 'When rogue shapeshifter Kieran crosses into forbidden territory during the blood moon, an ancient bond forces rival clans to choose between peace or total war.',
                    'content' => "The blood moon rose heavy and crimson over the jagged teeth of the Silver Ridge.\\n\\nKieran felt the change in his marrow twenty minutes before the light crested the pines. It was not a violent tearing, but a tidal surge of instincts: hearing the flutter of an owl's wing two miles down the valley, scenting ozone and frost on the wind.\\n\\nHe had broken pack law by crossing the river border. The Elders had drawn the truce line eighty winters ago after the massacre at Blackpine Ravine: no wolf steps east of the water, no vampire hunts west of the ridge.\\n\\nYet here he stood on the frozen eastern bank, following a scent that made his wolf whine with a longing deeper than survival.\\n\\nA shadow materialized atop the granite boulder before him, silent as snowfall. Crimson velvet coat, silver hair catching the moon, and eyes the piercing violet of nightshade.\\n\\n'You are trespassing, pup,' she spoke, her tone cool glass. But her hands, pale and clawed, were trembling with the exact same electric resonance that pinned Kieran to the earth.\\n\\nThe mating mark on his collarbone burned like white phosphorus. And on the hollow of her throat, identical silver script flared to life under the red sky.\\n\\n'It seems,' Kieran whispered, stepping out of the mist, 'that our ancestors forgot to consult the moon.'",
                    'tags' => 'paranormal,werewolf,vampire,mates,forbidden',
                    'words' => 2800,
                    'read_time' => 9,
                    'likes' => 81,
                    'comments' => 14
                ],
                [
                    'id' => 'story_horror_pines',
                    'title' => 'The Whispering Pines of Blackwood',
                    'category' => 'horror',
                    'format_status' => 'completed',
                    'target_audience' => 'adult',
                    'summary' => 'A forest ranger taking the winter night shift at an isolated lookout tower begins to hear knocks coming from the inside of the floorboards.',
                    'content' => "Lookout Tower 7 stood on stilts sixty feet above the canopy of the Blackwood National Preserve. In summer, tourists marvel at the panoramic view of the sawtooth mountains. In November, when the access roads ice over, it is just you, two propane heaters, and the wind.\\n\\nMark was brewing black coffee on the Coleman stove when the first knock came.\\n\\nIt was not the exterior rattle of timber under gale force. It was three deliberate, knuckle-sharp raps against wood.\\n\\n*Knock. Knock. Knock.*\\n\\nThey came from beneath the linoleum rug directly under his swivel chair.\\n\\nMark froze with the kettle poised over his tin mug. Below the tower was sixty feet of sheer drop, support beams, and hemlock branches. There was no crawlspace. There was no second story. The floor was two-inch tongue-and-groove pine bolted directly to steel girders.\\n\\nHe waited. The howling wind outside picked up, shaking the guy-wires.\\n\\nHe took a breath, letting himself scoff at his own paranoia. 'Wind buffeting the hatch,' he muttered aloud to break the suffocating silence.\\n\\nThen came the second sound.\\n\\nNot a knock this time.\\n\\nA wet, deliberate fingernail dragging along the bottom of the wood grain, following the path of his footsteps across the room.",
                    'tags' => 'horror,thriller,suspense,paranormal,cabin',
                    'words' => 2300,
                    'read_time' => 8,
                    'likes' => 63,
                    'comments' => 11
                ],
                [
                    'id' => 'story_poetry_nocturnal',
                    'title' => 'Nocturnal Verses from the Cedar Burrow',
                    'category' => 'poetry',
                    'format_status' => 'completed',
                    'target_audience' => 'general',
                    'summary' => 'A lyrical collection of nocturnal stanzas celebrating starry skies, silent midnight walks, and the comfort of solitude.',
                    'content' => "I. Midnight Cartography\\n\\nThe day belongs to brass and clocks,\\nTo hurried heels on crowded blocks;\\nBut when the streetlights buzz and dim,\\nThe silent city starts to brim.\\n\\nA shadow slips across the tile,\\nA whiskered mask, a crooked smile;\\nWe measure time by moonlight gleams,\\nAnd map the kingdom of our dreams.\\n\\nII. The Code in the Frost\\n\\nNo message sealed in paper folds\\nCan match the peace the cedar holds;\\nBeneath the roots where mosses creep,\\nThe night is watchful, wide, and deep.\\n\\nLet daylight run its noisy race,\\nWe find our home in nocturnal space.",
                    'tags' => 'poetry,verse,nocturnal,peace,stars',
                    'words' => 450,
                    'read_time' => 3,
                    'likes' => 49,
                    'comments' => 5
                ]
            ];

            $stmtInsert = $db->prepare("INSERT INTO community_stories (id, user_id, title, category, format_status, target_audience, summary, content, tags, word_count, reading_time, likes_count, comments_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($seedStories as $s) {
                $stmtInsert->execute([
                    $s['id'], $seedAuthor, $s['title'], $s['category'], $s['format_status'], $s['target_audience'],
                    $s['summary'], $s['content'], $s['tags'], $s['words'], $s['read_time'], $s['likes'], $s['comments']
                ]);
            }
        }
    } catch (Throwable $e) {}

    // 13.1 GET /api/community/stories (List, Search & Filter Stories)
    if ($route === 'community/stories' && $method === 'GET') {
        $category = trim($_GET['category'] ?? 'all');
        $status = trim($_GET['status'] ?? 'all');
        $search = trim($_GET['search'] ?? '');
        $sort = trim($_GET['sort'] ?? 'popular');

        $query = "SELECT s.*, 
                  COALESCE(u.name, REPLACE(SUBSTR(s.user_id, 1, 14), '_', ' ')) as author_name, 
                  COALESCE(u.email, s.user_id || '@racconmail.eu') as author_email, 
                  COALESCE(u.avatar, '/raccoon-logo.png') as author_avatar 
                  FROM community_stories s 
                  LEFT JOIN users u ON s.user_id = u.id 
                  WHERE 1=1";
        $params = [];

        if ($category !== 'all' && $category !== '') {
            $query .= " AND s.category = ?";
            $params[] = $category;
        }

        if ($status !== 'all' && $status !== '') {
            $query .= " AND s.format_status = ?";
            $params[] = $status;
        }

        if ($search !== '') {
            $query .= " AND (s.title LIKE ? OR s.summary LIKE ? OR s.content LIKE ? OR s.tags LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
        }

        if ($sort === 'latest') {
            $query .= " ORDER BY s.created_at DESC";
        } elseif ($sort === 'words') {
            $query .= " ORDER BY s.word_count DESC";
        } else {
            $query .= " ORDER BY s.likes_count DESC, s.created_at DESC";
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $stories = $stmt->fetchAll();

        // Attach like status
        if ($currentUserId) {
            $stmtLikes = $db->prepare("SELECT story_id FROM story_likes WHERE user_id = ?");
            $stmtLikes->execute([$currentUserId]);
            $likedIds = array_column($stmtLikes->fetchAll(), 'story_id');
            $likedSet = array_flip($likedIds);
            foreach ($stories as &$st) {
                $st['has_liked'] = isset($likedSet[$st['id']]);
            }
        } else {
            foreach ($stories as &$st) {
                $st['has_liked'] = false;
            }
        }

        echo json_encode([
            'success' => true,
            'stories' => $stories,
            'count' => count($stories),
            'isLoggedIn' => !empty($currentUserId),
            'currentUserId' => $currentUserId
        ]);
        exit;
    }

    // 13.2 GET /api/community/stories/:id (Single Story & Reader)
    if (preg_match('#^community/stories/([^/]+)$#', $route, $m) && $method === 'GET') {
        $storyId = $m[1];
        // Increment view count
        @$db->prepare("UPDATE community_stories SET views_count = views_count + 1 WHERE id = ?")->execute([$storyId]);

        $stmt = $db->prepare("SELECT s.*, 
                              COALESCE(u.name, REPLACE(SUBSTR(s.user_id, 1, 14), '_', ' ')) as author_name, 
                              COALESCE(u.email, s.user_id || '@racconmail.eu') as author_email, 
                              COALESCE(u.avatar, '/raccoon-logo.png') as author_avatar,
                              COALESCE(u.bio, 'Author & Storyteller on Raccon Stories') as author_bio 
                              FROM community_stories s 
                              LEFT JOIN users u ON s.user_id = u.id 
                              WHERE s.id = ?");
        $stmt->execute([$storyId]);
        $story = $stmt->fetch();
        if (!$story) {
            http_response_code(404);
            echo json_encode(['error' => 'Story not found']);
            exit;
        }

        if ($currentUserId) {
            $stmtLiked = $db->prepare("SELECT 1 FROM story_likes WHERE story_id = ? AND user_id = ?");
            $stmtLiked->execute([$storyId, $currentUserId]);
            $story['has_liked'] = (bool)$stmtLiked->fetchColumn();
        } else {
            $story['has_liked'] = false;
        }

        // Fetch comments
        $stmtC = $db->prepare("SELECT c.*, 
                               COALESCE(u.name, REPLACE(SUBSTR(c.user_id, 1, 14), '_', ' ')) as author_name, 
                               COALESCE(u.avatar, '/raccoon-logo.png') as author_avatar 
                               FROM story_comments c 
                               LEFT JOIN users u ON c.user_id = u.id 
                               WHERE c.story_id = ? 
                               ORDER BY c.created_at ASC");
        $stmtC->execute([$storyId]);
        $comments = $stmtC->fetchAll();

        echo json_encode([
            'success' => true,
            'story' => $story,
            'comments' => $comments,
            'isLoggedIn' => !empty($currentUserId),
            'currentUserId' => $currentUserId
        ]);
        exit;
    }

    // 13.3 POST /api/community/stories (Post a Story / Wall Story)
    if ($route === 'community/stories' && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Access restricted: Please sign in or join free to publish stories.']);
            exit;
        }

        $title = trim($input['title'] ?? '');
        $category = trim($input['category'] ?? 'fantasy');
        $formatStatus = trim($input['format_status'] ?? 'ongoing');
        $targetAudience = trim($input['target_audience'] ?? 'general');
        $summary = trim($input['summary'] ?? '');
        $content = trim($input['content'] ?? '');
        $tags = trim($input['tags'] ?? '');
        $coverImage = trim($input['cover_image'] ?? '');

        if (!$title && $content) {
            $title = mb_substr(strip_tags($content), 0, 45) . (mb_strlen($content) > 45 ? '...' : '');
        }

        if (!$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Please provide story content.']);
            exit;
        }

        if (!$summary) {
            $summary = mb_substr(strip_tags($content), 0, 160) . (mb_strlen($content) > 160 ? '...' : '');
        }

        $wordCount = str_word_count(strip_tags($content));
        $readTime = max(1, (int)ceil($wordCount / 220));

        $storyId = 'story_' . uniqid();
        $stmt = $db->prepare("INSERT INTO community_stories 
            (id, user_id, title, category, format_status, target_audience, summary, content, cover_image, tags, word_count, reading_time, views_count, likes_count, comments_count) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0)");
        $stmt->execute([
            $storyId, $currentUserId, $title, $category, $formatStatus, $targetAudience, $summary, $content, $coverImage, $tags, $wordCount, $readTime
        ]);

        echo json_encode([
            'success' => true,
            'id' => $storyId,
            'message' => 'Your story is now live in the Raccon Stories Library! 📚🦝'
        ]);
        exit;
    }

    // 13.4 POST /api/community/stories/:id/comments (Review / Comment)
    if (preg_match('#^community/stories/([^/]+)/comments$#', $route, $m) && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Please sign in to comment on stories.']);
            exit;
        }

        $storyId = $m[1];
        $content = trim($input['content'] ?? '');
        if (!$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment cannot be blank.']);
            exit;
        }

        $cid = 'sc_' . uniqid();
        $stmt = $db->prepare("INSERT INTO story_comments (id, story_id, user_id, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$cid, $storyId, $currentUserId, $content]);
        @$db->prepare("UPDATE community_stories SET comments_count = comments_count + 1 WHERE id = ?")->execute([$storyId]);

        echo json_encode(['success' => true, 'id' => $cid, 'message' => 'Review posted!']);
        exit;
    }

    // 13.5 POST /api/community/stories/:id/like (Like Story)
    if (preg_match('#^community/stories/([^/]+)/like$#', $route, $m) && $method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['error' => 'Please sign in to like stories.']);
            exit;
        }

        $storyId = $m[1];
        $stmtCheck = $db->prepare("SELECT 1 FROM story_likes WHERE story_id = ? AND user_id = ?");
        $stmtCheck->execute([$storyId, $currentUserId]);
        $hasLiked = (bool)$stmtCheck->fetchColumn();

        if ($hasLiked) {
            $db->prepare("DELETE FROM story_likes WHERE story_id = ? AND user_id = ?")->execute([$storyId, $currentUserId]);
            $db->prepare("UPDATE community_stories SET likes_count = MAX(0, likes_count - 1) WHERE id = ?")->execute([$storyId]);
            $action = 'unliked';
        } else {
            $db->prepare("INSERT INTO story_likes (story_id, user_id) VALUES (?, ?)")->execute([$storyId, $currentUserId]);
            $db->prepare("UPDATE community_stories SET likes_count = likes_count + 1 WHERE id = ?")->execute([$storyId]);
            $action = 'liked';
        }

        $likesCount = (int)$db->prepare("SELECT likes_count FROM community_stories WHERE id = ?")->execute([$storyId]) ? $db->query("SELECT likes_count FROM community_stories WHERE id = '$storyId'")->fetchColumn() : 0;

        echo json_encode([
            'success' => true,
            'action' => $action,
            'likes_count' => $likesCount
        ]);
        exit;
    }
`;

if (!api.includes('RACCON STORIES LIBRARY & STORY WALL API')) {
  api = api.replace('    // Default 404', storiesBackendCode + '\n    // Default 404');
  fs.writeFileSync(apiPath, api, 'utf8');
  console.log('Successfully injected stories backend API into public/api.php!');
} else {
  console.log('Stories backend already present in api.php!');
}

<?php
declare(strict_types=1);

namespace RacconMail\Core;

/**
 * World Stories Integration & Cultural Literacy Engine
 */
class StoryEngine
{
    private string $catalogPath;

    public function __construct(?string $catalogPath = null)
    {
        $this->catalogPath = $catalogPath ?? (__DIR__ . '/../public/worldstories_catalog.json');
    }

    /**
     * Retrieves the list of available stories (with caching)
     */
    public function getCatalog(): array
    {
        if (!file_exists($this->catalogPath)) {
            return [];
        }

        $raw = file_get_contents($this->catalogPath);
        return json_decode($raw, true) ?: [];
    }

    /**
     * Retrieves full story text, origin, and audio narrative link by slug
     */
    public function getStory(string $id): ?array
    {
        $catalog = $this->getCatalog();
        foreach ($catalog as $story) {
            if (($story['id'] ?? '') === $id || ($story['slug'] ?? '') === $id) {
                return $story;
            }
        }
        return null;
    }
}

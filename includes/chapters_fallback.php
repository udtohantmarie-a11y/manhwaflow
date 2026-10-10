<?php
// includes/chapters_fallback.php - Intelligent Universal Secondary Provider for DMCA & Missing Chapters

class ChaptersFallback {
    const ASURA_BASE = 'https://asurascans.com';
    const WC_BASE = 'https://weebcentral.com';
    const ANISA_BASE = 'https://anisascans.in';
    const CACHE_DIR = __DIR__ . '/../cache';

    // Direct Pre-mappings for Asura Scans (Murim & Hot Action scanlations)
    public static $asuraMap = [
        '6e4805a6-75ab-462d-883c-4ddedb8e4df6' => 'nano-machine-bd5bdaf8',
        '91ccce69-925a-4c75-abaa-716dd7e168de' => 'return-of-the-mount-hua-sect-bd5bdaf8',
        'fbf326f7-ccbd-4d54-bbad-852d76e46523' => 'revenge-of-the-iron-blooded-sword-hound-bd5bdaf8',
        'd7f56ace-cd30-48b9-8b64-afeca0077fca' => 'the-greatest-estate-developer-bd5bdaf8',
        '85a8ebda-9959-4244-ad03-a2d9f6a746a2' => 'swordmasters-youngest-son-bd5bdaf8',
        '14b81959-13ed-46eb-a277-da66f672acb5' => 'the-world-after-the-end-bd5bdaf8',
        '4d97b35d-2d9f-4369-964f-3b0ce2c7fbbc' => 'infinite-mage-bd5bdaf8',
        '58167656-a065-4b49-b499-bd73d00555d5' => 'pick-me-up-infinite-gacha-bd5bdaf8',
        '4a973243-952e-44d7-a50f-883b4b7c9cc2' => 'sss-class-suicide-hunter-bd5bdaf8'
    ];

    // Direct Pre-mappings for WeebCentral (Full archive for mega-hits with 200+ chapters)
    public static $weebCentralMap = [
        '32d76d19-8a05-4db0-9fc2-e0b0648fe9d0' => '01J76XYCPSY3C4BNPBRY8JMCBE', // Solo Leveling (201 ch)
        '596191eb-69ee-4401-983e-cc07e277fa17' => '01J76XYBD282Y3XKX0KRGD64Q5', // Lookism (627+ ch)
        'c0ee660b-f9f2-45c3-8068-5123ff53f84a' => '01J76XY7M0W9WWJ55VJYYB2J1S', // Tower of God (652+ ch)
        '9a414441-bbad-43f1-a3a7-dc262ca790a3' => '01J76XYDMWWJX3T4BFWR84FQQK', // Omniscient Reader (312+ ch)
        'b70c015b-e66d-4952-9721-e00965d5df29' => '01J76XYE23859ZWDP7BJ520AKY', // The Beginning After The End
        'b9687e83-9b88-44e2-8869-702b545f4459' => '01J76XYA1TQYKV6W4BCMR2WD5M', // Wind Breaker
        '8b3ce3c1-0c5a-4365-985d-8b89e701ba53' => '01J76XYD3Q2Q7HYYMB3FSDPSKC'  // Eleceed
    ];

    /**
     * Check cached dynamic series mappings
     */
    public static function getMapping($mangaId) {
        if (isset(self::$weebCentralMap[$mangaId])) {
            return ['type' => 'wc', 'id' => self::$weebCentralMap[$mangaId]];
        }
        if (isset(self::$asuraMap[$mangaId])) {
            return ['type' => 'asura', 'id' => self::$asuraMap[$mangaId]];
        }

        $mapFile = self::CACHE_DIR . '/series_mappings.json';
        if (file_exists($mapFile)) {
            $mappings = json_decode(@file_get_contents($mapFile), true) ?: [];
            if (isset($mappings[$mangaId])) return $mappings[$mangaId];
        }
        return null;
    }

    /**
     * Delete a dynamic mapping from cache
     */
    public static function deleteMapping($mangaId) {
        $mapFile = self::CACHE_DIR . '/series_mappings.json';
        if (file_exists($mapFile)) {
            $mappings = json_decode(@file_get_contents($mapFile), true) ?: [];
            if (isset($mappings[$mangaId])) {
                unset($mappings[$mangaId]);
                @file_put_contents($mapFile, json_encode($mappings, JSON_PRETTY_PRINT));
            }
        }
    }

    /**
     * Accurate Title Matcher - Checks if candidate series title matches target title
     */
    public static function isTitleMatch($title1, $title2) {
        if (empty($title1) || empty($title2)) return false;

        // Clean hash suffixes, brackets, and extra whitespace
        $clean1 = preg_replace('/\s*[\(\[].*?[\)\]]/', '', $title1);
        $clean2 = preg_replace('/\s*[\(\[].*?[\)\]]/', '', $title2);
        $clean1 = preg_replace('/-[a-f0-9]{8}$/i', '', $clean1);
        $clean2 = preg_replace('/-[a-f0-9]{8}$/i', '', $clean2);

        $norm1 = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '', $clean1)));
        $norm2 = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '', $clean2)));

        if (empty($norm1) || empty($norm2)) return false;
        if ($norm1 === $norm2) return true;

        // Significant words overlap check
        $stopWords = ['the', 'a', 'an', 'of', 'and', 'in', 'to', 'for', 'with', 'on', 'at', 'from', 'by', 'season', 'manhwa', 'comic', 'webtoon'];
        $words1 = array_values(array_filter(explode(' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $clean1))), fn($w) => strlen($w) > 1 && !in_array($w, $stopWords)));
        $words2 = array_values(array_filter(explode(' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $clean2))), fn($w) => strlen($w) > 1 && !in_array($w, $stopWords)));

        if (!empty($words1) && !empty($words2)) {
            if (count($words1) === 1 || count($words2) === 1) {
                return ($norm1 === $norm2);
            }

            $common = array_intersect($words1, $words2);
            $commonCount = count($common);
            $minWordCount = min(count($words1), count($words2));
            $maxWordCount = max(count($words1), count($words2));

            if ($commonCount >= 2 && ($commonCount / $minWordCount) >= 0.75) {
                if ($minWordCount / $maxWordCount >= 0.5) {
                    return true;
                }
            }
        }

        // Exact containment with length ratio
        if (str_contains($norm1, $norm2) || str_contains($norm2, $norm1)) {
            $minLen = min(strlen($norm1), strlen($norm2));
            $maxLen = max(strlen($norm1), strlen($norm2));
            if ($minLen / $maxLen >= 0.70) return true;
        }

        similar_text($norm1, $norm2, $pct);
        return $pct >= 78;
    }

    /**
     * Save dynamic mapping to cache file
     */
    public static function saveMapping($mangaId, $type, $targetId) {
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0777, true);
        }
        $mapFile = self::CACHE_DIR . '/series_mappings.json';
        $mappings = [];
        if (file_exists($mapFile)) {
            $mappings = json_decode(@file_get_contents($mapFile), true) ?: [];
        }
        $mappings[$mangaId] = ['type' => $type, 'id' => $targetId];
        @file_put_contents($mapFile, json_encode($mappings, JSON_PRETTY_PRINT));
    }

    /**
     * Get chapters for a manga when MangaDex is missing or incomplete
     */
    public static function getChapters($mangaId, $limit = 1000, $mangaTitle = '') {
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0777, true);
        }

        // 1. Check existing pre-map or cached dynamic mapping
        $mapping = self::getMapping($mangaId);
        if ($mapping) {
            if ($mapping['type'] === 'wc') {
                $chList = self::getWeebCentralChapters($mapping['id'], $limit);
                if (!empty($chList)) return $chList;
            } elseif ($mapping['type'] === 'asura') {
                $chList = self::getAsuraChapters($mapping['id'], $limit);
                if (!empty($chList)) return $chList;
            } elseif ($mapping['type'] === 'anisa') {
                $chList = self::getAnisaChapters($mapping['id'], $limit);
                if (!empty($chList)) return $chList;
            }
        }

        // 2. Dynamic multi-source search by title if title is provided
        if (!empty($mangaTitle)) {
            // A. Search WeebCentral (huge complete catalog)
            $wcId = self::searchWeebCentralId($mangaTitle);
            if (!$wcId) {
                $cleanTitle = trim(preg_replace('/\s*[\(\[].*?[\)\]]/', '', $mangaTitle));
                $cleanTitle = trim(explode(':', $cleanTitle)[0]);
                $cleanTitle = trim(explode('-', $cleanTitle)[0]);
                if (strlen($cleanTitle) >= 3 && $cleanTitle !== $mangaTitle) {
                    $wcId = self::searchWeebCentralId($cleanTitle);
                }
            }
            if ($wcId) {
                $chapters = self::getWeebCentralChapters($wcId, $limit);
                if (!empty($chapters)) {
                    self::saveMapping($mangaId, 'wc', $wcId);
                    return $chapters;
                }
            }

            // B. Search Asura Scans (Murim, Action, System)
            $asuraSlug = self::searchAsuraSlug($mangaTitle);
            if ($asuraSlug) {
                $chapters = self::getAsuraChapters($asuraSlug, $limit);
                if (!empty($chapters)) {
                    self::saveMapping($mangaId, 'asura', $asuraSlug);
                    return $chapters;
                }
            }

            // C. Search Anisa Scans (Trending Manhwa & Scanlations)
            $anisaSlug = self::searchAnisaSlug($mangaTitle);
            if ($anisaSlug) {
                $chapters = self::getAnisaChapters($anisaSlug, $limit);
                if (!empty($chapters)) {
                    self::saveMapping($mangaId, 'anisa', $anisaSlug);
                    return $chapters;
                }
            }
        }

        return [];
    }

    /**
     * Fetch chapters from Weeb Central (Universal parser for all series & seasons)
     */
    public static function getWeebCentralChapters($seriesId, $limit = 1000) {
        $cacheFile = self::CACHE_DIR . "/fallback_wc_{$seriesId}_chapters.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 43200)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached)) return array_slice($cached, 0, $limit);
        }

        $url = self::WC_BASE . "/series/{$seriesId}/full-chapter-list";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: [];
            return [];
        }

        preg_match_all('/href="[^"]*\/chapters\/([A-Z0-9]+)"[^>]*>[\s\S]*?<span class="">([^<]+)<\/span>/i', $html, $matches, PREG_SET_ORDER);

        $chapters = [];
        $seen = [];
        $totalMatches = count($matches);

        foreach ($matches as $idx => $m) {
            $chId = $m[1];
            $rawTitle = trim($m[2]);

            // Extract numeric chapter
            $chNum = null;
            if (preg_match('/(?:Chapter|Episode|Ch\.|Ep\.)\s*([0-9.]+)/i', $rawTitle, $nm)) {
                $chNum = floatval($nm[1]);
            } elseif (preg_match('/([0-9.]+)/', $rawTitle, $nm)) {
                $chNum = floatval($nm[1]);
            } else {
                $chNum = floatval($totalMatches - $idx);
            }

            // Prevent duplicate numbers if multiple seasons exist
            $displayTitle = $rawTitle;
            if (!str_starts_with(strtolower($displayTitle), 'chapter') && 
                !str_starts_with(strtolower($displayTitle), 's') && 
                !str_starts_with(strtolower($displayTitle), 'episode')) {
                $displayTitle = "Chapter " . $chNum . " - " . $displayTitle;
            }

            $key = $chNum;
            if (isset($seen[$key])) {
                $key = $chNum . '.' . $idx;
            }
            $seen[$key] = true;

            $chapters[$key] = [
                'id' => "wc_{$chId}",
                'chapter_number' => $chNum,
                'title' => $displayTitle,
                'pages' => 25,
                'language' => 'en',
                'externalUrl' => null,
                'created_at' => date('Y-m-d')
            ];
        }

        // Sort ascending by chapter number
        uasort($chapters, function($a, $b) {
            if ($a['chapter_number'] == $b['chapter_number']) return 0;
            return ($a['chapter_number'] < $b['chapter_number']) ? -1 : 1;
        });

        $list = array_values($chapters);
        if (!empty($list)) {
            @file_put_contents($cacheFile, json_encode($list));
        }

        return array_slice($list, 0, $limit);
    }

    /**
     * Fetch chapters from Asura Scans
     */
    public static function getAsuraChapters($slug, $limit = 1000) {
        $cacheFile = self::CACHE_DIR . "/fallback_asura_{$slug}_chapters.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 43200)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached)) return array_slice($cached, 0, $limit);
        }

        $url = self::ASURA_BASE . "/comics/{$slug}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: [];
            return [];
        }

        preg_match_all('/href="(\/comics\/([^\/]+)\/chapter\/([0-9.]+))"[^>]*>(.*?)<\/a>/si', $html, $matches, PREG_SET_ORDER);
        $chapters = [];
        foreach ($matches as $m) {
            $chNum = floatval($m[3]);
            if (!isset($chapters[$chNum])) {
                $chapters[$chNum] = [
                    'id' => "asura_{$slug}_ch_{$chNum}",
                    'chapter_number' => $chNum,
                    'title' => "Chapter " . $m[3],
                    'pages' => 20,
                    'language' => 'en',
                    'externalUrl' => null,
                    'created_at' => date('Y-m-d')
                ];
            }
        }

        ksort($chapters);
        $list = array_values($chapters);
        if (!empty($list)) {
            @file_put_contents($cacheFile, json_encode($list));
        }

        return array_slice($list, 0, $limit);
    }

    /**
     * Search WeebCentral dynamically for matching series ID
     */
    public static function searchWeebCentralId($title) {
        $clean = trim(preg_replace('/\s*[\(\[].*?[\)\]]/', '', $title));
        $q = urlencode($clean);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::WC_BASE . "/search/data?text={$q}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) return null;

        if (preg_match_all('/href="https:\/\/weebcentral\.com\/series\/([A-Z0-9]+)\/([^"]+)"[^>]*class="[^"]*link-hover">([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $candidateId = $m[1];
                $candidateTitle = html_entity_decode(trim($m[3]), ENT_QUOTES | ENT_HTML5);
                if (self::isTitleMatch($clean, $candidateTitle) || self::isTitleMatch($title, $candidateTitle)) {
                    return $candidateId;
                }
            }
        }
        return null;
    }

    /**
     * Search Asura Scans dynamically for matching series slug
     */
    public static function searchAsuraSlug($title) {
        $clean = trim(preg_replace('/\s*[\(\[].*?[\)\]]/', '', $title));
        $cleanAlnum = trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $clean));
        $q = urlencode($cleanAlnum);
        $ch = curl_init(self::ASURA_BASE . '/comics?name=' . $q);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) return null;

        if (preg_match_all('/href="\/comics\/([a-z0-9-]+)"[^>]*>/i', $html, $m)) {
            $slugs = array_values(array_unique($m[1]));
            foreach ($slugs as $s) {
                $cleanSlug = preg_replace('/-[a-f0-9]{8}$/', '', $s);
                $cleanSlugTitle = str_replace('-', ' ', $cleanSlug);
                if (self::isTitleMatch($clean, $cleanSlugTitle) || self::isTitleMatch($title, $cleanSlugTitle)) {
                    return $s;
                }
            }
        }
        return null;
    }

    /**
     * Search Anisa Scans dynamically for matching series slug
     */
    public static function searchAnisaSlug($title) {
        $clean = trim(preg_replace('/\s*[\(\[].*?[\)\]]/', '', $title));
        $q = urlencode($clean);
        $url = self::ANISA_BASE . "/?s={$q}&post_type=wp-manga";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) return null;

        // Pattern 1: Madara standard search result titles
        if (preg_match_all('/<a href="https:\/\/anisascans\.in\/manga\/([^"\/]+)\/"[^>]*title="([^"]+)"/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $candidateSlug = $m[1];
                $candidateTitle = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5);
                if (self::isTitleMatch($clean, $candidateTitle) || self::isTitleMatch($title, $candidateTitle)) {
                    return $candidateSlug;
                }
            }
        }

        // Pattern 2: Any manga link in search results
        if (preg_match_all('/href="https:\/\/anisascans\.in\/manga\/([^"\/]+)\/"/i', $html, $matches2)) {
            $slugs = array_values(array_unique($matches2[1]));
            foreach ($slugs as $s) {
                $slugTitle = str_replace('-', ' ', $s);
                if (self::isTitleMatch($clean, $slugTitle) || self::isTitleMatch($title, $slugTitle)) {
                    return $s;
                }
            }
        }

        return null;
    }

    /**
     * Fetch chapters from Anisa Scans (Madara theme AJAX endpoint)
     */
    public static function getAnisaChapters($slug, $limit = 1000) {
        $cacheFile = self::CACHE_DIR . "/fallback_anisa_{$slug}_chapters.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 43200)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached)) return array_slice($cached, 0, $limit);
        }

        $url = self::ANISA_BASE . "/manga/{$slug}/ajax/chapters/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: [];
            return [];
        }

        preg_match_all('/href="https:\/\/anisascans\.in\/manga\/[^\/]+\/([^"\/]+)\/"[^>]*>(.*?)<\/a>/si', $html, $matches, PREG_SET_ORDER);
        $chapters = [];

        foreach ($matches as $m) {
            $chSlug = trim($m[1]);
            $chNum = null;
            if (preg_match('/(?:chapter|ch)[-_]?([0-9.]+)/i', $chSlug, $cn)) {
                $chNum = floatval($cn[1]);
            } elseif (preg_match('/([0-9.]+)/', $chSlug, $cn)) {
                $chNum = floatval($cn[1]);
            }

            if ($chNum === null) continue;

            $key = $chNum;
            if (!isset($chapters[$key])) {
                $chapters[$key] = [
                    'id' => "anisa_{$slug}_ch_{$chSlug}",
                    'chapter_number' => $chNum,
                    'title' => "Chapter " . $chNum,
                    'pages' => 20,
                    'language' => 'en',
                    'externalUrl' => null,
                    'created_at' => date('Y-m-d')
                ];
            }
        }

        ksort($chapters);
        $list = array_values($chapters);
        if (!empty($list)) {
            @file_put_contents($cacheFile, json_encode($list));
        }

        return array_slice($list, 0, $limit);
    }

    /**
     * Fetch chapter images from Anisa Scans
     */
    public static function getAnisaPages($slug, $chapterSlug) {
        $cacheFile = self::CACHE_DIR . "/fallback_anisa_{$slug}_{$chapterSlug}_pages.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached)) return $cached;
        }

        $url = self::ANISA_BASE . "/manga/{$slug}/{$chapterSlug}/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: [];
            return [];
        }

        preg_match_all('/<img[^>]+(?:data-src|data-full-url|src)="([^">]+)"[^>]*class="[^"]*wp-manga-chapter-img[^"]*"/i', $html, $imgMatches);
        $rawPages = $imgMatches[1] ?? [];
        if (empty($rawPages)) {
            preg_match_all('/class="page-break[^"]*"[\s\S]*?<img[^>]+(?:data-src|data-full-url|src)="([^">]+)"/i', $html, $imgMatches2);
            $rawPages = $imgMatches2[1] ?? [];
        }

        $pages = [];
        foreach ($rawPages as $p) {
            $cleanUrl = trim($p);
            if (!empty($cleanUrl) && !str_contains($cleanUrl, 'logo') && !str_contains($cleanUrl, 'banner')) {
                $pages[] = $cleanUrl;
            }
        }
        $pages = array_values(array_unique($pages));

        if (!empty($pages)) {
            @file_put_contents($cacheFile, json_encode($pages));
        }
        return $pages;
    }

    /**
     * Get Chapter Pages for WeebCentral, Asura Scans, or Anisa Scans
     */
    public static function getPages($chapterId) {
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0777, true);
        }

        // --- 1. WeebCentral Chapter Images ---
        if (str_starts_with($chapterId, 'wc_')) {
            $rawId = substr($chapterId, 3);
            $cacheFile = self::CACHE_DIR . "/fallback_wc_ch_{$rawId}_pages.json";
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
                $cached = json_decode(@file_get_contents($cacheFile), true);
                if (!empty($cached)) return $cached;
            }

            $url = self::WC_BASE . "/chapters/{$rawId}/images";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $imgHtml = curl_exec($ch);
            curl_close($ch);

            if (!$imgHtml) {
                if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: [];
                return [];
            }

            preg_match_all('/<img[^>]+src="([^">]+)"/i', $imgHtml, $imgMatches);
            $rawPages = array_values(array_unique($imgMatches[1] ?? []));

            // Filter out non-chapter UI assets like logos or badges
            $pages = [];
            foreach ($rawPages as $u) {
                if (!str_contains($u, '/static/') && !str_contains($u, 'brand.png') && !str_contains($u, 'badge')) {
                    $pages[] = $u;
                }
            }

            if (!empty($pages)) {
                @file_put_contents($cacheFile, json_encode($pages));
            }
            return $pages;
        }

        // --- 2. Asura Scans Chapter Images ---
        if (str_starts_with($chapterId, 'asura_')) {
            if (!preg_match('/^asura_(.+)_ch_([0-9.]+)$/', $chapterId, $matches)) {
                return [];
            }

            $slug = $matches[1];
            $chNum = $matches[2];

            $cacheFile = self::CACHE_DIR . "/fallback_asura_{$slug}_ch_{$chNum}_pages.json";
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
                $cached = json_decode(@file_get_contents($cacheFile), true);
                if (!empty($cached)) return $cached;
            }

            $url = self::ASURA_BASE . "/comics/{$slug}/chapter/{$chNum}";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $html = curl_exec($ch);
            curl_close($ch);

            if (!$html) {
                if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: [];
                return [];
            }

            preg_match_all('/https:\/\/cdn\.asurascans\.com\/asura-images\/(?:chapters|chapters-restored)\/[^"\'\s&<>]+?\.(?:webp|jpg|png)/i', $html, $imgMatches);
            $pages = array_values(array_unique($imgMatches[0]));

            if (!empty($pages)) {
                @file_put_contents($cacheFile, json_encode($pages));
            }
            return $pages;
        }

        // --- 3. Anisa Scans Chapter Images ---
        if (str_starts_with($chapterId, 'anisa_')) {
            if (preg_match('/^anisa_(.+)_ch_(.+)$/', $chapterId, $matches)) {
                return self::getAnisaPages($matches[1], $matches[2]);
            }
            return [];
        }

        return [];
    }
}

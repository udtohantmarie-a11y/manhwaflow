<?php
// includes/chapters_fallback.php - Intelligent Universal Secondary Provider for DMCA & Missing Chapters

class ChaptersFallback {
    const ASURA_BASE = 'https://asurascans.com';
    const WC_BASE = 'https://weebcentral.com';
    const ANISA_BASE = 'https://anisascans.in';
    const ATHREA_BASE = 'https://athreascans.com';
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
        'b9687e83-9b88-44e2-8869-702b545f4459' => '01J76XYA1TQYKV6W4BCMR2WD5M', // Wind Breaker (500+ ch)
        '8b3ce3c1-0c5a-4365-985d-8b89e701ba53' => '01J76XYD3Q2Q7HYYMB3FSDPSKC', // Eleceed (300+ ch)
        '7f15e45c-b7fd-4164-8d8e-ede14a817f0d' => '01JNKH97S0APRHFW53V9DSFCGH', // The Extra's Academy Survival Guide (starts at 0 & 1, 128 ch)
        '6a468761-5bd6-4de0-a0cb-47cb456ac2e0' => '01J76XYCN4PDFA5CZQXHK82PXD', // A Returner's Magic Should Be Special (starts at 1, 269 ch)
        '14569f2f-f66a-4c67-ac7f-a37823a0fa23' => '01J76XYDMZ059WG12B0QQWRYXS', // Villains Are Destined to Die (starts at 1, 170+ ch)
        '4a973243-952e-44d7-a50f-883b4b7c9cc2' => '01J76XYEADS8EDBWFPVSF8J3VG', // SSS-Class Revival Hunter (starts at 1, 150+ ch)
        'd993f789-e7e5-4832-92fd-37614220b427' => '01J76XYCRFYM9JT9WE2M8Q6D6T', // The Skeleton Soldier Failed to Defend the Dungeon (starts at 1, 280+ ch)
        '73bc69fa-9ba9-4533-a243-ebc11651339f' => '01J76XYDQNFBDE0MD2EDS122Y5', // The Villainess Turns the Hourglass (starts at 1, 125 ch)
        '1ffca916-3ad7-46d2-9591-a9b39e639971' => '01J76XYDMS2ZM3HF00619R92NJ', // Second Life Ranker (starts at 1, 170+ ch)
        '50fc2f0f-aeac-4152-82ba-164b3bb3b5b3' => '01J76XYDQJ8P5BD4Z1XVQH53DT', // The Monstrous Duke's Adopted Daughter (starts at 1, 150+ ch)
        '722a45c0-5e55-40f2-929b-ff69b0989edb' => '01J76XYDQPCQQR5DQXXF8NHKWK', // Who Made Me a Princess (starts at 1, 125 ch)
        'b407de00-75a5-415a-a001-585fb41b9cf2' => '01J76XYE21JW91FDRNMX32J0WS', // The Max Level Hero Strikes Back (starts at 1, 180+ ch)
        '73886188-f459-4b80-8781-66a60520b420' => '01J76XYDQ9JTWW4THBJWPXNJ9H', // The Fantasie of a Stepmother (starts at 1, 130+ ch)
        'f89ed57a-e4c0-48f5-b664-8ef88aa87fd9' => '01J76XYEFWZNMKWCPH2W9SVSSD', // I Shall Master This Family (starts at 1, 160+ ch)
        '85b51b37-0ce6-4144-a19b-6b064bc2c2ae' => '01J76XYDQA0S3W7B918CTNC9Q4', // Beware the Villainess! (starts at 1, 130+ ch)
        '58167656-a065-4b49-b499-bd73d00555d5' => '01J76XYGY7FDV857J4HJAZ131K', // Pick Me Up, Infinite Gacha (starts at 1, 130+ ch)
        'b24f5a32-15f9-4458-8686-2a78f24b2203' => '01J76XYF02HVMZAMXFFS51ANAM', // Return of the Mad Demon (starts at 1, 140+ ch)
        'a603952f-8706-444f-8367-73b37996c568' => '01J76XYB7A985W3P3K887X9375'  // Damn Reincarnation (starts at 1, 80+ ch)
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
    public static function getChapters($mangaId, $limit = 1000, $mangaTitle = '', $altTitle = '') {
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0777, true);
        }

        // 1. Check existing pre-map or cached dynamic mapping
        $mapping = self::getMapping($mangaId);
        if ($mapping) {
            $chList = [];
            if ($mapping['type'] === 'wc') {
                $chList = self::getWeebCentralChapters($mapping['id'], $limit);
            } elseif ($mapping['type'] === 'asura') {
                $chList = self::getAsuraChapters($mapping['id'], $limit);
            } elseif ($mapping['type'] === 'anisa') {
                $chList = self::getAnisaChapters($mapping['id'], $limit);
            } elseif ($mapping['type'] === 'athrea') {
                $chList = self::getAthreaChapters($mapping['id'], $limit);
            }
            if (!empty($chList)) return $chList;
            // Clean up invalid or stale dynamic mapping
            self::deleteMapping($mangaId);
        }

        // 2. If title is not passed, auto-fetch from MangaDex
        if (empty($mangaTitle)) {
            require_once __DIR__ . '/mangadex.php';
            $details = MangaDexAPI::getMangaDetailsLive($mangaId);
            if ($details) {
                $mangaTitle = $details['title'] ?? '';
                if (empty($altTitle)) {
                    $altTitle = $details['alt_title'] ?? '';
                }
            }
        }

        // 3. Dynamic multi-source search across candidate titles
        $candidateTitles = array_values(array_filter([$mangaTitle, $altTitle]));
        foreach ($candidateTitles as $titleToSearch) {
            if (empty($titleToSearch) || strlen(trim($titleToSearch)) < 2) continue;

            // A. Search WeebCentral (largest complete archive)
            $wcId = self::searchWeebCentralId($titleToSearch);
            if ($wcId) {
                $chapters = self::getWeebCentralChapters($wcId, $limit);
                if (!empty($chapters)) {
                    self::saveMapping($mangaId, 'wc', $wcId);
                    return $chapters;
                }
            }

            // B. Search Asura Scans (Action, Murim, System)
            $asuraSlug = self::searchAsuraSlug($titleToSearch);
            if ($asuraSlug) {
                $chapters = self::getAsuraChapters($asuraSlug, $limit);
                if (!empty($chapters)) {
                    self::saveMapping($mangaId, 'asura', $asuraSlug);
                    return $chapters;
                }
            }

            // C. Search Anisa Scans (Trending Manhwa & Scanlations)
            $anisaSlug = self::searchAnisaSlug($titleToSearch);
            if ($anisaSlug) {
                $chapters = self::getAnisaChapters($anisaSlug, $limit);
                if (!empty($chapters)) {
                    self::saveMapping($mangaId, 'anisa', $anisaSlug);
                    return $chapters;
                }
            }

            // D. Search Athrea Scans (Romance, Drama, Shoujo & Smut Scanlations)
            $athreaSlug = self::searchAthreaSlug($titleToSearch);
            if ($athreaSlug) {
                $chapters = self::getAthreaChapters($athreaSlug, $limit);
                if (!empty($chapters)) {
                    self::saveMapping($mangaId, 'athrea', $athreaSlug);
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
     * Generate high-probability sanitized search queries for upstream providers
     */
    public static function generateSearchQueries($title) {
        if (empty($title)) return [];
        $queries = [];

        $clean = trim(preg_replace('/\s*[\(\[].*?[\)\]]/', '', $title));
        $queries[] = $clean;

        // Remove possessive 's or curly ’s
        $noPossessive = trim(preg_replace('/[\'’`]s\b/i', '', $clean));
        if ($noPossessive !== $clean) {
            $queries[] = $noPossessive;
        }

        // Alphanumeric with spaces
        $alnum = trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $clean));
        $alnum = trim(preg_replace('/\s+/', ' ', $alnum));
        if (!empty($alnum)) {
            $queries[] = $alnum;
        }

        $alnumNoPossessive = trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $noPossessive));
        $alnumNoPossessive = trim(preg_replace('/\s+/', ' ', $alnumNoPossessive));
        if (!empty($alnumNoPossessive)) {
            $queries[] = $alnumNoPossessive;
        }

        // Subtitle cuts (colon, hyphen, comma)
        foreach ([':', '-', ','] as $sep) {
            if (str_contains($clean, $sep)) {
                $part = trim(explode($sep, $clean)[0]);
                if (strlen($part) >= 3) {
                    $queries[] = $part;
                }
            }
        }

        // Word subsets (first 3 words, first 2 words)
        $words = array_values(array_filter(explode(' ', $alnumNoPossessive)));
        if (count($words) >= 3) {
            $queries[] = implode(' ', array_slice($words, 0, 3));
            $queries[] = implode(' ', array_slice($words, 0, 2));
        }

        $stopWords = ['the', 'a', 'an', 'of', 'in', 'to', 'for', 'with', 'on', 'at', 'i', 'my'];
        $nonStop = array_values(array_filter($words, fn($w) => !in_array(strtolower($w), $stopWords)));
        if (count($nonStop) >= 2) {
            $queries[] = implode(' ', array_slice($nonStop, 0, 2));
            if (count($nonStop) >= 3) {
                $queries[] = implode(' ', array_slice($nonStop, 0, 3));
            }
        }

        $final = [];
        foreach ($queries as $q) {
            $trimmed = trim($q);
            if (strlen($trimmed) >= 3 && !in_array($trimmed, $final)) {
                $final[] = $trimmed;
            }
        }
        return $final;
    }

    /**
     * Search WeebCentral dynamically for matching series ID
     */
    public static function searchWeebCentralId($title) {
        $queries = self::generateSearchQueries($title);
        foreach ($queries as $q) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::WC_BASE . "/search/data?text=" . urlencode($q));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            $html = curl_exec($ch);
            curl_close($ch);

            if (!$html) continue;

            if (preg_match_all('/href="https:\/\/weebcentral\.com\/series\/([A-Z0-9]+)\/([^"]+)"[^>]*class="[^"]*link-hover">([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $candidateId = $m[1];
                    $candidateTitle = html_entity_decode(trim($m[3]), ENT_QUOTES | ENT_HTML5);
                    if (self::isTitleMatch($title, $candidateTitle)) {
                        return $candidateId;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Search Asura Scans dynamically for matching series slug
     */
    public static function searchAsuraSlug($title) {
        $queries = self::generateSearchQueries($title);
        foreach ($queries as $q) {
            $ch = curl_init(self::ASURA_BASE . '/comics?name=' . urlencode($q));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            $html = curl_exec($ch);
            curl_close($ch);

            if (!$html) continue;

            if (preg_match_all('/href="\/comics\/([a-z0-9-]+)"[^>]*>/i', $html, $m)) {
                $slugs = array_values(array_unique($m[1]));
                foreach ($slugs as $s) {
                    $cleanSlug = preg_replace('/-[a-f0-9]{8}$/', '', $s);
                    $cleanSlugTitle = str_replace('-', ' ', $cleanSlug);
                    if (self::isTitleMatch($title, $cleanSlugTitle)) {
                        return $s;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Search Anisa Scans dynamically for matching series slug
     */
    public static function searchAnisaSlug($title) {
        $queries = self::generateSearchQueries($title);
        foreach ($queries as $q) {
            $url = self::ANISA_BASE . "/?s=" . urlencode($q) . "&post_type=wp-manga";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            $html = curl_exec($ch);
            curl_close($ch);

            if (!$html) continue;

            // Pattern 1: Madara standard search result titles
            if (preg_match_all('/<a href="https:\/\/anisascans\.in\/manga\/([^"\/]+)\/"[^>]*title="([^"]+)"/i', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $candidateSlug = $m[1];
                    $candidateTitle = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5);
                    if (self::isTitleMatch($title, $candidateTitle)) {
                        return $candidateSlug;
                    }
                }
            }

            // Pattern 2: Any manga link in search results
            if (preg_match_all('/href="https:\/\/anisascans\.in\/manga\/([^"\/]+)\/"/i', $html, $matches2)) {
                $slugs = array_values(array_unique($matches2[1]));
                foreach ($slugs as $s) {
                    $slugTitle = str_replace('-', ' ', $s);
                    if (self::isTitleMatch($title, $slugTitle)) {
                        return $s;
                    }
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
     * Search Athrea Scans dynamically for matching series slug
     */
    public static function searchAthreaSlug($title) {
        $queries = self::generateSearchQueries($title);
        foreach ($queries as $q) {
            $url = self::ATHREA_BASE . "/?s=" . urlencode($q);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $html = curl_exec($ch);
            curl_close($ch);

            if (!$html) continue;

            // Pattern 1: Manga link with title attribute
            if (preg_match_all('/<a[^>]+href=["\'](?:https:\/\/athreascans\.com)?\/manga\/([^"\'\/]+)\/?["\'][^>]*title=["\']([^"\']+)["\']/i', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $candidateSlug = trim($m[1]);
                    $candidateTitle = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5);
                    if (self::isTitleMatch($title, $candidateTitle)) {
                        return $candidateSlug;
                    }
                }
            }

            // Pattern 2: Any manga link in search results
            if (preg_match_all('/href=["\'](?:https:\/\/athreascans\.com)?\/manga\/([^"\'\/]+)\/?["\']/i', $html, $matches2)) {
                $slugs = array_values(array_unique($matches2[1]));
                foreach ($slugs as $s) {
                    $slugTitle = str_replace('-', ' ', $s);
                    if (self::isTitleMatch($title, $slugTitle)) {
                        return $s;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Fetch chapters from Athrea Scans
     */
    public static function getAthreaChapters($slug, $limit = 1000) {
        $cacheFile = self::CACHE_DIR . "/fallback_athrea_{$slug}_chapters.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 43200)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached)) return array_slice($cached, 0, $limit);
        }

        $url = self::ATHREA_BASE . "/manga/{$slug}/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: [];
            return [];
        }

        $chapters = [];

        // Match chapters via data-num list items or chapter links
        if (preg_match_all('/<li[^>]*data-num=["\']([^"\']+)["\'][^>]*>[\s\S]*?<a[^>]+href=["\'](?:https:\/\/athreascans\.com)?\/([^"\'\/]+)\/?["\'][^>]*>([\s\S]*?)<\/a>/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $chNum = floatval($m[1]);
                $chSlug = trim($m[2]);
                $key = $chNum;
                if (!isset($chapters[$key])) {
                    $chapters[$key] = [
                        'id' => "athrea_{$slug}_ch_{$chSlug}",
                        'chapter_number' => $chNum,
                        'title' => "Chapter " . $chNum,
                        'pages' => 20,
                        'language' => 'en',
                        'externalUrl' => null,
                        'created_at' => date('Y-m-d')
                    ];
                }
            }
        }

        // Fallback matching if data-num is missing
        if (empty($chapters)) {
            if (preg_match_all('/<a[^>]+href=["\']https:\/\/athreascans\.com\/([^"\'\/]+)\/?["\'][^>]*>([\s\S]*?)<\/a>/i', $html, $matchesFallback, PREG_SET_ORDER)) {
                foreach ($matchesFallback as $m) {
                    $chSlug = trim($m[1]);
                    if (!preg_match('/(?:chapter|ch)[-_]?([0-9.]+)/i', $chSlug, $cn)) continue;
                    $chNum = floatval($cn[1]);
                    $key = $chNum;
                    if (!isset($chapters[$key])) {
                        $chapters[$key] = [
                            'id' => "athrea_{$slug}_ch_{$chSlug}",
                            'chapter_number' => $chNum,
                            'title' => "Chapter " . $chNum,
                            'pages' => 20,
                            'language' => 'en',
                            'externalUrl' => null,
                            'created_at' => date('Y-m-d')
                        ];
                    }
                }
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
     * Fetch chapter images from Athrea Scans
     */
    public static function getAthreaPages($slug, $chapterSlug) {
        $cacheFile = self::CACHE_DIR . "/fallback_athrea_{$slug}_{$chapterSlug}_pages.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached)) return $cached;
        }

        $url = self::ATHREA_BASE . "/{$chapterSlug}/";
        $ch = curl_init($url);
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

        $pages = [];

        // Method 1: ts_reader.run JSON payload
        if (preg_match('/ts_reader\.run\((.*?)\);/is', $html, $tsMatches)) {
            $tsData = json_decode($tsMatches[1], true);
            if (!empty($tsData['sources'])) {
                foreach ($tsData['sources'] as $src) {
                    if (!empty($src['images']) && is_array($src['images'])) {
                        foreach ($src['images'] as $imgUrl) {
                            $imgUrl = trim($imgUrl);
                            if (!empty($imgUrl) && !str_contains($imgUrl, 'logo') && !str_contains($imgUrl, 'banner')) {
                                $pages[] = $imgUrl;
                            }
                        }
                        if (!empty($pages)) break;
                    }
                }
            }
        }

        // Method 2: Fallback to #readerarea img tags
        if (empty($pages)) {
            if (preg_match('/<div[^>]*id=["\']readerarea["\'][^>]*>(.*?)<\/div>/is', $html, $readerArea)) {
                preg_match_all('/<img[^>]+(?:data-src|data-lazy-src|src)=["\']([^"\']+)["\']/i', $readerArea[1], $imgMatches);
                $rawPages = $imgMatches[1] ?? [];
                foreach ($rawPages as $p) {
                    $cleanUrl = trim($p);
                    if (!empty($cleanUrl) && !str_contains($cleanUrl, 'logo') && !str_contains($cleanUrl, 'banner')) {
                        $pages[] = $cleanUrl;
                    }
                }
            }
        }

        $pages = array_values(array_unique($pages));
        if (!empty($pages)) {
            @file_put_contents($cacheFile, json_encode($pages));
        }
        return $pages;
    }

    /**
     * Get directory listing of series from Athrea Scans (Cached for 6 hours)
     */
    public static function getAthreaDirectory($limit = 35) {
        $cacheFile = self::CACHE_DIR . "/athrea_directory.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 21600)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached)) return array_slice($cached, 0, $limit);
        }

        $url = self::ATHREA_BASE . "/manga/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: [];
            return [];
        }

        $items = [];
        preg_match_all('/<div class="bs">[\s\S]*?<div class="bsx">[\s\S]*?<a href="https:\/\/athreascans\.com\/manga\/([^"\/]+)\/?" title="([^"]+)"[\s\S]*?<img[^>]+src="([^">]+)"[\s\S]*?(?:<span[^>]*class="[^"]*type[^"]*"[^>]*>(.*?)<\/span>)?[\s\S]*?(?:<div[^>]*class="[^"]*adds[^"]*"[^>]*>[\s\S]*?<span[^>]*class="[^"]*epxs[^"]*"[^>]*>(.*?)<\/span>)?[\s\S]*?<\/div>[\s\S]*?<\/div>/i', $html, $cards, PREG_SET_ORDER);

        foreach ($cards as $c) {
            $slug = trim($c[1]);
            $title = html_entity_decode(trim($c[2]), ENT_QUOTES | ENT_HTML5);
            $cover = trim($c[3]);
            $type = !empty($c[4]) ? trim(strip_tags($c[4])) : 'Manhwa';
            $latest = !empty($c[5]) ? trim(strip_tags($c[5])) : 'Latest';

            $items[] = [
                'id' => "athrea_{$slug}",
                'slug' => $slug,
                'title' => $title,
                'cover_url' => $cover,
                'status' => 'Ongoing',
                'type' => $type ?: 'Manhwa',
                'rating' => number_format(4.7 + (abs(crc32($slug)) % 30) / 100, 1),
                'tags' => ['Romance', 'Drama', 'Webtoon'],
                'latest_chapter' => $latest
            ];
        }

        if (!empty($items)) {
            @file_put_contents($cacheFile, json_encode($items));
        }

        return array_slice($items, 0, $limit);
    }

    /**
     * Get detailed metadata for an Athrea series
     */
    public static function getAthreaDetails($slug) {
        $cacheFile = self::CACHE_DIR . "/athrea_details_{$slug}.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 43200)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached)) return $cached;
        }

        $url = self::ATHREA_BASE . "/manga/{$slug}/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: null;
            return null;
        }

        // Title
        $title = ucwords(str_replace('-', ' ', $slug));
        if (preg_match('/<h1[^>]*class=["\'][^"\']*entry-title[^"\']*["\'][^>]*>(.*?)<\/h1>/is', $html, $t)) {
            $title = html_entity_decode(trim(strip_tags($t[1])), ENT_QUOTES | ENT_HTML5);
        }

        // Cover
        $cover = '';
        if (preg_match('/<div[^>]*class=["\'][^"\']*thumb[^"\']*["\'][^>]*>[\s\S]*?<img[^>]+src=["\']([^"\']+)["\']/i', $html, $c)) {
            $cover = trim($c[1]);
        }

        // Synopsis
        $synopsis = 'Read this trending webtoon series online on ManhwaFlow with official chapters.';
        if (preg_match('/<div[^>]*itemprop=["\']description["\'][^>]*>(.*?)<\/div>/is', $html, $syn)) {
            $synopsis = html_entity_decode(trim(strip_tags($syn[1])), ENT_QUOTES | ENT_HTML5);
        } elseif (preg_match('/<div[^>]*class=["\'][^"\']*entry-content[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $syn)) {
            $synopsis = html_entity_decode(trim(strip_tags($syn[1])), ENT_QUOTES | ENT_HTML5);
        }

        // Genres
        $genres = [];
        if (preg_match_all('/<a[^>]+href=["\'](?:https:\/\/athreascans\.com)?\/genres\/[^"\']+\/["\'][^>]*>(.*?)<\/a>/i', $html, $g)) {
            $rawGenres = array_values(array_unique(array_map('trim', $g[1])));
            foreach ($rawGenres as $rg) {
                if (stripos($rg, 'scans') === false && stripos($rg, 'athrea') === false) {
                    $genres[] = $rg;
                }
            }
        }
        if (empty($genres)) {
            $genres = ['Romance', 'Drama', 'Webtoon'];
        }

        // Status
        $status = 'Ongoing';
        if (preg_match('/Status<\/b>[\s\S]*?<i>(.*?)<\/i>/i', $html, $st)) {
            $status = trim(strip_tags($st[1]));
        }

        // Author
        $author = 'Webtoon Studio';
        if (preg_match('/Author<\/b>[\s\S]*?<span>(.*?)<\/span>/i', $html, $au)) {
            $candidateAuthor = html_entity_decode(trim(strip_tags($au[1])), ENT_QUOTES | ENT_HTML5);
            if (!empty($candidateAuthor) && stripos($candidateAuthor, 'athrea') === false && stripos($candidateAuthor, 'scans') === false) {
                $author = $candidateAuthor;
            }
        }

        $details = [
            'id' => "athrea_{$slug}",
            'slug' => $slug,
            'title' => $title,
            'cover_url' => $cover,
            'synopsis' => $synopsis,
            'genres' => $genres,
            'status' => $status,
            'author' => $author,
            'type' => 'Manhwa',
            'rating' => number_format(4.8 + (abs(crc32($slug)) % 20) / 100, 1)
        ];

        if (!empty($cover)) {
            @file_put_contents($cacheFile, json_encode($details));
        }

        return $details;
    }

    /**
     * Get detailed metadata for an Asura series
     */
    public static function getAsuraDetails($slug) {
        $cacheFile = self::CACHE_DIR . "/asura_details_{$slug}.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 43200)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached)) return $cached;
        }

        $url = self::ASURA_BASE . "/comics/{$slug}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: null;
            return null;
        }

        // Title
        $title = ucwords(str_replace('-', ' ', $slug));
        if (preg_match('/<title>(.*?)<\/title>/i', $html, $tm)) {
            $rawTitle = preg_replace('/\|.*$/', '', $tm[1]);
            $rawTitle = preg_replace('/-.*$/', '', $rawTitle);
            $title = html_entity_decode(trim($rawTitle), ENT_QUOTES | ENT_HTML5);
        } elseif (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $hm)) {
            $title = html_entity_decode(trim(strip_tags($hm[1])), ENT_QUOTES | ENT_HTML5);
        }

        // Cover
        $cover = '';
        if (preg_match('/https:\/\/cdn\.asurascans\.com\/asura-images\/covers\/[^"\'\s&<>]+?\.(?:webp|jpg|png)/i', $html, $cm)) {
            $cover = trim($cm[0]);
        }

        // Synopsis
        $synopsis = 'Read this trending webtoon series online on ManhwaFlow with official chapters.';
        if (preg_match('/<span class="font-medium text-sm text-\[#A2A2A2\][^"]*">(.*?)<\/span>/is', $html, $sm)) {
            $synopsis = html_entity_decode(trim(strip_tags($sm[1])), ENT_QUOTES | ENT_HTML5);
        } elseif (preg_match('/<div[^>]*class="[^"]*text-sm[^"]*"[^>]*>(.*?)<\/div>/is', $html, $sm)) {
            $synopsis = html_entity_decode(trim(strip_tags($sm[1])), ENT_QUOTES | ENT_HTML5);
        }

        // Genres
        $genres = ['Action', 'Fantasy', 'Adventure'];
        if (preg_match_all('/<button[^>]*class="[^"]*rounded-md[^"]*"[^>]*>(.*?)<\/button>/is', $html, $gm)) {
            $extracted = array_values(array_filter(array_unique(array_map('trim', array_map('strip_tags', $gm[1])))));
            if (!empty($extracted)) {
                $filtered = [];
                foreach ($extracted as $e) {
                    if (stripos($e, 'scans') === false && stripos($e, 'asura') === false) {
                        $filtered[] = $e;
                    }
                }
                if (!empty($filtered)) {
                    $genres = array_slice($filtered, 0, 5);
                }
            }
        }

        $details = [
            'id' => "asura_{$slug}",
            'slug' => $slug,
            'title' => $title,
            'cover_url' => $cover,
            'synopsis' => $synopsis,
            'genres' => $genres,
            'status' => 'Ongoing',
            'author' => 'Webtoon Studio',
            'type' => 'Manhwa',
            'rating' => number_format(4.8 + (abs(crc32($slug)) % 20) / 100, 1)
        ];

        if (!empty($cover)) {
            @file_put_contents($cacheFile, json_encode($details));
        }

        return $details;
    }

    /**
     * Get detailed metadata for an Anisa series
     */
    public static function getAnisaDetails($slug) {
        $cacheFile = self::CACHE_DIR . "/anisa_details_{$slug}.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 43200)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached) && !empty($cached['cover_url']) && !str_contains($cached['cover_url'], 'anisascans.in')) {
                return $cached;
            }
        }

        $url = self::ANISA_BASE . "/manga/{$slug}/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            if (file_exists($cacheFile)) return json_decode(@file_get_contents($cacheFile), true) ?: null;
            return null;
        }

        // Title
        $title = ucwords(str_replace('-', ' ', $slug));
        if (preg_match('/<title>(.*?)<\/title>/i', $html, $tm)) {
            $rawTitle = preg_replace('/&#8211;.*$/i', '', $tm[1]);
            $rawTitle = preg_replace('/-.*$/i', '', $rawTitle);
            $rawTitle = preg_replace('/\|.*$/i', '', $rawTitle);
            $title = html_entity_decode(trim($rawTitle), ENT_QUOTES | ENT_HTML5);
        } elseif (preg_match('/<div class="post-title"[^>]*>[\s\S]*?<h1>(.*?)<\/h1>/i', $html, $tm)) {
            $title = html_entity_decode(trim(strip_tags($tm[1])), ENT_QUOTES | ENT_HTML5);
        }

        // Cover (Anisascans.in direct images are blocked with 403, resolve to verified CDN or MangaDex)
        $cover = '';
        if (preg_match('/<div class="summary_image"[^>]*>[\s\S]*?<img[^>]+src="([^">]+)"/i', $html, $cm)) {
            $cover = trim($cm[1]);
        }

        // Priority 1: Match against verified Exclusive Spotlight list
        $spotlight = self::getExclusiveSpotlight();
        foreach ($spotlight as $sp) {
            if ($sp['slug'] === $slug || $sp['id'] === "anisa_{$slug}" || strcasecmp($sp['title'], $title) === 0) {
                if (!empty($sp['cover_url']) && !str_contains($sp['cover_url'], 'anisascans.in')) {
                    $cover = $sp['cover_url'];
                    break;
                }
            }
        }

        // Priority 2: If still anisascans.in or empty, search MangaDex
        if (empty($cover) || str_contains($cover, 'anisascans.in')) {
            $cleanTitle = preg_replace('/[’\']/u', '', $title);
            $cleanTitle = trim(preg_replace('/\s+/', ' ', $cleanTitle));
            if (class_exists('MangaDexAPI')) {
                $mdMatch = MangaDexAPI::search($cleanTitle, 1);
                if (!empty($mdMatch[0]['cover_url'])) {
                    $cover = $mdMatch[0]['cover_url'];
                }
            }
        }

        if (empty($cover) || str_contains($cover, 'anisascans.in')) {
            $cover = (defined('BASE_URL') ? BASE_URL : '/') . 'assets/images/placeholder.svg';
        }

        // Synopsis
        $synopsis = 'Read this trending webtoon series online on ManhwaFlow with official chapters.';
        if (preg_match('/<div class="description-summary"[^>]*>[\s\S]*?<div class="summary__content[^"]*"[^>]*>(.*?)<\/div>/is', $html, $sm)) {
            $synopsis = html_entity_decode(trim(strip_tags($sm[1])), ENT_QUOTES | ENT_HTML5);
        }

        // Genres
        $genres = [];
        if (preg_match_all('/<div class="genres-content"[^>]*>[\s\S]*?<\/div>/is', $html, $gm)) {
            if (preg_match_all('/<a[^>]+>(.*?)<\/a>/i', $gm[0][0], $ga)) {
                $rawGenres = array_values(array_filter(array_unique(array_map('trim', array_map('strip_tags', $ga[1])))));
                foreach ($rawGenres as $rg) {
                    if (stripos($rg, 'scans') === false && stripos($rg, 'anisa') === false) {
                        $genres[] = $rg;
                    }
                }
            }
        }
        if (empty($genres)) {
            $genres = ['Action', 'Fantasy', 'Shounen'];
        }

        $details = [
            'id' => "anisa_{$slug}",
            'slug' => $slug,
            'title' => $title,
            'cover_url' => $cover,
            'synopsis' => $synopsis,
            'genres' => $genres,
            'status' => 'Ongoing',
            'author' => 'Webtoon Studio',
            'type' => 'Manhwa',
            'rating' => number_format(4.8 + (abs(crc32($slug)) % 20) / 100, 1)
        ];

        if (!empty($cover)) {
            @file_put_contents($cacheFile, json_encode($details));
        }

        return $details;
    }

    /**
     * Unified Exclusive Spotlight: Top trending webtoons from multiple sources
     * Neutral presentation with no 3rd-party source mentions.
     */
    public static function getExclusiveSpotlight($limit = 12) {
        $cacheFile = self::CACHE_DIR . "/exclusive_spotlight_v2.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (!empty($cached) && count($cached) >= 6) {
                return array_slice($cached, 0, $limit);
            }
        }

        $curatedList = [
            [
                'id' => 'asura_nano-machine',
                'slug' => 'nano-machine',
                'title' => 'Nano Machine',
                'cover_url' => 'https://cdn.asurascans.com/asura-images/covers/nano-machine.e31bdb.webp',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.9',
                'tags' => ['Action', 'Murim', 'System'],
                'latest_chapter' => 'Ch. 250+'
            ],
            [
                'id' => 'asura_return-of-the-mount-hua-sect',
                'slug' => 'return-of-the-mount-hua-sect',
                'title' => 'Return of the Mount Hua Sect',
                'cover_url' => 'https://cdn.asurascans.com/asura-images/covers/return-of-the-mount-hua-sect.c0cbf9.webp',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.9',
                'tags' => ['Action', 'Martial Arts', 'Reincarnation'],
                'latest_chapter' => 'Ch. 140+'
            ],
            [
                'id' => 'asura_the-greatest-estate-developer',
                'slug' => 'the-greatest-estate-developer',
                'title' => 'The Greatest Estate Developer',
                'cover_url' => 'https://cdn.asurascans.com/asura-images/covers/the-greatest-estate-developer.ad682d.webp',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.9',
                'tags' => ['Comedy', 'Fantasy', 'System'],
                'latest_chapter' => 'Ch. 180+'
            ],
            [
                'id' => 'asura_revenge-of-the-iron-blooded-sword-hound',
                'slug' => 'revenge-of-the-iron-blooded-sword-hound',
                'title' => 'Revenge of the Iron-Blooded Sword Hound',
                'cover_url' => 'https://cdn.asurascans.com/asura-images/covers/revenge-of-the-iron-blooded-sword-hound.41b6fb.webp',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.8',
                'tags' => ['Action', 'Reincarnation', 'Fantasy'],
                'latest_chapter' => 'Ch. 110+'
            ],
            [
                'id' => 'asura_standard-of-reincarnation',
                'slug' => 'standard-of-reincarnation',
                'title' => 'Standard of Reincarnation',
                'cover_url' => 'https://cdn.asurascans.com/asura-images/covers/standard-of-reincarnation.32ce34.webp',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.8',
                'tags' => ['Action', 'Fantasy', 'Reincarnation'],
                'latest_chapter' => 'Ch. 125+'
            ],
            [
                'id' => 'asura_solo-max-level-newbie',
                'slug' => 'solo-max-level-newbie',
                'title' => 'Solo Max-Level Newbie',
                'cover_url' => 'https://cdn.asurascans.com/asura-images/covers/solo-max-level-newbie.bac83f.webp',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.8',
                'tags' => ['Action', 'Tower', 'System'],
                'latest_chapter' => 'Ch. 180+'
            ],
            [
                'id' => 'asura_surviving-the-game-as-a-barbarian',
                'slug' => 'surviving-the-game-as-a-barbarian',
                'title' => 'Surviving the Game as a Barbarian',
                'cover_url' => 'https://cdn.asurascans.com/asura-images/covers/surviving-the-game-as-a-barbarian.86af24.webp',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.8',
                'tags' => ['Action', 'Survival', 'Fantasy'],
                'latest_chapter' => 'Ch. 90+'
            ],
            [
                'id' => 'asura_pick-me-up-infinite-gacha',
                'slug' => 'pick-me-up-infinite-gacha',
                'title' => 'Pick Me Up, Infinite Gacha',
                'cover_url' => 'https://cdn.asurascans.com/asura-images/covers/pick-me-up-infinite-gacha.3ebe61.webp',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.8',
                'tags' => ['Action', 'System', 'Game'],
                'latest_chapter' => 'Ch. 120+'
            ],
            [
                'id' => 'anisa_overgeared',
                'slug' => 'overgeared',
                'title' => 'Overgeared',
                'cover_url' => 'https://temp.compsci88.com/cover/fallback/01J76XYDMR2777KEM5BKTBBK83.jpg',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.9',
                'tags' => ['Action', 'VRMMO', 'Fantasy'],
                'latest_chapter' => 'Ch. 220+'
            ],
            [
                'id' => 'anisa_genius-archers-streaming',
                'slug' => 'genius-archers-streaming',
                'title' => "Genius Archer's Streaming",
                'cover_url' => 'https://temp.compsci88.com/cover/fallback/01JXAC7MDTWPNM304YD9033CWJ.jpg',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.8',
                'tags' => ['Action', 'Hunter', 'Streaming'],
                'latest_chapter' => 'Ch. 60+'
            ],
            [
                'id' => 'athrea_trembling-as-i-escape-from-you',
                'slug' => 'trembling-as-i-escape-from-you',
                'title' => 'Trembling as I Escape From You',
                'cover_url' => 'https://athreascans.com/wp-content/uploads/2026/07/mc38749-cover-225x300.webp',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.8',
                'tags' => ['Romance', 'Drama', 'Fantasy'],
                'latest_chapter' => 'Ch. 35+'
            ],
            [
                'id' => 'athrea_romance-saga-succubus-story',
                'slug' => 'romance-saga-succubus-story',
                'title' => 'ROMANCE SAGA Succubus Story',
                'cover_url' => 'https://athreascans.com/wp-content/uploads/2026/05/tall-1-225x300.jpg',
                'status' => 'Ongoing',
                'type' => 'Manhwa',
                'rating' => '4.7',
                'tags' => ['Romance', 'Fantasy', 'Shoujo'],
                'latest_chapter' => 'Ch. 25+'
            ]
        ];

        // Also merge dynamic titles from Athrea directory if needed
        $athreaLive = self::getAthreaDirectory(10);
        foreach ($athreaLive as $al) {
            $exists = false;
            foreach ($curatedList as $cl) {
                if ($cl['id'] === $al['id']) { $exists = true; break; }
            }
            if (!$exists) {
                $curatedList[] = $al;
            }
        }

        @file_put_contents($cacheFile, json_encode($curatedList));
        return array_slice($curatedList, 0, $limit);
    }

    /**
     * Get Chapter Pages for WeebCentral, Asura Scans, Anisa Scans, or Athrea Scans
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

        // --- 4. Athrea Scans Chapter Images ---
        if (str_starts_with($chapterId, 'athrea_')) {
            if (preg_match('/^athrea_(.+)_ch_(.+)$/', $chapterId, $matches)) {
                return self::getAthreaPages($matches[1], $matches[2]);
            }
            return [];
        }

        return [];
    }

    /**
     * Universal Cover Resolver and Self-Healer
     * Heals broken covers, anisascans 403 hotlinking issues, and fallback placeholders.
     */
    public static function resolveCover($seriesId, $title = '', $currentCover = '') {
        $trimmedCover = trim($currentCover);
        $isBroken = empty($trimmedCover) 
            || str_contains($trimmedCover, 'anisascans.in') 
            || str_contains($trimmedCover, 'placeholder.svg') 
            || str_contains($trimmedCover, 'placehold.co')
            || !filter_var($trimmedCover, FILTER_VALIDATE_URL);

        if (!$isBroken) {
            return $trimmedCover;
        }

        $seriesId = trim($seriesId);
        $title = trim($title);
        $slug = preg_replace('/^(anisa_|asura_|athrea_)/', '', $seriesId);

        // 1. Direct verified CDN mappings for known series
        $knownDirect = [
            'genius-archers-streaming' => 'https://temp.compsci88.com/cover/fallback/01JXAC7MDTWPNM304YD9033CWJ.jpg',
            'anisa_genius-archers-streaming' => 'https://temp.compsci88.com/cover/fallback/01JXAC7MDTWPNM304YD9033CWJ.jpg',
            'overgeared' => 'https://temp.compsci88.com/cover/fallback/01J76XYDMR2777KEM5BKTBBK83.jpg',
            'anisa_overgeared' => 'https://temp.compsci88.com/cover/fallback/01J76XYDMR2777KEM5BKTBBK83.jpg',
            'nano-machine' => 'https://cdn.asurascans.com/asura-images/covers/nano-machine.e31bdb.webp',
            'asura_nano-machine' => 'https://cdn.asurascans.com/asura-images/covers/nano-machine.e31bdb.webp',
            'return-of-the-mount-hua-sect' => 'https://cdn.asurascans.com/asura-images/covers/return-of-the-mount-hua-sect.c0cbf9.webp',
            'asura_return-of-the-mount-hua-sect' => 'https://cdn.asurascans.com/asura-images/covers/return-of-the-mount-hua-sect.c0cbf9.webp',
            'the-greatest-estate-developer' => 'https://cdn.asurascans.com/asura-images/covers/the-greatest-estate-developer.ad682d.webp',
            'asura_the-greatest-estate-developer' => 'https://cdn.asurascans.com/asura-images/covers/the-greatest-estate-developer.ad682d.webp',
            'revenge-of-the-iron-blooded-sword-hound' => 'https://cdn.asurascans.com/asura-images/covers/revenge-of-the-iron-blooded-sword-hound.41b6fb.webp',
            'asura_revenge-of-the-iron-blooded-sword-hound' => 'https://cdn.asurascans.com/asura-images/covers/revenge-of-the-iron-blooded-sword-hound.41b6fb.webp',
            'standard-of-reincarnation' => 'https://cdn.asurascans.com/asura-images/covers/standard-of-reincarnation.32ce34.webp',
            'asura_standard-of-reincarnation' => 'https://cdn.asurascans.com/asura-images/covers/standard-of-reincarnation.32ce34.webp',
            'solo-max-level-newbie' => 'https://cdn.asurascans.com/asura-images/covers/solo-max-level-newbie.bac83f.webp',
            'asura_solo-max-level-newbie' => 'https://cdn.asurascans.com/asura-images/covers/solo-max-level-newbie.bac83f.webp',
            'surviving-the-game-as-a-barbarian' => 'https://cdn.asurascans.com/asura-images/covers/surviving-the-game-as-a-barbarian.86af24.webp',
            'asura_surviving-the-game-as-a-barbarian' => 'https://cdn.asurascans.com/asura-images/covers/surviving-the-game-as-a-barbarian.86af24.webp',
            'pick-me-up-infinite-gacha' => 'https://cdn.asurascans.com/asura-images/covers/pick-me-up-infinite-gacha.3ebe61.webp',
            'asura_pick-me-up-infinite-gacha' => 'https://cdn.asurascans.com/asura-images/covers/pick-me-up-infinite-gacha.3ebe61.webp',
            'trembling-as-i-escape-from-you' => 'https://athreascans.com/wp-content/uploads/2026/07/mc38749-cover-225x300.webp',
            'athrea_trembling-as-i-escape-from-you' => 'https://athreascans.com/wp-content/uploads/2026/07/mc38749-cover-225x300.webp',
            'romance-saga-succubus-story' => 'https://athreascans.com/wp-content/uploads/2026/05/tall-1-225x300.jpg',
            'athrea_romance-saga-succubus-story' => 'https://athreascans.com/wp-content/uploads/2026/05/tall-1-225x300.jpg'
        ];

        if (isset($knownDirect[$seriesId])) {
            return $knownDirect[$seriesId];
        }
        if (isset($knownDirect[$slug])) {
            return $knownDirect[$slug];
        }
        if (!empty($title)) {
            $normTitle = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($title)));
            $normTitle = trim($normTitle, '-');
            if (isset($knownDirect[$normTitle])) {
                return $knownDirect[$normTitle];
            }
        }

        // 2. Check spotlight
        $spotlight = self::getExclusiveSpotlight(50);
        foreach ($spotlight as $sp) {
            $spSlug = $sp['slug'] ?? '';
            $spId = $sp['id'] ?? '';
            $spTitle = $sp['title'] ?? '';

            if (
                (!empty($spId) && $spId === $seriesId) ||
                (!empty($spSlug) && ($spSlug === $slug || $spSlug === $seriesId)) ||
                (!empty($title) && !empty($spTitle) && (strcasecmp($spTitle, $title) === 0 || self::isTitleMatch($spTitle, $title)))
            ) {
                if (!empty($sp['cover_url']) && !str_contains($sp['cover_url'], 'anisascans.in')) {
                    return $sp['cover_url'];
                }
            }
        }

        // 3. MangaDex UUID lookup
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $seriesId)) {
            if (class_exists('MangaDexAPI')) {
                $details = MangaDexAPI::getMangaDetailsLive($seriesId);
                if (!empty($details['cover_url'])) {
                    return $details['cover_url'];
                }
            }
        }

        // 4. Athrea lookup
        if (str_starts_with($seriesId, 'athrea_')) {
            $athreaData = self::getAthreaDetails($slug);
            if (!empty($athreaData['cover_url']) && !str_contains($athreaData['cover_url'], 'anisascans.in')) {
                return $athreaData['cover_url'];
            }
        }

        // 5. Asura lookup
        if (str_starts_with($seriesId, 'asura_')) {
            $asuraData = self::getAsuraDetails($slug);
            if (!empty($asuraData['cover_url']) && !str_contains($asuraData['cover_url'], 'anisascans.in')) {
                return $asuraData['cover_url'];
            }
        }

        // 6. Search MangaDex by title
        if (!empty($title) && class_exists('MangaDexAPI')) {
            $cleanSearchTitle = preg_replace('/[’\']/u', '', $title);
            $cleanSearchTitle = trim(preg_replace('/\s+/', ' ', $cleanSearchTitle));
            if (!empty($cleanSearchTitle)) {
                $mdMatch = MangaDexAPI::search($cleanSearchTitle, 1);
                if (!empty($mdMatch[0]['cover_url'])) {
                    return $mdMatch[0]['cover_url'];
                }
            }
        }

        return (defined('BASE_URL') ? BASE_URL : '/') . 'assets/images/placeholder.svg';
    }
}

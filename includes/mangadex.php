<?php
// includes/mangadex.php - MangaDex API v5 Client with Live Feed & Smart Caching

class MangaDexAPI {
    const API_BASE = 'https://api.mangadex.org';
    const COVER_BASE = 'https://uploads.mangadex.org/covers';
    const USER_AGENT = 'ManhwaFlow-Live/1.0 (XAMPP Local Development; +https://github.com/manhwaflow)';

    /**
     * Send GET request with custom User-Agent and Smart File Caching
     */
    public static function request($endpoint, $params = [], $cacheTtl = 300) {
        $cacheDir = __DIR__ . '/../cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $cacheKey = md5($endpoint . serialize($params));
        $cacheFile = $cacheDir . '/' . $cacheKey . '.json';

        // Check cache if TTL > 0
        if ($cacheTtl > 0 && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
            $cachedContent = @file_get_contents($cacheFile);
            if ($cachedContent) {
                $decoded = json_decode($cachedContent, true);
                if ($decoded) return $decoded;
            }
        }

        $url = self::API_BASE . $endpoint;
        if (!empty($params)) {
            $queryString = http_build_query($params);
            // Replace numeric indices for array params like originalLanguage%5B0%5D -> originalLanguage[]
            $queryString = preg_replace('/%5B\d+%5D/', '%5B%5D', $queryString);
            $url .= '?' . $queryString;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP compatibility

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            // Return stale cache if available upon network error
            if (file_exists($cacheFile)) {
                return json_decode(@file_get_contents($cacheFile), true);
            }
            return null;
        }

        $decoded = json_decode($response, true);
        if ($decoded && $cacheTtl > 0) {
            @file_put_contents($cacheFile, $response);
        }

        return $decoded;
    }

    /**
     * Parse Manga Object from MangaDex Response
     */
    public static function parseMangaItem($item) {
        $attr = $item['attributes'] ?? [];
        $mangaId = $item['id'];

        // Title preference: English -> English in AltTitles -> Korean Romanized -> First available
        $title = $attr['title']['en'] ?? '';
        if (empty($title) && !empty($attr['altTitles'])) {
            foreach ($attr['altTitles'] as $alt) {
                if (!empty($alt['en'])) {
                    $title = $alt['en'];
                    break;
                }
            }
        }
        if (empty($title)) {
            $title = $attr['title']['ko-ro'] ?? reset($attr['title']) ?? 'Untitled';
        }

        // Alt Title
        $altTitle = '';
        if (!empty($attr['altTitles'])) {
            foreach ($attr['altTitles'] as $alt) {
                if (isset($alt['ko'])) {
                    $altTitle = $alt['ko'];
                    break;
                } elseif (isset($alt['ko-ro']) && $alt['ko-ro'] !== $title) {
                    $altTitle = $alt['ko-ro'];
                    break;
                } elseif (isset($alt['en']) && $alt['en'] !== $title) {
                    $altTitle = $alt['en'];
                }
            }
        }

        // Strictly English Description - Never fall back to other languages
        $desc = $attr['description']['en'] ?? '';
        if (empty($desc) && !empty($attr['description']['en-us'])) {
            $desc = $attr['description']['en-us'];
        }
        if (empty($desc)) {
            $desc = "Follow this popular webtoon series with weekly English-translated updates and continuous vertical strip panels on ManhwaFlow.";
        }
        $desc = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $desc);

        // Relationships (Cover Art & Author)
        $coverFileName = '';
        $authorName = 'Unknown';
        if (!empty($item['relationships'])) {
            foreach ($item['relationships'] as $rel) {
                if ($rel['type'] === 'cover_art' && !empty($rel['attributes']['fileName'])) {
                    $coverFileName = $rel['attributes']['fileName'];
                }
                if ($rel['type'] === 'author' && !empty($rel['attributes']['name'])) {
                    $authorName = $rel['attributes']['name'];
                }
            }
        }

        $coverUrl = !empty($coverFileName) 
            ? self::COVER_BASE . "/{$mangaId}/{$coverFileName}.512.jpg" 
            : 'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=600&auto=format&fit=crop&q=80';

        // Tags
        $tags = [];
        if (!empty($attr['tags'])) {
            foreach ($attr['tags'] as $tag) {
                if (!empty($tag['attributes']['name']['en'])) {
                    $tags[] = $tag['attributes']['name']['en'];
                }
            }
        }

        // Realistic distinct rating (4.4 to 4.9) computed per series ID
        $ratingSeed = abs(crc32($mangaId));
        $rating = number_format(4.4 + (($ratingSeed % 6) * 0.1), 1);
        $views = 18000 + ($ratingSeed % 78000);

        return [
            'id' => $mangaId,
            'title' => $title,
            'alt_title' => $altTitle,
            'author' => $authorName,
            'status' => ucfirst($attr['status'] ?? 'ongoing'),
            'year' => $attr['year'] ?? date('Y'),
            'rating' => $rating,
            'views' => $views,
            'description' => $desc,
            'cover_url' => $coverUrl,
            'tags' => $tags,
            'latest_chapter_id' => $attr['latestUploadedChapter'] ?? null,
            'updated_at' => $attr['updatedAt'] ?? null
        ];
    }

    /**
     * Get Real-time Latest Uploaded Series with English Translations (Paged) - Strictly Korean Manhwa
     */
    public static function getLatestLiveUpdatesPaged($limit = 30, $page = 1) {
        $offset = max(0, ($page - 1) * $limit);
        $params = [
            'availableTranslatedLanguage' => ['en'],
            'originalLanguage' => ['ko'],
            'order' => ['latestUploadedChapter' => 'desc'],
            'limit' => $limit,
            'offset' => $offset,
            'includes' => ['cover_art', 'author'],
            'contentRating' => ['safe', 'suggestive']
        ];

        $res = self::request('/manga', $params, 180); // 3-minute cache
        if (!$res || empty($res['data'])) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'total_pages' => 1];
        }

        $list = [];
        foreach ($res['data'] as $item) {
            $list[] = self::parseMangaItem($item);
        }

        $total = intval($res['total'] ?? count($list));
        return [
            'items' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => max(1, ceil($total / $limit))
        ];
    }

    public static function getLatestLiveUpdates($limit = 30, $page = 1) {
        $res = self::getLatestLiveUpdatesPaged($limit, $page);
        return $res['items'];
    }

    /**
     * Get Popular Series on MangaDex Live with English Translations - Strictly Korean Manhwa
     */
    public static function getPopularLive($limit = 12) {
        $params = [
            'availableTranslatedLanguage' => ['en'],
            'originalLanguage' => ['ko'],
            'order' => ['followedCount' => 'desc'],
            'limit' => $limit,
            'includes' => ['cover_art', 'author'],
            'contentRating' => ['safe', 'suggestive']
        ];

        $res = self::request('/manga', $params, 600); // 10-minute cache
        if (!$res || empty($res['data'])) return [];

        $list = [];
        foreach ($res['data'] as $item) {
            $list[] = self::parseMangaItem($item);
        }
        return $list;
    }

    /**
     * MangaDex Official Genre & Theme Tag UUID Mappings
     */
    public static $tagMap = [
        'action' => '391b0423-d847-456f-aff0-8b0cfc03066b',
        'adventure' => '87cc87cd-a395-47af-b27a-93258283bbc6',
        'fantasy' => 'cdc58593-87dd-415e-bbc0-2ec27bf404cc',
        'martial-arts' => '799c202e-7daa-44eb-9cf7-8a3c0441531e',
        'comedy' => '4d32cc48-9f00-4cca-9b5a-a839f0764984',
        'supernatural' => 'eabc5b4c-6aff-42f3-b657-3e90cbd00b75',
        'drama' => 'b9af3a63-f058-46de-a9a0-e0c13906197a',
        'romance' => '423e2eae-a7a2-4a8b-ac03-a8351462d71d',
        'isekai' => 'ace04997-f6bd-436e-b261-779182193d3d',
        'school-life' => 'caaa44eb-cd40-4177-b930-79d3ef2afe87',
        'magic' => 'a1f53773-c69a-4ce5-8cab-fffcd90b1565',
        'mystery' => 'ee968100-4191-4968-93d3-f82d72be7e46',
        'slice-of-life' => 'e5301a23-ebd9-49dd-a0cb-2add944c7fe9',
        'sci-fi' => '256c8bd9-4904-4360-bf4f-508a76d67183',
        'psychological' => '3b60b75c-a2d7-4860-ab56-05f391bb889c',
        'historical' => '33771934-028e-4cb3-8744-691e866a923e',
        'horror' => 'cdad7e68-1419-41dd-bdce-27753074a640',
        'thriller' => '07251805-a27e-4d59-b488-f0bfbec15168',
        'reincarnation' => '0bc90acb-ccc1-44ca-a34a-b9f3a73259d0',
        'villainess' => 'd14322ac-4d6f-4e9b-afd9-629d5f4d8a41',
        'sports' => '69964a64-2f90-4d33-beeb-f3ed2875eb4c',
        'superhero' => '7064a261-a137-4d3a-8848-2d385de3a99c',
        'survival' => '5fff9cde-849c-4d78-aab0-0d52b2ee1d25',
        'crime' => '5ca48985-9a9d-4bd8-be29-80dc0303db72',
        'demons' => '39730448-9a5f-48a2-85b0-a70db87b1233',
        'monsters' => '36fd93ea-e8b8-445e-b836-358f02b3d33d',
        'time-travel' => '292e862b-2d17-4062-90a2-0356caa4ae27',
        'tragedy' => 'f8f62932-27da-4fe4-8ee1-6779a8c5edba',
        'military' => 'ac72833b-c4e9-4878-b9db-6c8a4a99444a',
        'wuxia' => 'acc803a4-c95a-4c22-86fc-eb6b582d82a2',
        'vampires' => 'd7d1730f-6eb0-4ba6-9437-602cac38664c'
    ];

    /**
     * Get Series Filtered by Genre / Tag (Paged across full MangaDex library) - Strictly Korean Manhwa
     */
    public static function getByGenrePaged($genreSlug, $limit = 30, $page = 1) {
        $slugClean = strtolower(trim($genreSlug));
        $tagId = self::$tagMap[$slugClean] ?? null;
        $offset = max(0, ($page - 1) * $limit);

        $params = [
            'availableTranslatedLanguage' => ['en'],
            'originalLanguage' => ['ko'],
            'limit' => $limit,
            'offset' => $offset,
            'includes' => ['cover_art', 'author'],
            'order' => ['followedCount' => 'desc'],
            'contentRating' => ['safe', 'suggestive']
        ];

        if ($tagId) {
            $params['includedTags'] = [$tagId];
        } else {
            return self::searchPaged(str_replace('-', ' ', $genreSlug), $limit, $page, true);
        }

        $res = self::request('/manga', $params, 600); // 10-minute cache per genre page
        if (!$res || empty($res['data'])) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'total_pages' => 1];
        }

        $list = [];
        foreach ($res['data'] as $item) {
            $list[] = self::parseMangaItem($item);
        }

        $total = intval($res['total'] ?? count($list));
        return [
            'items' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => max(1, ceil($total / $limit))
        ];
    }

    public static function getByGenre($genreSlug, $limit = 30, $page = 1) {
        $res = self::getByGenrePaged($genreSlug, $limit, $page);
        return $res['items'];
    }

    /**
     * Live Search MangaDex with English Translations (Paged) - Strictly Korean Manhwa
     */
    public static function searchPaged($query, $limit = 30, $page = 1, $onlyKorean = true) {
        $offset = max(0, ($page - 1) * $limit);
        $params = [
            'title' => $query,
            'availableTranslatedLanguage' => ['en'],
            'originalLanguage' => ['ko'],
            'limit' => $limit,
            'offset' => $offset,
            'includes' => ['cover_art', 'author'],
            'order' => ['relevance' => 'desc'],
            'contentRating' => ['safe', 'suggestive']
        ];

        $res = self::request('/manga', $params, 300);
        if (!$res || empty($res['data'])) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'total_pages' => 1];
        }

        $list = [];
        foreach ($res['data'] as $item) {
            $list[] = self::parseMangaItem($item);
        }

        $total = intval($res['total'] ?? count($list));
        return [
            'items' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => max(1, ceil($total / $limit))
        ];
    }

    public static function search($query, $limit = 30, $page = 1, $onlyKorean = true) {
        $res = self::searchPaged($query, $limit, $page, $onlyKorean);
        return $res['items'];
    }

    /**
     * Get Single Manga Details Live
     */
    public static function getMangaDetailsLive($mangaId) {
        $res = self::request("/manga/{$mangaId}", [
            'includes' => ['cover_art', 'author']
        ], 600);

        if (!$res || empty($res['data'])) return null;
        return self::parseMangaItem($res['data']);
    }

    /**
     * Get Chapters for a Manga Live - Strictly English with Complete Gapless Fallback
     */
    public static function getChaptersLive($mangaId, $limit = 1000, $mangaTitle = '') {
        require_once __DIR__ . '/chapters_fallback.php';

        // Check if pre-mapped or cached dynamic fallback mapping exists
        $existingMapping = ChaptersFallback::getMapping($mangaId);
        if ($existingMapping) {
            $fb = ChaptersFallback::getChapters($mangaId, $limit, $mangaTitle);
            if (!empty($fb)) return $fb;
        }

        // Fetch from MangaDex with limit=500
        $params = [
            'limit' => 500,
            'order' => ['chapter' => 'asc'],
            'contentRating' => ['safe', 'suggestive'],
            'translatedLanguage' => ['en']
        ];

        $res = self::request("/manga/{$mangaId}/feed", $params, 300);
        $totalFeed = intval($res['total'] ?? 0);
        $feedData = $res['data'] ?? [];

        // If MangaDex reports > 500 chapters, fetch offset 500 to cover up to 1000 chapters
        if ($totalFeed > 500) {
            $params['offset'] = 500;
            $res2 = self::request("/manga/{$mangaId}/feed", $params, 300);
            if (!empty($res2['data'])) {
                $feedData = array_merge($feedData, $res2['data']);
            }
        }

        $chapters = [];
        $seen = [];
        $hostedCount = 0;
        $maxChapterNum = 0;

        foreach ($feedData as $ch) {
            $attr = $ch['attributes'] ?? [];
            $chNum = $attr['chapter'] ?? null;
            if ($chNum === null) continue;

            $chNumFloat = floatval($chNum);
            $hasPages = intval($attr['pages'] ?? 0) > 0;
            $isExternal = !empty($attr['externalUrl']);

            // Deduplicate: replace external/empty chapter with a hosted one if found
            if (isset($seen[$chNumFloat])) {
                if ($hasPages && !$isExternal) {
                    foreach ($chapters as $k => $c) {
                        if ($c['chapter_number'] == $chNumFloat && (!empty($c['externalUrl']) || $c['pages'] == 0)) {
                            $chapters[$k] = [
                                'id' => $ch['id'],
                                'chapter_number' => $chNumFloat,
                                'title' => $attr['title'] ?? ('Chapter ' . $chNum),
                                'pages' => intval($attr['pages'] ?? 0),
                                'language' => $attr['translatedLanguage'] ?? 'en',
                                'externalUrl' => null,
                                'created_at' => $attr['publishAt'] ?? $attr['createdAt'] ?? date('Y-m-d')
                            ];
                            $hostedCount++;
                            break;
                        }
                    }
                }
                continue;
            }

            $seen[$chNumFloat] = true;
            if ($chNumFloat > $maxChapterNum) {
                $maxChapterNum = $chNumFloat;
            }

            if ($hasPages && !$isExternal) {
                $hostedCount++;
            }

            $chapters[] = [
                'id' => $ch['id'],
                'chapter_number' => $chNumFloat,
                'title' => $attr['title'] ?? ('Chapter ' . $chNum),
                'pages' => intval($attr['pages'] ?? 0),
                'language' => $attr['translatedLanguage'] ?? 'en',
                'externalUrl' => $attr['externalUrl'] ?? null,
                'created_at' => $attr['publishAt'] ?? $attr['createdAt'] ?? date('Y-m-d')
            ];
        }

        // When to check secondary provider:
        // 1. MangaDex has 0 hosted chapters
        // 2. MangaDex hosted count is very low (< 15)
        // 3. MangaDex has missing gaps: highest chapter is >= 25, but hosted count is less than 60% of that number!
        $hasMissingGap = ($maxChapterNum >= 25 && $hostedCount < ($maxChapterNum * 0.6));
        if ($hostedCount < 15 || $hasMissingGap) {
            $fallbackList = ChaptersFallback::getChapters($mangaId, $limit, $mangaTitle);
            if (!empty($fallbackList) && count($fallbackList) > $hostedCount) {
                return $fallbackList;
            }
        }

        usort($chapters, function($a, $b) {
            return $a['chapter_number'] <=> $b['chapter_number'];
        });

        return array_slice($chapters, 0, $limit);
    }

    /**
     * Get Chapter Pages Live via @Home CDN or Secondary Fallback Provider
     */
    public static function getChapterPagesLive($chapterId, $dataSaver = true) {
        if (str_starts_with($chapterId, 'asura_') || str_starts_with($chapterId, 'wc_')) {
            require_once __DIR__ . '/chapters_fallback.php';
            return ChaptersFallback::getPages($chapterId);
        }

        $res = self::request("/at-home/server/{$chapterId}", ['forcePort443' => 'true'], 300);
        if (!$res || empty($res['baseUrl']) || empty($res['chapter']['hash'])) {
            // Immediate fresh retry bypassing cache if previous node expired
            $res = self::request("/at-home/server/{$chapterId}", ['forcePort443' => 'true'], 0);
        }
        if (!$res || empty($res['baseUrl']) || empty($res['chapter']['hash'])) {
            return [];
        }

        $baseUrl = $res['baseUrl'];
        $hash = $res['chapter']['hash'];
        $qualityMode = $dataSaver ? 'data-saver' : 'data';
        $files = $dataSaver ? ($res['chapter']['dataSaver'] ?? []) : ($res['chapter']['data'] ?? []);

        if (empty($files) && $dataSaver) {
            $qualityMode = 'data';
            $files = $res['chapter']['data'] ?? [];
        }

        $pages = [];
        foreach ($files as $file) {
            $pages[] = "{$baseUrl}/{$qualityMode}/{$hash}/{$file}";
        }

        return $pages;
    }

    /**
     * Import a Manga and its Chapters into MySQL (Optional Persistent Copy)
     */
    public static function importToDatabase($pdo, $mangaData, $maxChapters = 3) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $mangaData['title']), '-'));
        $check = $pdo->prepare("SELECT id FROM manhwas WHERE slug = ?");
        $check->execute([$slug]);
        $existing = $check->fetch();

        if ($existing) {
            $manhwaId = $existing['id'];
            $update = $pdo->prepare("
                UPDATE manhwas 
                SET title = ?, alt_title = ?, author = ?, synopsis = ?, cover_image = ?, banner_image = ?, status = ?
                WHERE id = ?
            ");
            $update->execute([
                $mangaData['title'], $mangaData['alt_title'], $mangaData['author'],
                $mangaData['description'], $mangaData['cover_url'], $mangaData['cover_url'],
                $mangaData['status'], $manhwaId
            ]);
        } else {
            $insert = $pdo->prepare("
                INSERT INTO manhwas 
                (title, slug, alt_title, author, artist, type, status, rating, synopsis, cover_image, banner_image, views)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $mangaData['title'], $slug, $mangaData['alt_title'], $mangaData['author'],
                $mangaData['author'], 'Manhwa', $mangaData['status'], 4.9,
                $mangaData['description'], $mangaData['cover_url'], $mangaData['cover_url'], rand(5000, 25000)
            ]);
            $manhwaId = $pdo->lastInsertId();
        }

        // Link Tags
        if (!empty($mangaData['tags'])) {
            $stmtGenreFind = $pdo->prepare("SELECT id FROM genres WHERE slug = ?");
            $stmtGenreInsert = $pdo->prepare("INSERT INTO genres (name, slug) VALUES (?, ?)");
            $stmtLink = $pdo->prepare("INSERT IGNORE INTO manhwa_genres (manhwa_id, genre_id) VALUES (?, ?)");

            foreach ($mangaData['tags'] as $tagName) {
                $gSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $tagName), '-'));
                $stmtGenreFind->execute([$gSlug]);
                $gRow = $stmtGenreFind->fetch();
                $gId = $gRow ? $gRow['id'] : null;

                if (!$gId) {
                    try {
                        $stmtGenreInsert->execute([$tagName, $gSlug]);
                        $gId = $pdo->lastInsertId();
                    } catch (Exception $e) {
                        $stmtGenreFind->execute([$gSlug]);
                        $gRow = $stmtGenreFind->fetch();
                        $gId = $gRow ? $gRow['id'] : null;
                    }
                }

                if ($gId) {
                    $stmtLink->execute([$manhwaId, $gId]);
                }
            }
        }

        // Import Chapters
        $importedChaptersCount = 0;
        if ($maxChapters > 0) {
            $availableChapters = self::getChaptersLive($mangaData['id'], 30);
            $chaptersToImport = array_slice($availableChapters, 0, $maxChapters);

            $stmtCheckCh = $pdo->prepare("SELECT id FROM chapters WHERE manhwa_id = ? AND chapter_number = ?");
            $stmtInsertCh = $pdo->prepare("INSERT INTO chapters (manhwa_id, chapter_number, title, views) VALUES (?, ?, ?, ?)");
            $stmtInsertPage = $pdo->prepare("INSERT INTO chapter_pages (chapter_id, page_number, image_url) VALUES (?, ?, ?)");

            foreach ($chaptersToImport as $ch) {
                $stmtCheckCh->execute([$manhwaId, $ch['chapter_number']]);
                $existingCh = $stmtCheckCh->fetch();

                if ($existingCh) {
                    $chapterId = $existingCh['id'];
                } else {
                    $stmtInsertCh->execute([$manhwaId, $ch['chapter_number'], $ch['title'], rand(500, 3000)]);
                    $chapterId = $pdo->lastInsertId();
                }

                $checkPages = $pdo->prepare("SELECT COUNT(*) FROM chapter_pages WHERE chapter_id = ?");
                $checkPages->execute([$chapterId]);
                if ($checkPages->fetchColumn() == 0) {
                    $pages = self::getChapterPagesLive($ch['id'], true);

                    if (empty($pages)) {
                        for ($p = 1; $p <= 5; $p++) {
                            $panelUrl = BASE_URL . "panel.php?title=" . urlencode($mangaData['title']) . "&ch=" . $ch['chapter_number'] . "&page=" . $p;
                            $stmtInsertPage->execute([$chapterId, $p, $panelUrl]);
                        }
                    } else {
                        foreach ($pages as $pIndex => $pageUrl) {
                            $stmtInsertPage->execute([$chapterId, $pIndex + 1, $pageUrl]);
                        }
                    }
                }
                $importedChaptersCount++;
            }
        }

        return [
            'manhwa_id' => $manhwaId,
            'slug' => $slug,
            'chapters_imported' => $importedChaptersCount
        ];
    }
}

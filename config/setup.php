<?php
// config/setup.php

function initializeDatabase($pdo) {
    // 1. Create tables if not exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `genres` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(60) NOT NULL UNIQUE,
            `slug` VARCHAR(60) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `manhwas` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `alt_title` VARCHAR(255) NULL,
            `author` VARCHAR(100) DEFAULT 'Unknown',
            `artist` VARCHAR(100) DEFAULT 'Unknown',
            `status` ENUM('Ongoing', 'Completed', 'Hiatus') DEFAULT 'Ongoing',
            `type` ENUM('Manhwa', 'Manga', 'Manhua') DEFAULT 'Manhwa',
            `synopsis` TEXT NULL,
            `cover_image` VARCHAR(500) NULL,
            `banner_image` VARCHAR(500) NULL,
            `rating` DECIMAL(3, 1) DEFAULT 4.9,
            `views` INT DEFAULT 12500,
            `is_featured` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `manhwa_genres` (
            `manhwa_id` INT NOT NULL,
            `genre_id` INT NOT NULL,
            PRIMARY KEY (`manhwa_id`, `genre_id`),
            FOREIGN KEY (`manhwa_id`) REFERENCES `manhwas`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`genre_id`) REFERENCES `genres`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `chapters` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `manhwa_id` INT NOT NULL,
            `chapter_number` DECIMAL(6, 1) NOT NULL,
            `title` VARCHAR(255) NULL,
            `views` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`manhwa_id`) REFERENCES `manhwas`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `chapter_pages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `chapter_id` INT NOT NULL,
            `page_number` INT NOT NULL,
            `image_url` TEXT NOT NULL,
            FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `bookmarks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_token` VARCHAR(100) NOT NULL,
            `manhwa_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (`user_token`, `manhwa_id`),
            FOREIGN KEY (`manhwa_id`) REFERENCES `manhwas`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `avatar` VARCHAR(255) DEFAULT 'default.png',
            `role` ENUM('user', 'admin') DEFAULT 'user',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `user_bookmarks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `series_id` VARCHAR(100) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `cover_image` TEXT NULL,
            `status` VARCHAR(50) DEFAULT 'Ongoing',
            `rating` DECIMAL(3, 1) DEFAULT 4.8,
            `last_known_chapter` VARCHAR(50) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_series` (`user_id`, `series_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `chapter_comments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `chapter_id` VARCHAR(100) NOT NULL,
            `series_id` VARCHAR(100) NOT NULL,
            `user_id` INT NULL,
            `author_name` VARCHAR(100) NOT NULL,
            `avatar` VARCHAR(255) DEFAULT 'default.png',
            `comment` TEXT NOT NULL,
            `likes` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `user_notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `series_id` VARCHAR(100) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `message` VARCHAR(500) NOT NULL,
            `read_url` VARCHAR(500) NOT NULL,
            `cover_image` TEXT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `reading_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NULL,
            `user_token` VARCHAR(100) NOT NULL,
            `series_id` VARCHAR(100) NOT NULL,
            `series_title` VARCHAR(255) NOT NULL,
            `cover_image` TEXT NULL,
            `chapter_id` VARCHAR(100) NOT NULL,
            `chapter_number` DECIMAL(6, 1) NOT NULL,
            `chapter_title` VARCHAR(255) NULL,
            `read_url` VARCHAR(500) NOT NULL,
            `scroll_percent` INT DEFAULT 0,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_user_series` (`user_token`, `series_id`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_series_id` (`series_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `user_rewards` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL UNIQUE,
            `coins` INT DEFAULT 0,
            `total_earned` INT DEFAULT 0,
            `streak_days` INT DEFAULT 0,
            `last_checkin_date` DATE NULL,
            `last_sponsor_date` DATE NULL,
            `chapters_read_count` INT DEFAULT 0,
            `default_payout_method` VARCHAR(50) DEFAULT 'gcash',
            `default_account_name` VARCHAR(100) NULL,
            `default_account_number` VARCHAR(100) NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `user_chapter_rewards` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `chapter_id` VARCHAR(100) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_user_chapter` (`user_id`, `chapter_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `payout_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `amount_php` INT NOT NULL,
            `coins_deducted` INT NOT NULL,
            `payout_method` VARCHAR(50) NOT NULL DEFAULT 'gcash',
            `account_name` VARCHAR(100) NOT NULL,
            `account_number` VARCHAR(100) NOT NULL,
            `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            `admin_note` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `reward_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `action_type` VARCHAR(50) NOT NULL,
            `coins` INT NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_action` (`user_id`, `action_type`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Ensure default payout columns exist in user_rewards
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `user_rewards` LIKE 'default_account_number'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `user_rewards` 
                ADD COLUMN `default_payout_method` VARCHAR(50) DEFAULT 'gcash',
                ADD COLUMN `default_account_name` VARCHAR(100) NULL,
                ADD COLUMN `default_account_number` VARCHAR(100) NULL");
        }
    } catch(Exception $e) {}

    // Seed default admin if no users exist
    $checkUsers = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($checkUsers == 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmtUser = $pdo->prepare("INSERT INTO `users` (`username`, `email`, `password`, `role`) VALUES (?, ?, ?, ?)");
        $stmtUser->execute(['admin', 'admin@manhwaflow.local', $adminPass, 'admin']);
    }

    // Check if genres table has data
    $checkGenres = $pdo->query("SELECT COUNT(*) FROM `genres`")->fetchColumn();
    if ($checkGenres == 0) {
        $genres = [
            ['Action', 'action'],
            ['Adventure', 'adventure'],
            ['Fantasy', 'fantasy'],
            ['System / Dungeon', 'system-dungeon'],
            ['Regression / Reincarnation', 'regression-reincarnation'],
            ['Murim / Martial Arts', 'murim-martial-arts'],
            ['Comedy', 'comedy'],
            ['Romance', 'romance'],
            ['Drama', 'drama'],
            ['School Life', 'school-life'],
            ['Supernatural', 'supernatural'],
            ['Isekai', 'isekai']
        ];
        $stmt = $pdo->prepare("INSERT INTO `genres` (`name`, `slug`) VALUES (?, ?)");
        foreach ($genres as $g) {
            $stmt->execute($g);
        }
    }

    // Check if manhwas table has data
    $checkManhwas = $pdo->query("SELECT COUNT(*) FROM `manhwas`")->fetchColumn();
    if ($checkManhwas == 0) {
        seedSampleData($pdo);
    }
}

function seedSampleData($pdo) {
    $sampleManhwas = [
        [
            'title' => 'Solo Leveling',
            'slug' => 'solo-leveling',
            'alt_title' => 'Only I Level Up (나 혼자만 레벨업)',
            'author' => 'Chugong',
            'artist' => 'DUBU (REDICE Studio)',
            'status' => 'Completed',
            'type' => 'Manhwa',
            'rating' => 4.9,
            'views' => 248000,
            'is_featured' => 1,
            'cover_image' => 'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=600&auto=format&fit=crop&q=80',
            'banner_image' => 'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?w=1600&auto=format&fit=crop&q=80',
            'synopsis' => '10 years ago, after "the Gate" opened and connected the real world with the realm of magic and monsters, ordinary people were granted superhuman powers. Sung Jin-woo, known as the "Weakest Hunter of All Mankind", finds himself trapped in a deadly double dungeon and wakes up with a mysterious Quest Log only he can see.',
            'genres' => ['action', 'fantasy', 'system-dungeon', 'supernatural']
        ],
        [
            'title' => 'Omniscient Reader’s Viewpoint',
            'slug' => 'omniscient-readers-viewpoint',
            'alt_title' => 'Jeonjijeok Dokja Sijeom (전지적 독자 시점)',
            'author' => 'sing N song',
            'artist' => 'Sleepy-C (REDICE Studio)',
            'status' => 'Ongoing',
            'type' => 'Manhwa',
            'rating' => 4.9,
            'views' => 195000,
            'is_featured' => 1,
            'cover_image' => 'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?w=600&auto=format&fit=crop&q=80',
            'banner_image' => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=1600&auto=format&fit=crop&q=80',
            'synopsis' => 'Kim Dokja is an ordinary office worker whose only hobby is reading his favorite web novel: "Three Ways to Survive the Apocalypse." When the novel suddenly finishes with him being the sole reader, the real world turns into the exact apocalyptic world of the novel, and only Dokja knows how it ends.',
            'genres' => ['action', 'fantasy', 'regression-reincarnation', 'supernatural']
        ],
        [
            'title' => 'The Greatest Estate Developer',
            'slug' => 'the-greatest-estate-developer',
            'alt_title' => 'Yeokdaegeup Yeongji Seolgyesa (역대급 영지 설계사)',
            'author' => 'BK_Moon',
            'artist' => 'Kim Hyunsoo',
            'status' => 'Ongoing',
            'type' => 'Manhwa',
            'rating' => 4.9,
            'views' => 172000,
            'is_featured' => 1,
            'cover_image' => 'https://images.unsplash.com/photo-1563089145-599997674d42?w=600&auto=format&fit=crop&q=80',
            'banner_image' => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=1600&auto=format&fit=crop&q=80',
            'synopsis' => 'Civil engineering student Suho Kim falls asleep reading a fantasy novel—and wakes up in the body of Lloyd Frontera, a lazy noble whose family is drowning in massive debt. Using his real-world engineering knowledge and hilarious cunning faces, Lloyd starts inventing heated floors, bridges, and aqueducts to save his estate!',
            'genres' => ['comedy', 'fantasy', 'isekai', 'regression-reincarnation']
        ],
        [
            'title' => 'Return of the Blossoming Blade',
            'slug' => 'return-of-the-blossoming-blade',
            'alt_title' => 'Return of the Mount Hua Sect (화산귀환)',
            'author' => 'Biga',
            'artist' => 'LICO',
            'status' => 'Ongoing',
            'type' => 'Manhwa',
            'rating' => 4.8,
            'views' => 143000,
            'is_featured' => 0,
            'cover_image' => 'https://images.unsplash.com/photo-1534447677768-be436bb09401?w=600&auto=format&fit=crop&q=80',
            'banner_image' => 'https://images.unsplash.com/photo-1508739773434-c26b3d09e071?w=1600&auto=format&fit=crop&q=80',
            'synopsis' => 'Chung Myung, the 13th disciple of the Mount Hua Sect and the Plum Blossom Sword Saint, defeated the Heavenly Demon but died atop Mount 100,000. Reincarnated 100 years later as a child, he finds his once-glorious Mount Hua Sect fallen into ruins and poverty. He vows to revive it by beating everyone into shape!',
            'genres' => ['action', 'murim-martial-arts', 'comedy', 'regression-reincarnation']
        ],
        [
            'title' => 'The Beginning After the End',
            'slug' => 'the-beginning-after-the-end',
            'alt_title' => 'TBATE',
            'author' => 'TurtleMe',
            'artist' => 'Fuyuki23',
            'status' => 'Ongoing',
            'type' => 'Manhwa',
            'rating' => 4.8,
            'views' => 210000,
            'is_featured' => 0,
            'cover_image' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=600&auto=format&fit=crop&q=80',
            'banner_image' => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=1600&auto=format&fit=crop&q=80',
            'synopsis' => 'King Grey possessed unrivaled strength, wealth, and prestige in a world governed by martial ability. However, solitude lingers closely behind those with great power. Reborn into a new world filled with magic and monsters, the king has a second chance to relive his life corrected from his past mistakes.',
            'genres' => ['action', 'adventure', 'fantasy', 'isekai']
        ],
        [
            'title' => 'Wind Breaker',
            'slug' => 'wind-breaker',
            'alt_title' => '윈드브레이커',
            'author' => 'Jo Yongseok',
            'artist' => 'Jo Yongseok',
            'status' => 'Ongoing',
            'type' => 'Manhwa',
            'rating' => 4.7,
            'views' => 118000,
            'is_featured' => 0,
            'cover_image' => 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?w=600&auto=format&fit=crop&q=80',
            'banner_image' => 'https://images.unsplash.com/photo-1508739773434-c26b3d09e071?w=1600&auto=format&fit=crop&q=80',
            'synopsis' => 'Jay is the student body president of Taeyang High, pressured by his parents to focus only on studies. But on his bike, he is free. When a biking crew spots his extraordinary street racing skills, Jay gets pulled into the adrenaline-pumping world of street cycling and the Hummingbird crew.',
            'genres' => ['action', 'drama', 'school-life', 'comedy']
        ]
    ];

    $stmtInsertManhwa = $pdo->prepare("
        INSERT INTO `manhwas` 
        (`title`, `slug`, `alt_title`, `author`, `artist`, `status`, `type`, `rating`, `views`, `is_featured`, `cover_image`, `banner_image`, `synopsis`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $genreMap = [];
    $allGenres = $pdo->query("SELECT id, slug FROM genres")->fetchAll();
    foreach ($allGenres as $g) {
        $genreMap[$g['slug']] = $g['id'];
    }

    $stmtGenreLink = $pdo->prepare("INSERT INTO `manhwa_genres` (`manhwa_id`, `genre_id`) VALUES (?, ?)");
    $stmtChapter = $pdo->prepare("INSERT INTO `chapters` (`manhwa_id`, `chapter_number`, `title`, `views`) VALUES (?, ?, ?, ?)");
    $stmtPage = $pdo->prepare("INSERT INTO `chapter_pages` (`chapter_id`, `page_number`, `image_url`) VALUES (?, ?, ?)");

    foreach ($sampleManhwas as $m) {
        $stmtInsertManhwa->execute([
            $m['title'], $m['slug'], $m['alt_title'], $m['author'], $m['artist'],
            $m['status'], $m['type'], $m['rating'], $m['views'], $m['is_featured'],
            $m['cover_image'], $m['banner_image'], $m['synopsis']
        ]);
        $manhwaId = $pdo->lastInsertId();

        // Link genres
        foreach ($m['genres'] as $gSlug) {
            if (isset($genreMap[$gSlug])) {
                $stmtGenreLink->execute([$manhwaId, $genreMap[$gSlug]]);
            }
        }

        // Add 3 Chapters for each manhwa
        for ($chNum = 1; $chNum <= 3; $chNum++) {
            $chTitle = ($chNum === 1) ? 'Prologue / Awakening' : 'Chapter ' . $chNum;
            $chViews = rand(1500, 8000);
            $stmtChapter->execute([$manhwaId, $chNum, $chTitle, $chViews]);
            $chapterId = $pdo->lastInsertId();

            // Add 4-5 high quality visual webtoon strip panels
            for ($p = 1; $p <= 5; $p++) {
                // Dynamically created SVG webtoon strip panel URL
                $panelUrl = BASE_URL . "panel.php?title=" . urlencode($m['title']) . "&ch=" . $chNum . "&page=" . $p;
                $stmtPage->execute([$chapterId, $p, $panelUrl]);
            }
        }
    }
}


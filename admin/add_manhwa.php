<?php
// admin/add_manhwa.php - Add New Manhwa Title
require_once __DIR__ . '/auth_check.php';
$pdo = getPdo();

$error = '';
$success = '';

// Fetch all available genres
$genres = $pdo->query("SELECT * FROM `genres` ORDER BY `name` ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $title = trim($_POST['title'] ?? '');
    $alt_title = trim($_POST['alt_title'] ?? '');
    $author = trim($_POST['author'] ?? 'Unknown');
    $artist = trim($_POST['artist'] ?? 'Unknown');
    $type = $_POST['type'] ?? 'Manhwa';
    $status = $_POST['status'] ?? 'Ongoing';
    $rating = floatval($_POST['rating'] ?? 4.8);
    $synopsis = trim($_POST['synopsis'] ?? '');
    $selectedGenres = $_POST['genres'] ?? [];

    if (empty($title)) {
        $error = 'Kailangan ang pamagat (Title) ng Manhwa.';
    } else {
        // Generate slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        // Check uniqueness of slug
        $check = $pdo->prepare("SELECT id FROM manhwas WHERE slug = ?");
        $check->execute([$slug]);
        if ($check->fetch()) {
            $slug .= '-' . rand(100, 999);
        }

        // Handle Cover Image (Upload or URL)
        $cover_image = trim($_POST['cover_url'] ?? '');
        if (!empty($_FILES['cover_file']['name'])) {
            $ext = strtolower(pathinfo($_FILES['cover_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $coverFilename = 'cover_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $targetPath = ROOT_PATH . 'uploads/covers/' . $coverFilename;
                if (move_uploaded_file($_FILES['cover_file']['tmp_name'], $targetPath)) {
                    $cover_image = BASE_URL . 'uploads/covers/' . $coverFilename;
                }
            }
        }
        if (empty($cover_image)) {
            $cover_image = 'https://images.unsplash.com/photo-1578632767115-351597cf2477?w=600&auto=format&fit=crop&q=80';
        }

        // Handle Banner Image (Upload or URL)
        $banner_image = trim($_POST['banner_url'] ?? '');
        if (!empty($_FILES['banner_file']['name'])) {
            $bExt = strtolower(pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION));
            if (in_array($bExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $bannerFilename = 'banner_' . time() . '_' . rand(100, 999) . '.' . $bExt;
                $bTargetPath = ROOT_PATH . 'uploads/covers/' . $bannerFilename;
                if (move_uploaded_file($_FILES['banner_file']['tmp_name'], $bTargetPath)) {
                    $banner_image = BASE_URL . 'uploads/covers/' . $bannerFilename;
                }
            }
        }
        if (empty($banner_image)) {
            $banner_image = $cover_image;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO `manhwas` 
                (`title`, `slug`, `alt_title`, `author`, `artist`, `type`, `status`, `rating`, `synopsis`, `cover_image`, `banner_image`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $slug, $alt_title, $author, $artist, $type, $status, $rating, $synopsis, $cover_image, $banner_image
            ]);
            $newManhwaId = $pdo->lastInsertId();

            // Link genres
            if (!empty($selectedGenres)) {
                $genreStmt = $pdo->prepare("INSERT INTO `manhwa_genres` (`manhwa_id`, `genre_id`) VALUES (?, ?)");
                foreach ($selectedGenres as $gId) {
                    $genreStmt->execute([$newManhwaId, intval($gId)]);
                }
            }

            header("Location: " . BASE_URL . "admin/add_chapter.php?manhwa_id=" . $newManhwaId . "&msg=created");
            exit;
        } catch (PDOException $e) {
            $error = 'Error sa database: ' . $e->getMessage();
        }
    }
}

$page_title = 'Magdagdag ng Bagong Manhwa - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-dark-800">
        <div>
            <a href="<?= BASE_URL ?>admin/index.php" class="text-xs text-brand-400 hover:text-brand-300 font-semibold mb-1 block">
                <i class="fa-solid fa-arrow-left mr-1"></i> Bumalik sa Admin Dashboard
            </a>
            <h1 class="text-2xl font-black text-white">Magdagdag ng Bagong Manhwa</h1>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="bg-dark-900 border border-dark-800 rounded-2xl p-6 sm:p-8 space-y-6">
        <?= csrfField() ?>
        
        <!-- Row 1: Title & Alt Title -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Pamagat ng Serye (Title) <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="title" required placeholder="Hal. Solo Leveling, Nano Machine..."
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Alternative / Korean Title
                </label>
                <input type="text" name="alt_title" placeholder="Hal. Only I Level Up (나 혼자만 레벨업)"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>
        </div>

        <!-- Row 2: Author, Artist, Type, Status -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">May-akda (Author)</label>
                <input type="text" name="author" placeholder="Author name"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">Guhit (Artist)</label>
                <input type="text" name="artist" placeholder="Artist name"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">Uri (Type)</label>
                <select name="type" class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-brand-500">
                    <option value="Manhwa">Manhwa (Korean)</option>
                    <option value="Manhua">Manhua (Chinese)</option>
                    <option value="Manga">Manga (Japanese)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">Katayuan (Status)</label>
                <select name="status" class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-brand-500">
                    <option value="Ongoing">Ongoing</option>
                    <option value="Completed">Completed</option>
                    <option value="Hiatus">Hiatus</option>
                </select>
            </div>
        </div>

        <!-- Genres Selection -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">
                Pumili ng mga Kategorya (Genres)
            </label>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 bg-dark-850 p-4 rounded-xl border border-dark-700">
                <?php foreach ($genres as $g): ?>
                    <label class="flex items-center gap-2 text-xs text-slate-300 hover:text-white cursor-pointer">
                        <input type="checkbox" name="genres[]" value="<?= $g['id'] ?>" class="rounded bg-dark-800 border-dark-600 text-brand-600 focus:ring-0">
                        <span><?= htmlspecialchars($g['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Row 3: Cover & Banner Image (Upload or URL) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 bg-dark-850/50 p-4 rounded-xl border border-dark-750">
            <!-- Cover -->
            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                    Cover Poster Image
                </label>
                <input type="text" name="cover_url" placeholder="Paste Cover Image URL..."
                       class="w-full bg-dark-850 border border-dark-700 rounded-lg px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 mb-1">
                <p class="text-[11px] text-slate-400">O mag-upload mula sa iyong computer:</p>
                <input type="file" name="cover_file" accept="image/*"
                       class="text-xs text-slate-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:bg-dark-700 file:text-slate-200 hover:file:bg-brand-600">
            </div>

            <!-- Banner -->
            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                    Banner Header Background
                </label>
                <input type="text" name="banner_url" placeholder="Paste Banner Image URL..."
                       class="w-full bg-dark-850 border border-dark-700 rounded-lg px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 mb-1">
                <p class="text-[11px] text-slate-400">O mag-upload ng banner image:</p>
                <input type="file" name="banner_file" accept="image/*"
                       class="text-xs text-slate-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:bg-dark-700 file:text-slate-200 hover:file:bg-brand-600">
            </div>
        </div>

        <!-- Synopsis -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                Maikling Buod / Kuwento (Synopsis)
            </label>
            <textarea name="synopsis" rows="4" placeholder="Ikuwento ang plot o panimula ng manhwa..."
                      class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500"></textarea>
        </div>

        <div class="pt-2 flex justify-end gap-3">
            <a href="<?= BASE_URL ?>admin/index.php" class="px-5 py-2.5 rounded-xl bg-dark-800 hover:bg-dark-700 text-slate-300 text-xs font-semibold">
                Kanselahin
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-lg shadow-brand-600/30 flex items-center gap-2">
                <i class="fa-solid fa-check"></i> I-save at Mag-upload ng Chapters
            </button>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


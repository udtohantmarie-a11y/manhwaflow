<?php
// admin/add_chapter.php - Upload New Chapter and Panels
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

$error = '';
$success = '';

$selectedManhwaId = isset($_GET['manhwa_id']) ? intval($_GET['manhwa_id']) : 0;

// Fetch all manhwas for selection
$manhwas = $pdo->query("SELECT id, title, (SELECT MAX(chapter_number) FROM chapters WHERE manhwa_id = manhwas.id) as max_ch FROM manhwas ORDER BY title ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manhwaId = intval($_POST['manhwa_id'] ?? 0);
    $chapterNum = floatval($_POST['chapter_number'] ?? 1);
    $chapterTitle = trim($_POST['chapter_title'] ?? '');
    $autoGenerate = isset($_POST['auto_generate_panels']);
    $pageUrlsRaw = trim($_POST['page_urls'] ?? '');

    if ($manhwaId <= 0) {
        $error = 'Mangyaring pumili ng Manhwa.';
    } elseif ($chapterNum <= 0) {
        $error = 'Wastong numero ng kabanata ang kailangan.';
    } else {
        // Fetch manhwa title
        $mStmt = $pdo->prepare("SELECT title FROM manhwas WHERE id = ?");
        $mStmt->execute([$manhwaId]);
        $targetManhwa = $mStmt->fetch();

        if (!$targetManhwa) {
            $error = 'Hindi natagpuan ang napiling manhwa.';
        } else {
            try {
                // Insert Chapter
                $insertCh = $pdo->prepare("INSERT INTO `chapters` (`manhwa_id`, `chapter_number`, `title`, `views`) VALUES (?, ?, ?, ?)");
                $insertCh->execute([$manhwaId, $chapterNum, $chapterTitle, rand(100, 500)]);
                $chapterId = $pdo->lastInsertId();

                $pageIndex = 1;
                $stmtPage = $pdo->prepare("INSERT INTO `chapter_pages` (`chapter_id`, `page_number`, `image_url`) VALUES (?, ?, ?)");

                // 1. Process Uploaded Files
                if (!empty($_FILES['pages']['name'][0])) {
                    $uploadDir = ROOT_PATH . "uploads/chapters/{$manhwaId}/ch_{$chapterNum}/";
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $totalFiles = count($_FILES['pages']['name']);
                    for ($i = 0; $i < $totalFiles; $i++) {
                        if ($_FILES['pages']['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpName = $_FILES['pages']['tmp_name'][$i];
                            $originalName = $_FILES['pages']['name'][$i];
                            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                            
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                                $newFilename = sprintf("%03d_%s", $pageIndex, preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName));
                                $dest = $uploadDir . $newFilename;
                                if (move_uploaded_file($tmpName, $dest)) {
                                    $publicUrl = BASE_URL . "uploads/chapters/{$manhwaId}/ch_{$chapterNum}/" . $newFilename;
                                    $stmtPage->execute([$chapterId, $pageIndex, $publicUrl]);
                                    $pageIndex++;
                                }
                            }
                        }
                    }
                }

                // 2. Process Image URLs (one per line)
                if (!empty($pageUrlsRaw)) {
                    $urls = preg_split('/\r\n|\r|\n/', $pageUrlsRaw);
                    foreach ($urls as $u) {
                        $cleanUrl = trim($u);
                        if (!empty($cleanUrl) && filter_var($cleanUrl, FILTER_VALIDATE_URL)) {
                            $stmtPage->execute([$chapterId, $pageIndex, $cleanUrl]);
                            $pageIndex++;
                        }
                    }
                }

                // 3. Auto Generate Comic Panels if checked or if no images were provided
                if ($autoGenerate || $pageIndex === 1) {
                    for ($p = 1; $p <= 5; $p++) {
                        $panelUrl = BASE_URL . "panel.php?title=" . urlencode($targetManhwa['title']) . "&ch=" . $chapterNum . "&page=" . $p;
                        $stmtPage->execute([$chapterId, $pageIndex, $panelUrl]);
                        $pageIndex++;
                    }
                }

                // Update manhwa updated_at
                $pdo->prepare("UPDATE `manhwas` SET updated_at = NOW() WHERE id = ?")->execute([$manhwaId]);

                // Redirect to reader of new chapter
                header("Location: " . BASE_URL . "reader.php?chapter_id=" . $chapterId);
                exit;

            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Mag-upload ng Kabanata - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 py-8 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-dark-800">
        <div>
            <a href="<?= BASE_URL ?>admin/index.php" class="text-xs text-brand-400 hover:text-brand-300 font-semibold mb-1 block">
                <i class="fa-solid fa-arrow-left mr-1"></i> Bumalik sa Admin Dashboard
            </a>
            <h1 class="text-2xl font-black text-white">Mag-upload ng Bagong Kabanata (Chapter)</h1>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i> Matagumpay na nagawa ang Manhwa! Maaari ka nang mag-upload ng unang kabanata nito.
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="bg-dark-900 border border-dark-800 rounded-2xl p-6 sm:p-8 space-y-6">
        
        <!-- Select Manhwa -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                Pumili ng Manhwa Serye <span class="text-rose-500">*</span>
            </label>
            <select name="manhwa_id" id="manhwa-select" required 
                    class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand-500">
                <option value="">-- Piliin ang Manhwa --</option>
                <?php foreach ($manhwas as $m): ?>
                    <option value="<?= $m['id'] ?>" data-max="<?= $m['max_ch'] ?? 0 ?>" <?= ($selectedManhwaId == $m['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['title']) ?> (Kasalukuyang max: Ch. <?= $m['max_ch'] ?? 0 ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Row: Chapter Number & Title -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Numero ng Kabanata (Chapter #) <span class="text-rose-500">*</span>
                </label>
                <input type="number" step="0.1" name="chapter_number" id="chapter-number-input" required placeholder="Hal. 1 o 14.5"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Pamagat ng Kabanata (Opsyonal)
                </label>
                <input type="text" name="chapter_title" placeholder="Hal. Ang Paggising / The Awakening"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>
        </div>

        <!-- Chapter Pages / Images Options -->
        <div class="space-y-4 pt-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 border-b border-dark-800 pb-2 flex items-center gap-2">
                <i class="fa-solid fa-images text-brand-500"></i> Mga Pahina / Panels ng Kabanata
            </h3>

            <!-- Option A: File Upload -->
            <div class="bg-dark-850 p-4 rounded-xl border border-dark-700 space-y-2">
                <label class="block text-xs font-bold text-slate-200">
                    Opsyon 1: Mag-upload ng mga Imahe (Multiple Files)
                </label>
                <p class="text-[11px] text-slate-400">Pumili ng isa o higit pang images (JPG, PNG, WEBP) sa tamang pagkakasunod-sunod.</p>
                <input type="file" name="pages[]" multiple accept="image/*"
                       class="w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-600 file:text-white hover:file:bg-brand-500 cursor-pointer">
            </div>

            <!-- Option B: Direct Image URLs -->
            <div class="bg-dark-850 p-4 rounded-xl border border-dark-700 space-y-2">
                <label class="block text-xs font-bold text-slate-200">
                    Opsyon 2: Mag-paste ng Image URLs (Isang link bawat linya)
                </label>
                <textarea name="page_urls" rows="3" placeholder="https://domain.com/page-01.jpg&#10;https://domain.com/page-02.jpg"
                          class="w-full bg-dark-900 border border-dark-700 rounded-xl p-3 text-xs text-white placeholder-slate-600 focus:outline-none focus:border-brand-500 font-mono"></textarea>
            </div>

            <!-- Option C: Auto-generate Sample panels -->
            <div class="p-3 bg-brand-600/10 border border-brand-500/30 rounded-xl flex items-center gap-3">
                <input type="checkbox" name="auto_generate_panels" id="auto_gen" value="1" checked class="rounded bg-dark-800 border-dark-600 text-brand-600 focus:ring-0">
                <label for="auto_gen" class="text-xs text-slate-300 cursor-pointer">
                    <strong>Auto-generate Dynamic Webtoon Panels</strong> (Lilikha ng 5 stylized manhwa comic panels kung walang na-upload na file, para ma-test agad ang reader).
                </label>
            </div>
        </div>

        <div class="pt-2 flex justify-end gap-3">
            <a href="<?= BASE_URL ?>admin/index.php" class="px-5 py-2.5 rounded-xl bg-dark-800 hover:bg-dark-700 text-slate-300 text-xs font-semibold">
                Kanselahin
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-lg shadow-brand-600/30 flex items-center gap-2">
                <i class="fa-solid fa-cloud-arrow-up"></i> I-upload at Basahin Agad
            </button>
        </div>

    </form>
</div>

<script>
// Auto-suggest next chapter number when manhwa is selected
document.getElementById('manhwa-select')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const maxCh = parseFloat(selected.getAttribute('data-max') || 0);
    const input = document.getElementById('chapter-number-input');
    if (input && maxCh > 0) {
        input.value = (maxCh + 1).toFixed(0);
    } else if (input) {
        input.value = '1';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


<?php
// panel.php - Dynamic Webtoon comic panel generator for sample preview
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$title = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : 'Manhwa';
$ch = isset($_GET['ch']) ? intval($_GET['ch']) : 1;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Colors & Themes based on title
$colors = [
    'Solo Leveling' => ['bg1' => '#090d16', 'bg2' => '#0c1b33', 'accent' => '#00d2ff', 'glow' => '#3b82f6', 'theme' => 'system'],
    'Omniscient Reader’s Viewpoint' => ['bg1' => '#0a0a0f', 'bg2' => '#18122B', 'accent' => '#c084fc', 'glow' => '#9333ea', 'theme' => 'constellation'],
    'The Greatest Estate Developer' => ['bg1' => '#0f1710', 'bg2' => '#142817', 'accent' => '#4ade80', 'glow' => '#22c55e', 'theme' => 'lloyd'],
    'Return of the Blossoming Blade' => ['bg1' => '#180a0a', 'bg2' => '#2b1014', 'accent' => '#f43f5e', 'glow' => '#fb7185', 'theme' => 'sword'],
    'The Beginning After the End' => ['bg1' => '#0e1726', 'bg2' => '#1b2a4a', 'accent' => '#38bdf8', 'glow' => '#60a5fa', 'theme' => 'mana'],
    'Wind Breaker' => ['bg1' => '#18181b', 'bg2' => '#27272a', 'accent' => '#facc15', 'glow' => '#eab308', 'theme' => 'speed']
];

$theme = $colors[$title] ?? ['bg1' => '#09090b', 'bg2' => '#18181b', 'accent' => '#6366f1', 'glow' => '#818cf8', 'theme' => 'system'];

// Dialogue & Actions per page
$dialogues = [
    1 => [
        'sfx' => 'THUMP... THUMP...',
        'box' => '“Where... am I?”',
        'sub' => 'An ominous silence fell upon the dark chamber.',
        'tag' => 'SCENE 1: THE DISCOVERY'
    ],
    2 => [
        'sfx' => '⚡ CRAAAAASH! ⚡',
        'box' => '“Watch out from above!!”',
        'sub' => 'The ground beneath them shattered without warning.',
        'tag' => 'SCENE 2: THE AMBUSH'
    ],
    3 => [
        'sfx' => '[ SYSTEM NOTIFICATION ]',
        'box' => '“You have met the required conditions for Awakening.”',
        'sub' => 'Stat points have been distributed. Do you accept the Quest?',
        'tag' => 'SCENE 3: AWAKENING'
    ],
    4 => [
        'sfx' => '⚔️ SHRRRRK! ⚔️',
        'box' => '“I will not run away ever again.”',
        'sub' => 'Blue flames engulfed the blade as he stepped forward.',
        'tag' => 'SCENE 4: CLASH'
    ],
    5 => [
        'sfx' => '— TO BE CONTINUED —',
        'box' => '“From this moment, everything changes.”',
        'sub' => 'Next Chapter coming soon. Leave a bookmark to stay updated!',
        'tag' => 'SCENE 5: CLIFFHANGER'
    ]
];

$d = $dialogues[$page] ?? $dialogues[1];
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1100" width="800" height="1100">
  <defs>
    <linearGradient id="bgGrad" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" stop-color="<?= $theme['bg1'] ?>" />
      <stop offset="50%" stop-color="<?= $theme['bg2'] ?>" />
      <stop offset="100%" stop-color="<?= $theme['bg1'] ?>" />
    </linearGradient>
    <filter id="glowEffect" x="-20%" y="-20%" width="140%" height="140%">
      <feGaussianBlur stdDeviation="8" result="blur" />
      <feComposite in="SourceGraphic" in2="blur" operator="over" />
    </filter>
    <linearGradient id="accentGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="<?= $theme['accent'] ?>" />
      <stop offset="100%" stop-color="<?= $theme['glow'] ?>" />
    </linearGradient>
  </defs>

  <!-- Background Canvas -->
  <rect width="800" height="1100" fill="url(#bgGrad)" />

  <!-- Grid / Action speed lines -->
  <g opacity="0.08" stroke="#ffffff" stroke-width="1">
    <line x1="0" y1="200" x2="800" y2="450" />
    <line x1="0" y1="400" x2="800" y2="650" />
    <line x1="0" y1="600" x2="800" y2="850" />
    <line x1="800" y1="150" x2="0" y2="500" />
    <line x1="800" y1="550" x2="0" y2="900" />
  </g>

  <!-- Top Strip Header -->
  <g transform="translate(40, 50)">
    <rect width="720" height="40" rx="8" fill="#18181b" opacity="0.8" />
    <text x="20" y="25" fill="#a1a1aa" font-family="'Segoe UI', Roboto, sans-serif" font-size="14" font-weight="600">
      <?= strtoupper($title) ?> &bull; CH. <?= $ch ?> &bull; PAGE <?= $page ?>/5
    </text>
    <text x="700" y="25" text-anchor="end" fill="<?= $theme['accent'] ?>" font-family="'Segoe UI', Roboto, sans-serif" font-size="12" font-weight="700">
      WEBTOON STRIP
    </text>
  </g>

  <!-- Central Visual Illustration Graphic -->
  <g transform="translate(400, 480)">
    <!-- Aura Ring -->
    <circle r="180" fill="none" stroke="url(#accentGrad)" stroke-width="3" opacity="0.4" stroke-dasharray="10 8" />
    <circle r="140" fill="url(#accentGrad)" opacity="0.1" filter="url(#glowEffect)" />
    
    <!-- Cool Webtoon Graphic Silhouette -->
    <path d="M -70 80 L -30 -120 L 0 -150 L 30 -120 L 70 80 L 40 100 L 0 60 L -40 100 Z" fill="<?= $theme['accent'] ?>" opacity="0.85" filter="url(#glowEffect)" />
    <circle cx="0" cy="-60" r="18" fill="#ffffff" filter="url(#glowEffect)" />
    <line x1="-120" y1="40" x2="120" y2="-40" stroke="<?= $theme['accent'] ?>" stroke-width="4" filter="url(#glowEffect)" />
    <line x1="-80" y1="-80" x2="80" y2="80" stroke="#ffffff" stroke-width="2" opacity="0.8" />
  </g>

  <!-- SFX (Sound Effect) in Manhwa style -->
  <g transform="translate(400, 310)">
    <text x="0" y="0" text-anchor="middle" fill="<?= $theme['accent'] ?>" font-family="'Impact', 'Arial Black', sans-serif" font-size="52" letter-spacing="4" filter="url(#glowEffect)" transform="rotate(-4)">
      <?= htmlspecialchars($d['sfx']) ?>
    </text>
  </g>

  <!-- Speech Bubble / System Box -->
  <?php if ($page === 3): ?>
    <!-- Blue/Purple System Window Box -->
    <g transform="translate(100, 680)">
      <rect width="600" height="190" rx="12" fill="#030712" stroke="<?= $theme['accent'] ?>" stroke-width="3" filter="url(#glowEffect)" opacity="0.95" />
      <rect x="0" y="0" width="600" height="40" rx="12" fill="<?= $theme['accent'] ?>" opacity="0.25" />
      <text x="300" y="27" text-anchor="middle" fill="<?= $theme['accent'] ?>" font-family="'Segoe UI', Roboto, sans-serif" font-size="16" font-weight="800" letter-spacing="2">
        [ SYSTEM WINDOW ]
      </text>
      <text x="300" y="95" text-anchor="middle" fill="#ffffff" font-family="'Segoe UI', Roboto, sans-serif" font-size="20" font-weight="700">
        <?= htmlspecialchars($d['box']) ?>
      </text>
      <text x="300" y="140" text-anchor="middle" fill="#93c5fd" font-family="'Segoe UI', Roboto, sans-serif" font-size="15">
        <?= htmlspecialchars($d['sub']) ?>
      </text>
    </g>
  <?php else: ?>
    <!-- Webtoon Speech Bubble -->
    <g transform="translate(120, 710)">
      <rect width="560" height="160" rx="28" fill="#18181b" stroke="#3f3f46" stroke-width="2" opacity="0.9" />
      <path d="M 280 160 L 265 190 L 305 160 Z" fill="#18181b" stroke="#3f3f46" stroke-width="2" />
      <text x="280" y="65" text-anchor="middle" fill="#f4f4f5" font-family="'Segoe UI', Roboto, sans-serif" font-size="22" font-weight="700">
        <?= htmlspecialchars($d['box']) ?>
      </text>
      <text x="280" y="110" text-anchor="middle" fill="#a1a1aa" font-family="'Segoe UI', Roboto, sans-serif" font-size="16" font-style="italic">
        <?= htmlspecialchars($d['sub']) ?>
      </text>
    </g>
  <?php endif; ?>

  <!-- Bottom Panel Tag -->
  <g transform="translate(400, 1020)">
    <text x="0" y="0" text-anchor="middle" fill="#52525b" font-family="'Segoe UI', Roboto, sans-serif" font-size="13" font-weight="600" letter-spacing="3">
      MANHWA READER &bull; <?= htmlspecialchars($d['tag']) ?>
    </text>
  </g>
</svg>


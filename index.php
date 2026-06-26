<?php
// ──────────────────────────────────────────────────────────────
//  Laragon Dev Hub  –  Windows 11 Fluent Design
//  Place at: C:\laragon\www\index.php
// ──────────────────────────────────────────────────────────────

// ── FOLDER OPENER ENDPOINT ────────────────────────────────────
// Called via fetch() from JS — browsers can't open file:// URLs
// directly, so we ask PHP to call explorer.exe on the server.
if (isset($_GET['action']) && $_GET['action'] === 'open') {
    header('Content-Type: application/json');
    $req = isset($_GET['p']) ? urldecode($_GET['p']) : '';
    $safePath = realpath($req);
    $wwwRoot = realpath('.');

    if ($safePath && is_dir($safePath) && strpos($safePath, $wwwRoot) === 0) {
        // popen with start keeps it non-blocking on Windows
        @pclose(@popen('start explorer.exe "' . $safePath . '"', 'r'));
        echo json_encode(['ok' => true, 'path' => $safePath]);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Invalid path']);
    }
    exit;
}

// ── LANGUAGE DETECTION ────────────────────────────────────────
// Maps file extensions → [Label, hex colour]
$LANG_MAP = [
    // Frameworks / templates first (checked before plain extension)
    'blade' => ['Blade', '#ff2d20'],
    'vue' => ['Vue', '#42b883'],
    'jsx' => ['React', '#61dafb'],
    'tsx' => ['React/TS', '#61dafb'],
    'svelte' => ['Svelte', '#ff3e00'],
    // Primary languages
    'php' => ['PHP', '#777bb4'],
    'js' => ['JavaScript', '#f7df1e'],
    'mjs' => ['JavaScript', '#f7df1e'],
    'ts' => ['TypeScript', '#3178c6'],
    'py' => ['Python', '#3776ab'],
    'rb' => ['Ruby', '#cc342d'],
    'go' => ['Go', '#00add8'],
    'rs' => ['Rust', '#dea584'],
    'java' => ['Java', '#ed8b00'],
    'kt' => ['Kotlin', '#7f52ff'],
    'dart' => ['Dart', '#0175c2'],
    'cs' => ['C#', '#239120'],
    'cpp' => ['C++', '#00599c'],
    'c' => ['C', '#a8b9cc'],
    'swift' => ['Swift', '#fa7343'],
    'lua' => ['Lua', '#000080'],
    // Styling
    'css' => ['CSS', '#1572b6'],
    'scss' => ['SCSS', '#cc6699'],
    'sass' => ['Sass', '#cc6699'],
    'less' => ['Less', '#1d365d'],
    // Data / config
    'sql' => ['SQL', '#f29111'],
    'graphql' => ['GraphQL', '#e10098'],
    'sh' => ['Shell', '#89e051'],
    'bash' => ['Shell', '#89e051'],
    'html' => ['HTML', '#e34f26'],
    'htm' => ['HTML', '#e34f26'],
    'xml' => ['XML', '#0060ac'],
    'twig' => ['Twig', '#bacf29'],
    'hbs' => ['Handlebars', '#f0772b'],
    'pug' => ['Pug', '#a86454'],
    'erb' => ['ERB', '#cc342d'],
    'ex' => ['Elixir', '#4b275f'],
    'exs' => ['Elixir', '#4b275f'],
    'r' => ['R', '#276dc3'],
    'scala' => ['Scala', '#de3423'],
    'pl' => ['Perl', '#0298c3'],
    'coffee' => ['CoffeeScript', '#244776'],
    'astro' => ['Astro', '#ff5a03'],
    'prisma' => ['Prisma', '#2d3748'],
    'tf' => ['Terraform', '#7b42bc'],
    'sol' => ['Solidity', '#363636'],
    'hs' => ['Haskell', '#5e5086'],
];

// Directories to skip when scanning (saves time, avoids noise)
$SKIP_DIRS = [
    'vendor',
    'node_modules',
    '.git',
    '.svn',
    '.hg',
    'storage',
    'bootstrap',
    '.idea',
    '.vscode',
    'cache',
    'logs',
    '.next',
    'dist',
    'build',
    '__pycache__',
    '.sass-cache',
];

function detectLanguages(string $dir): array
{
    global $LANG_MAP, $SKIP_DIRS;

    $found = [];   // ext => true
    $scanned = 0;
    $maxFiles = 600;

    $scan = function (string $path, int $depth) use (&$scan, &$found, &$scanned, $maxFiles, $LANG_MAP, $SKIP_DIRS) {
        if ($depth > 4 || $scanned >= $maxFiles)
            return;
        $items = @scandir($path);
        if (!$items)
            return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..')
                continue;
            $full = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($full)) {
                if (!in_array(strtolower($item), $SKIP_DIRS, true)) {
                    $scan($full, $depth + 1);
                }
            } else {
                $scanned++;
                $lower = strtolower($item);

                // Special case: .blade.php before generic .php
                if (substr($lower, -9) === '.blade.php') {
                    $found['blade'] = true;
                    $found['php'] = true;
                    continue;
                }

                $ext = pathinfo($lower, PATHINFO_EXTENSION);
                if ($ext && isset($LANG_MAP[$ext])) {
                    $found[$ext] = true;
                }
            }
        }
    };

    $scan($dir, 0);

    // Build result ordered by $LANG_MAP key order (priority order)
    $result = [];
    foreach (array_keys($LANG_MAP) as $ext) {
        if (isset($found[$ext])) {
            $result[] = ['ext' => $ext] + ['label' => $LANG_MAP[$ext][0], 'color' => $LANG_MAP[$ext][1]];
        }
    }

    return $result;
}

// ── PROJECT SCANNING ──────────────────────────────────────────
$projects = [];
$dirs = array_filter(glob('*'), 'is_dir');

foreach ($dirs as $dir) {
    // --- Framework detection ---
    $isLaravel = file_exists($dir . '/artisan') ||
        (file_exists($dir . '/app') && file_exists($dir . '/routes'));
    $isWordPress = file_exists($dir . '/wp-config.php') ||
        file_exists($dir . '/wp-login.php');
    $isNode = !$isLaravel && file_exists($dir . '/package.json') &&
        !file_exists($dir . '/artisan');

    $type = 'php';
    if ($isLaravel)
        $type = 'laravel';
    elseif ($isWordPress)
        $type = 'wordpress';
    elseif ($isNode)
        $type = 'node';

    // --- Folder path (used by PHP opener endpoint) ---
    $real = realpath($dir);

    // --- Language scan ---
    $langs = $real ? detectLanguages($real) : [];

    // --- Project entry ---
    $projects[] = [
        'name' => $dir,
        'url' => 'http://' . $dir . '.test',
        'path' => $real ?: '',   // raw Windows path for the opener
        'type' => $type,
        'has_public' => file_exists($dir . '/public'),
        'has_index' => file_exists($dir . '/index.php') || file_exists($dir . '/index.html'),
        'modified' => is_readable($dir) ? date('M d, Y', filemtime($dir)) : '—',
        'created_ts' => is_readable($dir) ? filectime($dir) : 0,
        'langs' => array_slice($langs, 0, 7),  // cap at 7 displayed
    ];
}

usort($projects, fn($a, $b) => $b['created_ts'] - $a['created_ts']);

$total = count($projects);
$laravelC = count(array_filter($projects, fn($p) => $p['type'] === 'laravel'));
$wpC = count(array_filter($projects, fn($p) => $p['type'] === 'wordpress'));
$nodeC = count(array_filter($projects, fn($p) => $p['type'] === 'node'));
$phpC = $total - $laravelC - $wpC - $nodeC;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laragon Dev Hub</title>
    <style>
        /* ── TOKENS ──────────────────────────────────── */
        :root {
            --bg-base: #202020;
            --bg-nav: #1a1a1a;
            --bg-surface: #2b2b2b;
            --bg-card: #2d2d2d;
            --bg-card-hov: #363636;
            --bg-input: rgba(255, 255, 255, .06);
            --accent: #0078d4;
            --accent-hov: #1683d7;
            --accent-lit: #60cdff;
            --c-laravel: #ff6b63;
            --c-wp: #4db6e0;
            --c-php: #9fa3d9;
            --c-node: #68a063;
            --t1: rgba(255, 255, 255, .9);
            --t2: rgba(255, 255, 255, .58);
            --t3: rgba(255, 255, 255, .32);
            --bd: rgba(255, 255, 255, .07);
            --bd2: rgba(255, 255, 255, .13);
            --nav-w: 240px;
            --top-h: 48px;
            --sb-h: 26px;
            --radius: 6px;
            --tr: .15s ease;
        }

        /* ── RESET ───────────────────────────────────── */
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            font-family: 'Segoe UI Variable', 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-base);
            color: var(--t1);
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .14);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, .24);
        }

        /* ── TOP NAV ──────────────────────────────────── */
        .topnav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--top-h);
            background: rgba(26, 26, 26, .9);
            backdrop-filter: blur(24px) saturate(1.4);
            -webkit-backdrop-filter: blur(24px) saturate(1.4);
            border-bottom: 1px solid var(--bd);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 20px 0 16px;
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .brand-icon {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
        }

        .brand-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--t1);
            letter-spacing: .01em;
        }

        .brand-name span {
            color: var(--accent-lit);
        }

        .topnav-search {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-input);
            border: 1px solid var(--bd2);
            border-radius: var(--radius);
            padding: 6px 12px;
            margin-left: 20px;
            width: 260px;
            transition: border-color var(--tr), box-shadow var(--tr);
        }

        .topnav-search:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(0, 120, 212, .22);
        }

        .topnav-search svg {
            width: 13px;
            height: 13px;
            flex-shrink: 0;
            opacity: .4;
        }

        .topnav-search input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--t1);
            font: 13px inherit;
            width: 100%;
        }

        .topnav-search input::placeholder {
            color: var(--t3);
        }

        .topnav-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-left: auto;
        }

        .nav-icon-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius);
            border: none;
            background: transparent;
            color: var(--t2);
            cursor: pointer;
            transition: background var(--tr), color var(--tr);
        }

        .nav-icon-btn:hover {
            background: rgba(255, 255, 255, .07);
            color: var(--t1);
        }

        .nav-icon-btn svg {
            width: 16px;
            height: 16px;
        }

        .view-toggle {
            display: flex;
            background: rgba(255, 255, 255, .06);
            border: 1px solid var(--bd2);
            border-radius: var(--radius);
            overflow: hidden;
            margin-left: 6px;
        }

        .vt-btn {
            width: 32px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: var(--t3);
            cursor: pointer;
            transition: all var(--tr);
        }

        .vt-btn.active {
            background: var(--accent);
            color: #fff;
        }

        .vt-btn:hover:not(.active) {
            background: rgba(255, 255, 255, .07);
            color: var(--t1);
        }

        .vt-btn svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }

        /* ── LAYOUT ───────────────────────────────────── */
        .layout {
            display: flex;
            min-height: 100vh;
            padding-top: var(--top-h);
        }

        /* ── SIDEBAR ──────────────────────────────────── */
        .sidebar {
            width: var(--nav-w);
            flex-shrink: 0;
            background: var(--bg-nav);
            border-right: 1px solid var(--bd);
            position: fixed;
            top: var(--top-h);
            left: 0;
            bottom: var(--sb-h);
            overflow-y: auto;
            padding: 16px 0 24px;
            z-index: 50;
        }

        .nav-section {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--t3);
            padding: 14px 20px 5px;
        }

        .nav-section:first-child {
            padding-top: 6px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 8px 16px;
            margin: 1px 8px;
            border-radius: var(--radius);
            border: 1px solid transparent;
            font-size: 13px;
            color: var(--t2);
            text-decoration: none;
            cursor: pointer;
            transition: background var(--tr), color var(--tr), border-color var(--tr);
            position: relative;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, .06);
            color: var(--t1);
        }

        .nav-link.active {
            background: rgba(0, 120, 212, .16);
            border-color: rgba(0, 120, 212, .22);
            color: var(--accent-lit);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: -1px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 18px;
            background: var(--accent);
            border-radius: 0 2px 2px 0;
        }

        .nav-link svg {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            opacity: .65;
        }

        .nav-link.active svg {
            opacity: 1;
        }

        .nav-pill {
            margin-left: auto;
            background: rgba(255, 255, 255, .08);
            color: var(--t3);
            font-size: 10.5px;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 20px;
            min-width: 22px;
            text-align: center;
        }

        .nav-link.active .nav-pill {
            background: rgba(0, 120, 212, .28);
            color: var(--accent-lit);
        }

        .nav-divider {
            height: 1px;
            background: var(--bd);
            margin: 12px 16px;
        }

        /* ── MAIN ─────────────────────────────────────── */
        .main {
            flex: 1;
            margin-left: var(--nav-w);
            min-width: 0;
            padding-bottom: var(--sb-h);
        }

        .page-head {
            padding: 28px 28px 0;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
        }

        .page-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--t1);
            letter-spacing: -.01em;
        }

        .page-subtitle {
            font-size: 13px;
            color: var(--t2);
            margin-top: 4px;
        }

        .page-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: var(--radius);
            font: 13px 'Segoe UI Variable', 'Segoe UI', system-ui, sans-serif;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--tr);
            border: 1px solid transparent;
        }

        .btn svg {
            width: 13px;
            height: 13px;
            flex-shrink: 0;
        }

        .btn-accent {
            background: var(--accent);
            color: #fff;
            border-color: rgba(255, 255, 255, .08);
        }

        .btn-accent:hover {
            background: var(--accent-hov);
        }

        .btn-subtle {
            background: rgba(255, 255, 255, .06);
            color: var(--t2);
            border-color: var(--bd2);
        }

        .btn-subtle:hover {
            background: rgba(255, 255, 255, .1);
            color: var(--t1);
        }

        /* Stats row */
        .stats-row {
            display: flex;
            gap: 12px;
            padding: 20px 28px 0;
            flex-wrap: wrap;
        }

        .stat-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-surface);
            border: 1px solid var(--bd);
            border-radius: var(--radius);
            padding: 10px 16px;
            flex: 1;
            min-width: 100px;
        }

        .stat-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .stat-dot.all {
            background: var(--accent-lit);
        }

        .stat-dot.laravel {
            background: var(--c-laravel);
        }

        .stat-dot.wp {
            background: var(--c-wp);
        }

        .stat-dot.node {
            background: var(--c-node);
        }

        .stat-dot.php {
            background: var(--c-php);
        }

        .stat-num {
            font-size: 20px;
            font-weight: 700;
            color: var(--t1);
            line-height: 1;
        }

        .stat-label {
            font-size: 11px;
            color: var(--t3);
            margin-top: 2px;
        }

        /* Filter bar */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 20px 28px 0;
        }

        .bc {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--t3);
            margin-right: auto;
        }

        .bc a {
            color: var(--accent-lit);
            text-decoration: none;
        }

        .bc a:hover {
            text-decoration: underline;
        }

        .bc-sep {
            font-size: 10px;
        }

        .bc-cur {
            color: var(--t2);
        }

        /* ── PROJECTS AREA ────────────────────────────── */
        .projects-wrap {
            padding: 16px 28px 32px;
        }

        /* GRID VIEW */
        .grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 12px;
        }

        /* LIST VIEW — hidden by default */
        .list-view {
            display: none;
            flex-direction: column;
            background: var(--bg-surface);
            border: 1px solid var(--bd);
            border-radius: 8px;
            overflow: hidden;
        }

        /* ── GRID CARD ────────────────────────────────── */
        .g-card {
            background: var(--bg-card);
            border: 1px solid var(--bd);
            border-radius: 8px;
            padding: 18px;
            transition: background var(--tr), border-color var(--tr), transform .2s ease;
            position: relative;
            overflow: hidden;
        }

        .g-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            opacity: 0;
            transition: opacity .2s;
            border-radius: 8px 0 0 8px;
        }

        .g-card[data-type="laravel"]::before {
            background: var(--c-laravel);
        }

        .g-card[data-type="wordpress"]::before {
            background: var(--c-wp);
        }

        .g-card[data-type="node"]::before {
            background: var(--c-node);
        }

        .g-card[data-type="php"]::before {
            background: var(--c-php);
        }

        .g-card:hover {
            background: var(--bg-card-hov);
            border-color: var(--bd2);
            transform: translateY(-2px);
        }

        .g-card:hover::before {
            opacity: 1;
        }

        .gc-head {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }

        .gc-icon {
            width: 40px;
            height: 40px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .gc-icon.laravel {
            background: rgba(255, 107, 99, .14);
        }

        .gc-icon.wordpress {
            background: rgba(77, 182, 224, .14);
        }

        .gc-icon.node {
            background: rgba(104, 160, 99, .16);
        }

        .gc-icon.php {
            background: rgba(159, 163, 217, .14);
        }

        .gc-meta {
            flex: 1;
            min-width: 0;
        }

        .gc-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--t1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px;
        }

        .gc-domain {
            font-family: 'Cascadia Code', 'Consolas', monospace;
            font-size: 11px;
            color: var(--t3);
        }

        /* Type badge */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 9px;
            border-radius: 4px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .03em;
            flex-shrink: 0;
        }

        .badge-laravel {
            background: rgba(255, 107, 99, .14);
            color: var(--c-laravel);
            border: 1px solid rgba(255, 107, 99, .28);
        }

        .badge-wordpress {
            background: rgba(77, 182, 224, .14);
            color: var(--c-wp);
            border: 1px solid rgba(77, 182, 224, .28);
        }

        .badge-node {
            background: rgba(104, 160, 99, .16);
            color: var(--c-node);
            border: 1px solid rgba(104, 160, 99, .28);
        }

        .badge-php {
            background: rgba(159, 163, 217, .14);
            color: var(--c-php);
            border: 1px solid rgba(159, 163, 217, .28);
        }

        .gc-divider {
            border: none;
            border-top: 1px solid var(--bd);
            margin: 12px 0;
        }

        /* Info chips row */
        .gc-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
            min-height: 16px;
        }

        .chip {
            font-size: 11px;
            color: var(--t3);
            display: flex;
            align-items: center;
            gap: 3px;
        }

        /* Language pills */
        .gc-langs {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 12px;
            min-height: 18px;
        }

        .lang-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(255, 255, 255, .06);
        }

        .lang-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* Card actions */
        .gc-actions {
            display: flex;
            gap: 8px;
        }

        .gc-btn {
            flex: 1;
            padding: 7px 10px;
            border-radius: 5px;
            font: 12px 'Segoe UI Variable', 'Segoe UI', system-ui, sans-serif;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all var(--tr);
            border: 1px solid transparent;
            display: inline-block;
        }

        .gc-btn-p {
            background: var(--accent);
            color: #fff;
            border-color: rgba(255, 255, 255, .07);
        }

        .gc-btn-p:hover {
            background: var(--accent-hov);
        }

        .gc-btn-s {
            background: rgba(255, 255, 255, .06);
            color: var(--t2);
            border-color: var(--bd2);
            cursor: pointer;
        }

        .gc-btn-s:hover {
            background: rgba(255, 255, 255, .1);
            color: var(--t1);
        }

        /* ── LIST VIEW ────────────────────────────────── */
        .lv-header {
            display: flex;
            align-items: center;
            padding: 8px 16px;
            border-bottom: 1px solid var(--bd);
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--t3);
        }

        .lv-row {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            border-bottom: 1px solid var(--bd);
            transition: background var(--tr);
        }

        .lv-row:last-child {
            border-bottom: none;
        }

        .lv-row:hover {
            background: rgba(255, 255, 255, .04);
        }

        .lv-icon {
            width: 30px;
            height: 30px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            margin-right: 14px;
        }

        .lv-icon.laravel {
            background: rgba(255, 107, 99, .13);
        }

        .lv-icon.wordpress {
            background: rgba(77, 182, 224, .13);
        }

        .lv-icon.node {
            background: rgba(104, 160, 99, .13);
        }

        .lv-icon.php {
            background: rgba(159, 163, 217, .13);
        }

        .col-name {
            flex: 1;
            font-size: 13px;
            color: var(--t1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }

        .col-domain {
            width: 190px;
            font-family: 'Cascadia Code', 'Consolas', monospace;
            font-size: 11px;
            color: var(--t3);
            flex-shrink: 0;
        }

        .col-langs {
            width: 200px;
            flex-shrink: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .col-mod {
            width: 100px;
            font-size: 11px;
            color: var(--t3);
            flex-shrink: 0;
        }

        .col-type {
            width: 100px;
            flex-shrink: 0;
        }

        .col-act {
            width: 110px;
            flex-shrink: 0;
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }

        .lv-btn {
            padding: 4px 11px;
            border-radius: 4px;
            font: 11.5px 'Segoe UI Variable', 'Segoe UI', system-ui, sans-serif;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--tr);
            border: 1px solid transparent;
            display: inline-block;
            text-align: center;
        }

        .lv-btn-p {
            background: var(--accent);
            color: #fff;
        }

        .lv-btn-p:hover {
            background: var(--accent-hov);
        }

        .lv-btn-s {
            background: rgba(255, 255, 255, .07);
            color: var(--t2);
            border-color: var(--bd2);
            cursor: pointer;
        }

        .lv-btn-s:hover {
            background: rgba(255, 255, 255, .12);
            color: var(--t1);
        }

        /* ── EMPTY STATE ──────────────────────────────── */
        .empty-state {
            grid-column: 1/-1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 20px;
            text-align: center;
        }

        .empty-state svg {
            width: 52px;
            height: 52px;
            stroke: var(--t3);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 15px;
            color: var(--t2);
            margin-bottom: 6px;
        }

        .empty-state p {
            font-size: 12px;
            color: var(--t3);
        }

        /* ── STATUS BAR ───────────────────────────────── */
        .status-bar {
            position: fixed;
            bottom: 0;
            left: var(--nav-w);
            right: 0;
            height: var(--sb-h);
            background: rgba(22, 22, 22, .9);
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--bd);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 20px;
            font-size: 11px;
            color: var(--t3);
            z-index: 50;
        }

        .sb-sep {
            width: 1px;
            height: 12px;
            background: var(--bd2);
        }

        .sb-live {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #16c60c;
            flex-shrink: 0;
        }

        /* ── TOAST ────────────────────────────────────── */
        .toast-wrap {
            position: fixed;
            top: 60px;
            right: 16px;
            z-index: 999;
            display: flex;
            flex-direction: column;
            gap: 8px;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #2f2f2f;
            border: 1px solid var(--bd2);
            border-radius: 8px;
            padding: 10px 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .5);
            font-size: 13px;
            color: var(--t1);
            pointer-events: all;
            min-width: 240px;
            animation: toast-in .2s ease;
        }

        .toast.removing {
            animation: toast-out .2s ease forwards;
        }

        @keyframes toast-in {
            from {
                opacity: 0;
                transform: translateX(16px)
            }

            to {
                opacity: 1;
                transform: translateX(0)
            }
        }

        @keyframes toast-out {
            from {
                opacity: 1;
                transform: translateX(0)
            }

            to {
                opacity: 0;
                transform: translateX(16px)
            }
        }

        /* ── HIDDEN ───────────────────────────────────── */
        .hidden {
            display: none !important;
        }

        /* ── RESPONSIVE ───────────────────────────────── */
        @media(max-width:900px) {
            :root {
                --nav-w: 0px;
            }

            .sidebar {
                display: none;
            }

            .status-bar {
                left: 0;
            }

            .col-domain,
            .col-mod,
            .col-langs {
                display: none;
            }

            .grid-view {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- ── TOP NAV ── -->
    <nav class="topnav">
        <a class="brand" href="#">
            <svg class="brand-icon" viewBox="0 0 28 28" fill="none">
                <rect width="28" height="28" rx="6" fill="#22a65a" />
                <polygon points="14,5 22,14 14,23 6,14" fill="rgba(255,255,255,.92)" />
                <polygon points="14,9 18,14 14,19 10,14" fill="#22a65a" />
            </svg>
            <span class="brand-name">Laragon <span>Dev Hub</span></span>
        </a>

        <div class="topnav-search">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                <circle cx="7" cy="7" r="4.5" />
                <line x1="10.5" y1="10.5" x2="13.5" y2="13.5" />
            </svg>
            <input type="text" id="searchInput" placeholder="Search projects…" oninput="applyFilters()"
                autocomplete="off">
        </div>

        <div class="topnav-actions">
            <button class="nav-icon-btn" onclick="location.reload()" title="Refresh">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M2.5 8a5.5 5.5 0 1 1 .9 3" />
                    <polyline points="2.5,5 2.5,8 5.5,8" />
                </svg>
            </button>
            <div class="view-toggle">
                <button class="vt-btn active" id="btnGrid" onclick="setView('grid')" title="Grid view">
                    <svg viewBox="0 0 14 14">
                        <rect x="1" y="1" width="5" height="5" rx="1" />
                        <rect x="8" y="1" width="5" height="5" rx="1" />
                        <rect x="1" y="8" width="5" height="5" rx="1" />
                        <rect x="8" y="8" width="5" height="5" rx="1" />
                    </svg>
                </button>
                <button class="vt-btn" id="btnList" onclick="setView('list')" title="List view">
                    <svg viewBox="0 0 14 14">
                        <rect x="1" y="2" width="12" height="2" rx="1" />
                        <rect x="1" y="6" width="12" height="2" rx="1" />
                        <rect x="1" y="10" width="12" height="2" rx="1" />
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <!-- ── LAYOUT ── -->
    <div class="layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="nav-section">Workspace</div>

            <a class="nav-link active" href="#" onclick="filterType('all',this);return false;">
                <svg viewBox="0 0 16 16" fill="currentColor">
                    <path
                        d="M1 2.5A1.5 1.5 0 0 1 2.5 1h11A1.5 1.5 0 0 1 15 2.5v11a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 13.5v-11zm1.5-.5a.5.5 0 0 0-.5.5V6h12V2.5a.5.5 0 0 0-.5-.5h-11zM2 7v6.5a.5.5 0 0 0 .5.5H7V7H2zm6 0v7h5.5a.5.5 0 0 0 .5-.5V7H8z" />
                </svg>
                All Projects
                <span class="nav-pill"><?php echo $total; ?></span>
            </a>
            <a class="nav-link" href="#" onclick="filterType('laravel',this);return false;">
                <svg viewBox="0 0 16 16" fill="#ff6b63">
                    <path
                        d="M8 .5a7.5 7.5 0 1 0 0 15A7.5 7.5 0 0 0 8 .5zm0 1.5a6 6 0 1 1 0 12A6 6 0 0 1 8 2zm-1 3L5 10l2 1 .5-2L9 7l-2-2z"
                        opacity=".9" />
                </svg>
                Laravel
                <span class="nav-pill"><?php echo $laravelC; ?></span>
            </a>
            <a class="nav-link" href="#" onclick="filterType('wordpress',this);return false;">
                <svg viewBox="0 0 16 16" fill="#4db6e0">
                    <path
                        d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zM1.5 8A6.5 6.5 0 0 1 2 5.6L4.6 13A6.5 6.5 0 0 1 1.5 8zM8 14.5a6.47 6.47 0 0 1-2-.3L8.1 8.8l2.1 5.7a6.5 6.5 0 0 1-2.2.3zm2.8-.9L13 7.1H12l-1.5 4.3L9 7.1H7.9l.2-.5L9.5 1.7A6.5 6.5 0 0 1 14.2 6h-.6l-1.3 3.7 1.3-3.7h.6a6.5 6.5 0 0 1-3.4 7.6z"
                        opacity=".9" />
                </svg>
                WordPress
                <span class="nav-pill"><?php echo $wpC; ?></span>
            </a>
            <a class="nav-link" href="#" onclick="filterType('node',this);return false;">
                <svg viewBox="0 0 16 16" fill="#68a063">
                    <path d="M8 0l7 4v8l-7 4-7-4V4z" opacity=".9" />
                </svg>
                Node.js
                <span class="nav-pill"><?php echo $nodeC; ?></span>
            </a>
            <a class="nav-link" href="#" onclick="filterType('php',this);return false;">
                <svg viewBox="0 0 16 16" fill="#9fa3d9">
                    <path
                        d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 1.5a5.5 5.5 0 1 1 0 11A5.5 5.5 0 0 1 8 2.5zm-1.3 2.8C7 4.6 7.8 4.2 9 4.2c1 0 1.8.3 2.3 1s.8 1.6.8 2.8c0 1.2-.3 2.1-.9 2.8-.5.6-1.3 1-2.2 1-.9 0-1.6-.3-2-.8l-.5 1.8H5.1L7 6.5l-.3-.7 1-1z"
                        opacity=".9" />
                </svg>
                PHP / Other
                <span class="nav-pill"><?php echo $phpC; ?></span>
            </a>

            <div class="nav-divider"></div>
            <div class="nav-section">Tools</div>

            <a class="nav-link" href="http://localhost/phpmyadmin" target="_blank" rel="noopener">
                <svg viewBox="0 0 16 16" fill="currentColor" opacity=".65">
                    <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1H0V2zm0 4h16v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6z" />
                </svg>
                phpMyAdmin
            </a>
            <a class="nav-link" href="http://localhost:8025" target="_blank" rel="noopener">
                <svg viewBox="0 0 16 16" fill="currentColor" opacity=".65">
                    <path
                        d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555zM0 4.697v7.104l5.803-3.558L0 4.697zm6.761 8.083 6.57-4.027L16 11.8V14a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.2l6.761-1.02z" />
                </svg>
                MailHog
            </a>
            <a class="nav-link" href="http://localhost" target="_blank" rel="noopener">
                <svg viewBox="0 0 16 16" fill="currentColor" opacity=".65">
                    <path
                        d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm7.5-6.923c-.67.204-1.335.82-1.887 1.855-.43.832-.711 1.87-.83 3.068h2.717V1.077zM4.047 4c.03-.077.062-.155.095-.23.636-1.428 1.52-2.234 2.458-2.5v2.73H4.047zM3.603 5a13.4 13.4 0 0 0-.093 1.5c0 .528.036 1.03.093 1.5H6.5V5H3.603zm0 4H6.5v3H3.603A9.7 9.7 0 0 1 3.51 10a9.7 9.7 0 0 1 .093-1zm2.897 4v2.73c-.938-.266-1.822-1.072-2.458-2.5-.033-.075-.065-.153-.095-.23H6.5zm1 0h2.453c-.03.077-.062.155-.095.23-.636 1.428-1.52 2.234-2.458 2.5V13zm3.297-1H8v3H9.5v-3zm0-4H8v3h2.9A9.7 9.7 0 0 0 11 9a9.7 9.7 0 0 0-.093-1H8zm0-4H8V1.077c.938.266 1.822 1.072 2.458 2.5.033.075.065.153.095.23H10.5z" />
                </svg>
                Localhost
            </a>
        </aside>

        <!-- MAIN -->
        <main class="main">

            <div class="page-head">
                <div>
                    <div class="page-title" id="pageTitle">All Projects</div>
                    <div class="page-subtitle">C:\laragon\www — <?php echo $total; ?>
                        project<?php echo $total !== 1 ? 's' : ''; ?> found</div>
                </div>
                <div class="page-actions">
                    <button class="btn btn-subtle" onclick="toggleSort()">
                        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round">
                            <line x1="2" y1="3" x2="9" y2="3" />
                            <line x1="2" y1="7" x2="7" y2="7" />
                            <line x1="2" y1="11" x2="5" y2="11" />
                        </svg>
                        Sort
                    </button>
                    <button class="btn btn-accent" onclick="location.reload()">
                        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 7a5 5 0 1 1 .8 2.6" />
                            <polyline points="2,4 2,7 5,7" />
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-chip">
                    <span class="stat-dot all"></span>
                    <div>
                        <div class="stat-num"><?php echo $total; ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                <div class="stat-chip">
                    <span class="stat-dot laravel"></span>
                    <div>
                        <div class="stat-num"><?php echo $laravelC; ?></div>
                        <div class="stat-label">Laravel</div>
                    </div>
                </div>
                <div class="stat-chip">
                    <span class="stat-dot wp"></span>
                    <div>
                        <div class="stat-num"><?php echo $wpC; ?></div>
                        <div class="stat-label">WordPress</div>
                    </div>
                </div>
                <div class="stat-chip">
                    <span class="stat-dot node"></span>
                    <div>
                        <div class="stat-num"><?php echo $nodeC; ?></div>
                        <div class="stat-label">Node.js</div>
                    </div>
                </div>
                <div class="stat-chip">
                    <span class="stat-dot php"></span>
                    <div>
                        <div class="stat-num"><?php echo $phpC; ?></div>
                        <div class="stat-label">PHP/Other</div>
                    </div>
                </div>
            </div>

            <!-- Filter bar -->
            <div class="filter-bar">
                <div class="bc">
                    <a href="#">Laragon</a><span class="bc-sep">›</span>
                    <a href="#">www</a><span class="bc-sep">›</span>
                    <span class="bc-cur" id="bcCurrent">All Projects</span>
                </div>
                <span id="filterCount" style="font-size:12px;color:var(--t3)"><?php echo $total; ?> items</span>
            </div>

            <!-- Projects -->
            <div class="projects-wrap">

                <!-- GRID VIEW -->
                <div class="grid-view" id="gridView">
                    <?php if ($total === 0): ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.2">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
                            </svg>
                            <h3>No projects found</h3>
                            <p>Add a folder to C:\laragon\www to see it here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $p):
                            $t = htmlspecialchars($p['type']);
                            $n = htmlspecialchars($p['name']);
                            $u = htmlspecialchars($p['url']);
                            $mo = htmlspecialchars($p['modified']);
                            $ph = htmlspecialchars($p['path']);
                            $ic = $p['type'] === 'laravel' ? '🔺'
                                : ($p['type'] === 'wordpress' ? '🔵'
                                    : ($p['type'] === 'node' ? '🟢' : '🟣'));
                            ?>
                            <div class="g-card" data-type="<?php echo $t; ?>" data-name="<?php echo strtolower($n); ?>"
                                data-created="<?php echo $p['created_ts']; ?>">
                                <div class="gc-head">
                                    <div class="gc-icon <?php echo $t; ?>"><?php echo $ic; ?></div>
                                    <div class="gc-meta">
                                        <div class="gc-name" title="<?php echo $n; ?>"><?php echo $n; ?></div>
                                        <div class="gc-domain"><?php echo $n; ?>.test</div>
                                    </div>
                                    <span
                                        class="badge badge-<?php echo $t; ?>"><?php echo ucfirst($t === 'node' ? 'Node' : $t); ?></span>
                                </div>
                                <hr class="gc-divider">

                                <!-- Info chips -->
                                <div class="gc-chips">
                                    <?php if ($p['has_public']): ?><span class="chip">📁 /public</span><?php endif; ?>
                                    <?php if ($p['has_index']): ?><span class="chip">📄 index</span><?php endif; ?>
                                    <span class="chip">🕐 <?php echo $mo; ?></span>
                                </div>

                                <!-- Language pills (scanned from files) -->
                                <?php if (!empty($p['langs'])): ?>
                                    <div class="gc-langs">
                                        <?php foreach ($p['langs'] as $lang): ?>
                                            <span class="lang-pill">
                                                <span class="lang-dot"
                                                    style="background:<?php echo htmlspecialchars($lang['color']); ?>"></span>
                                                <?php echo htmlspecialchars($lang['label']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="gc-actions">
                                    <a href="<?php echo $u; ?>" class="gc-btn gc-btn-p" target="_blank" rel="noopener">▶
                                        Open</a>
                                    <button class="gc-btn gc-btn-s" onclick="openFolder('<?php echo addslashes($ph); ?>')"
                                        title="Open in Explorer">
                                        📁 Folder
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- LIST VIEW -->
                <div class="list-view" id="listView">
                    <div class="lv-header">
                        <div style="width:44px;"></div>
                        <div class="col-name">Name</div>
                        <div class="col-domain">Domain</div>
                        <div class="col-langs">Languages</div>
                        <div class="col-mod">Modified</div>
                        <div class="col-type">Type</div>
                        <div class="col-act"></div>
                    </div>
                    <?php foreach ($projects as $p):
                        $t = htmlspecialchars($p['type']);
                        $n = htmlspecialchars($p['name']);
                        $u = htmlspecialchars($p['url']);
                        $mo = htmlspecialchars($p['modified']);
                        $ph = htmlspecialchars($p['path']);
                        $ic = $p['type'] === 'laravel' ? '🔺'
                            : ($p['type'] === 'wordpress' ? '🔵'
                                : ($p['type'] === 'node' ? '🟢' : '🟣'));
                        ?>
                        <div class="lv-row" data-type="<?php echo $t; ?>" data-name="<?php echo strtolower($n); ?>"
                            data-created="<?php echo $p['created_ts']; ?>">
                            <div class="lv-icon <?php echo $t; ?>"><?php echo $ic; ?></div>
                            <div class="col-name" title="<?php echo $n; ?>"><?php echo $n; ?></div>
                            <div class="col-domain"><?php echo $n; ?>.test</div>
                            <div class="col-langs">
                                <?php foreach (array_slice($p['langs'], 0, 4) as $lang): ?>
                                    <span class="lang-pill">
                                        <span class="lang-dot"
                                            style="background:<?php echo htmlspecialchars($lang['color']); ?>"></span>
                                        <?php echo htmlspecialchars($lang['label']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <div class="col-mod"><?php echo $mo; ?></div>
                            <div class="col-type">
                                <span
                                    class="badge badge-<?php echo $t; ?>"><?php echo ucfirst($t === 'node' ? 'Node' : $t); ?></span>
                            </div>
                            <div class="col-act">
                                <a href="<?php echo $u; ?>" class="lv-btn lv-btn-p" target="_blank" rel="noopener">▶
                                    Open</a>
                                <button class="lv-btn lv-btn-s" onclick="openFolder('<?php echo addslashes($ph); ?>')"
                                    title="Open folder">📁</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div><!-- /projects-wrap -->
        </main>
    </div><!-- /layout -->

    <!-- Status bar -->
    <div class="status-bar">
        <span class="sb-live"></span>
        <span id="sbCount"><?php echo $total; ?> item<?php echo $total !== 1 ? 's' : ''; ?></span>
        <span class="sb-sep"></span>
        <span>🔺 <?php echo $laravelC; ?> Laravel</span>
        <span class="sb-sep"></span>
        <span>🟣 <?php echo $phpC; ?> PHP</span>
        <?php if ($wpC > 0): ?>
            <span class="sb-sep"></span><span>🔵 <?php echo $wpC; ?> WordPress</span>
        <?php endif; ?>
        <?php if ($nodeC > 0): ?>
            <span class="sb-sep"></span><span>🟢 <?php echo $nodeC; ?> Node</span>
        <?php endif; ?>
        <span style="margin-left:auto;opacity:.5">All .test domains active</span>
    </div>

    <!-- Toast container -->
    <div class="toast-wrap" id="toastWrap"></div>

    <script>
        /* ── STATE ── */
        var curView = 'grid';
        var curType = 'all';
        var sortAsc = true;

        var bcMap = {
            all: 'All Projects',
            laravel: 'Laravel Projects',
            wordpress: 'WordPress Projects',
            node: 'Node.js Projects',
            php: 'PHP / Other'
        };

        /* ── VIEW TOGGLE ── */
        function setView(v) {
            curView = v;
            var gv = document.getElementById('gridView');
            var lv = document.getElementById('listView');
            var bg = document.getElementById('btnGrid');
            var bl = document.getElementById('btnList');

            if (v === 'list') {
                gv.style.display = 'none';
                lv.style.display = 'flex';
                lv.style.flexDirection = 'column';
                bg.classList.remove('active');
                bl.classList.add('active');
            } else {
                gv.style.display = 'grid';
                lv.style.display = 'none';
                bg.classList.add('active');
                bl.classList.remove('active');
            }
        }

        /* ── SIDEBAR TYPE FILTER ── */
        function filterType(type, el) {
            curType = type;

            // Reset all nav links then activate clicked one
            document.querySelectorAll('.nav-link').forEach(function (n) {
                n.classList.remove('active');
            });
            if (el) el.classList.add('active');

            var bc = document.getElementById('bcCurrent');
            if (bc) bc.textContent = bcMap[type] || 'All Projects';

            var pt = document.getElementById('pageTitle');
            if (pt) pt.textContent = bcMap[type] || 'All Projects';

            applyFilters();
        }

        /* ── APPLY FILTERS (search + type) ── */
        function applyFilters() {
            var q = document.getElementById('searchInput').value.toLowerCase().trim();

            // Filter grid cards
            var gridCards = document.querySelectorAll('#gridView .g-card');
            var listRows = document.querySelectorAll('#listView .lv-row');
            var visible = 0;

            gridCards.forEach(function (el) {
                var nameOk = !q || el.dataset.name.indexOf(q) !== -1;
                var typeOk = curType === 'all' || el.dataset.type === curType;
                if (nameOk && typeOk) {
                    el.classList.remove('hidden');
                    visible++;
                } else {
                    el.classList.add('hidden');
                }
            });

            // Mirror same logic to list rows (same count, just sync visibility)
            listRows.forEach(function (el) {
                var nameOk = !q || el.dataset.name.indexOf(q) !== -1;
                var typeOk = curType === 'all' || el.dataset.type === curType;
                if (nameOk && typeOk) {
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            });

            // Update counts
            var sb = document.getElementById('sbCount');
            if (sb) sb.textContent = visible + ' item' + (visible !== 1 ? 's' : '');
            var fc = document.getElementById('filterCount');
            if (fc) fc.textContent = visible + ' item' + (visible !== 1 ? 's' : '');
        }

        /* ── SORT ── */
        var sortMode = 2; // 0=A→Z  1=Z→A  2=Newest  3=Oldest
        var sortLabels = ['Name A → Z', 'Name Z → A', 'Newest First', 'Oldest First'];

        function toggleSort() {
            sortMode = (sortMode + 1) % 4;

            function reorder(containerId, selector) {
                var container = document.getElementById(containerId);
                if (!container) return;
                var items = Array.prototype.slice.call(container.querySelectorAll(selector));
                items.sort(function (a, b) {
                    if (sortMode === 0) return (a.dataset.name || '').localeCompare(b.dataset.name || '');
                    if (sortMode === 1) return (b.dataset.name || '').localeCompare(a.dataset.name || '');
                    if (sortMode === 2) return Number(b.dataset.created || 0) - Number(a.dataset.created || 0);
                    if (sortMode === 3) return Number(a.dataset.created || 0) - Number(b.dataset.created || 0);
                });
                items.forEach(function (el) { container.appendChild(el); });
            }

            reorder('gridView', '.g-card');
            reorder('listView', '.lv-row');

            showToast('Sorted: ' + sortLabels[sortMode], 'info');
        }

        /* ── FOLDER OPENER ── */
        // Browsers block file:// links from http:// pages.
        // Instead we call a PHP endpoint that runs explorer.exe on the server.
        function openFolder(path) {
            if (!path) {
                showToast('No path available for this project', 'error');
                return;
            }
            fetch('?action=open&p=' + encodeURIComponent(path))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        showToast('Opening folder…', 'success');
                    } else {
                        showToast('Could not open folder', 'error');
                    }
                })
                .catch(function () {
                    showToast('Could not open folder', 'error');
                });
        }

        /* ── TOAST NOTIFICATION ── */
        function showToast(msg, type) {
            var icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
            var wrap = document.getElementById('toastWrap');
            var el = document.createElement('div');
            el.className = 'toast';
            el.innerHTML = '<span>' + (icons[type] || icons.info) + '</span> ' + msg;
            wrap.appendChild(el);
            setTimeout(function () {
                el.classList.add('removing');
                el.addEventListener('animationend', function () { el.remove(); });
            }, 3000);
        }

        /* ── INIT ── */
        (function init() {
            var total = document.querySelectorAll('#gridView .g-card').length;
            var sb = document.getElementById('sbCount');
            var fc = document.getElementById('filterCount');
            if (sb) sb.textContent = total + ' item' + (total !== 1 ? 's' : '');
            if (fc) fc.textContent = total + ' item' + (total !== 1 ? 's' : '');
        })();
    </script>

</body>

</html>

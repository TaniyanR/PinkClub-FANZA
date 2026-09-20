<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/images.php';

if (!function_exists('pcf_parse_image_urls')) {
    function pcf_parse_image_urls(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $urls = [];
            foreach ($decoded as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $urls[] = trim($item);
                } elseif (is_array($item)) {
                    foreach (['large', 'small', 'url', 'src'] as $k) {
                        if (!empty($item[$k]) && is_string($item[$k])) {
                            $urls[] = trim($item[$k]);
                            break;
                        }
                    }
                }
            }
            if ($urls !== []) {
                return array_values(array_unique($urls));
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $value);
        $urls = [];
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '' && filter_var($line, FILTER_VALIDATE_URL)) {
                    $urls[] = $line;
                }
            }
        }

        return array_values(array_unique($urls));
    }
}

if (!function_exists('pcf_maybe_decode_json_val')) {
    function pcf_maybe_decode_json_val($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return $value;
        }
        $trimmed = trim($value);
        if (($trimmed[0] === '{' && substr($trimmed, -1) === '}') || ($trimmed[0] === '[' && substr($trimmed, -1) === ']')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return $value;
    }
}

if (!function_exists('pcf_looks_like_image_url')) {
    function pcf_looks_like_image_url(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }
        return (bool)preg_match('/\.(jpe?g|png|webp|gif)$/i', $path);
    }
}

if (!function_exists('pcf_is_self_hosted_fanza_image')) {
    function pcf_is_self_hosted_fanza_image(string $url): bool
    {
        return str_starts_with($url, '/uploads/fanza/');
    }
}

if (!function_exists('pcf_first_image_from_mixed')) {
    function pcf_first_image_from_mixed($value): string
    {
        $val = pcf_maybe_decode_json_val($value);
        if (is_string($val)) {
            $val = trim($val);
            if (pcf_is_self_hosted_fanza_image($val) || pcf_looks_like_image_url($val)) {
                return $val;
            }
            return '';
        }
        if (is_array($val)) {
            foreach (['large', 'small', 'url', 'src'] as $k) {
                if (!empty($val[$k]) && is_string($val[$k])) {
                    $candidate = trim($val[$k]);
                    if (pcf_is_self_hosted_fanza_image($candidate) || pcf_looks_like_image_url($candidate)) {
                        return $candidate;
                    }
                }
            }
            foreach ($val as $sub) {
                $candidate = pcf_first_image_from_mixed($sub);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }
        return '';
    }
}

if (!function_exists('pcf_first_text_from_mixed')) {
    function pcf_first_text_from_mixed($value): string
    {
        $val = pcf_maybe_decode_json_val($value);
        if (is_string($val)) {
            return trim($val);
        }
        if (is_array($val)) {
            foreach (['name', 'title', 'label', 'text'] as $k) {
                if (!empty($val[$k]) && is_string($val[$k])) {
                    return trim($val[$k]);
                }
            }
            foreach ($val as $sub) {
                $candidate = pcf_first_text_from_mixed($sub);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }
        return '';
    }
}

if (!function_exists('pcf_first_text_by_keys')) {
    function pcf_first_text_by_keys(array $row, array $keys): string
    {
        foreach ($keys as $k) {
            if (isset($row[$k])) {
                $txt = pcf_first_text_from_mixed($row[$k]);
                if ($txt !== '') {
                    return $txt;
                }
            }
        }
        return '';
    }
}

if (!function_exists('pcf_first_image_by_keys')) {
    function pcf_first_image_by_keys(array $row, array $keys): string
    {
        foreach ($keys as $k) {
            if (isset($row[$k])) {
                $img = pcf_first_image_from_mixed($row[$k]);
                if ($img !== '') {
                    return $img;
                }
            }
        }
        return '';
    }
}

if (!function_exists('pcf_item_image')) {
    function pcf_item_image(array $item): string
    {
        $img = pcf_pick_detail_main_image($item);
        if ($img !== '') {
            return $img;
        }

        $img = pcf_first_image_by_keys($item, [
            'package_image',
            'image_url',
            'image_large',
            'image_small',
            'image_list',
            'package_url',
            'thumb_url',
            'thumbnail_url',
            'sample_images',
        ]);
        if ($img !== '') {
            return $img;
        }
        return pcf_placeholder_data_uri('No Image');
    }
}

if (!function_exists('pcf_item_title')) {
    function pcf_item_title(array $item): string
    {
        $title = pcf_first_text_by_keys($item, [
            'title',
            'name',
            'product_name',
        ]);
        return $title !== '' ? $title : '名称未設定';
    }
}

if (!function_exists('pcf_item_content_id')) {
    function pcf_item_content_id(array $item): string
    {
        return pcf_first_text_by_keys($item, [
            'content_id',
            'dmm_id',
            'cid',
            'sku',
        ]);
    }
}

if (!function_exists('pcf_item_price_text')) {
    function pcf_item_price_text(array $item): string
    {
        if (isset($item['price']) && is_numeric($item['price'])) {
            $price = (int)$item['price'];
            if ($price > 0) {
                return '¥' . number_format($price) . '〜';
            }
        }
        return '';
    }
}

if (!function_exists('pcf_item_release_date')) {
    function pcf_item_release_date(array $item): string
    {
        $raw = pcf_first_text_by_keys($item, [
            'date_released',
            'release_date',
            'date',
            'created_at',
        ]);
        if ($raw === '' || $raw === '0000-00-00' || str_starts_with($raw, '0000-00-00')) {
            return '';
        }
        return substr($raw, 0, 10);
    }
}

if (!function_exists('pcf_render_header_search')) {
    function pcf_render_header_search(string $keyword = '', string $media = 'all', string $sort = 'rank'): void
    {
        $keyword = trim($keyword);
        $media = trim($media);
        $sort = trim($sort);

        echo '<form class="pcf-header-search" action="' . e(public_url('items.php')) . '" method="get" role="search">';
        echo '<div class="pcf-header-search__inner">';
        echo '<div class="pcf-header-search__field">';
        echo '<input type="search" name="keyword" value="' . e($keyword) . '" class="pcf-header-search__input" placeholder="作品名・女優名・品番で検索" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" aria-label="検索キーワード">';
        echo '</div>';
        echo '<div class="pcf-header-search__controls">';
        echo '<select name="media" class="pcf-header-search__select" aria-label="メディア種別">';
        echo '<option value="all"' . ($media === 'all' || $media === '' ? ' selected' : '') . '>すべて</option>';
        echo '<option value="video"' . ($media === 'video' ? ' selected' : '') . '>動画</option>';
        echo '</select>';
        echo '<select name="sort" class="pcf-header-search__select" aria-label="並び順">';
        echo '<option value="rank"' . ($sort === 'rank' || $sort === '' ? ' selected' : '') . '>人気順</option>';
        echo '<option value="date"' . ($sort === 'date' ? ' selected' : '') . '>新着順</option>';
        echo '<option value="price"' . ($sort === 'price' ? ' selected' : '') . '>価格順</option>';
        echo '</select>';
        echo '<button type="submit" class="pcf-header-search__submit" aria-label="検索する">';
        echo '<span class="pcf-header-search__submit-icon" aria-hidden="true">🔍</span>';
        echo '<span class="pcf-header-search__submit-text">検索</span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
}

if (!function_exists('pcf_render_breadcrumbs')) {
    function pcf_render_breadcrumbs(array $crumbs): void
    {
        if ($crumbs === []) {
            return;
        }

        echo '<nav class="pcf-breadcrumb" aria-label="パンくずリスト">';
        echo '<ol class="pcf-breadcrumb__list" itemscope itemtype="https://schema.org/BreadcrumbList">';

        $position = 1;
        $count = count($crumbs);
        foreach ($crumbs as $index => $crumb) {
            $label = trim((string)($crumb['label'] ?? ''));
            $url = trim((string)($crumb['url'] ?? ''));
            $isLast = ($index === $count - 1);

            if ($label === '') {
                continue;
            }

            echo '<li class="pcf-breadcrumb__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            if (!$isLast && $url !== '') {
                echo '<a class="pcf-breadcrumb__link" href="' . e($url) . '" itemprop="item"><span itemprop="name">' . e($label) . '</span></a>';
            } else {
                echo '<span class="pcf-breadcrumb__current" aria-current="page" itemprop="name">' . e($label) . '</span>';
            }
            echo '<meta itemprop="position" content="' . $position . '">';
            echo '</li>';
            $position++;
        }

        echo '</ol>';
        echo '</nav>';
    }
}

if (!function_exists('pcf_render_item_card')) {
    function pcf_render_item_card(array $item, array $options = []): void
    {
        $id = (int)($item['id'] ?? 0);
        $title = pcf_item_title($item);
        $image = pcf_item_image($item);
        $price = pcf_item_price_text($item);
        $date = pcf_item_release_date($item);
        $contentId = pcf_item_content_id($item);

        $detailUrl = public_url('item.php') . '?id=' . rawurlencode((string)$id);
        $outUrl = pcf_out_url($id, (string)($options['position'] ?? 'card'));
        $sampleMovieUrl = pcf_item_sample_movie_url($item);

        echo '<article class="pcf-card" data-item-id="' . e((string)$id) . '">';
        echo '<div class="pcf-card__thumb-wrap">';
        echo '<a href="' . e($detailUrl) . '" class="pcf-card__thumb-link" aria-label="' . e($title) . 'の詳細へ">';
        echo '<img class="pcf-card__thumb" src="' . e($image) . '" alt="' . e($title) . '" loading="lazy" decoding="async" onerror="this.onerror=null;this.src=' . "'" . e(pcf_placeholder_data_uri('No Image')) . "'" . ';">';
        echo '</a>';

        if ($sampleMovieUrl !== '') {
            echo '<button type="button" class="pcf-card__quick-play sample-movie-trigger" data-movie-url="' . e($sampleMovieUrl) . '" data-movie-title="' . e($title) . '" aria-label="サンプル動画を再生">';
            echo '▶ 再生';
            echo '</button>';
        }

        echo '</div>';

        echo '<div class="pcf-card__body">';
        echo '<h3 class="pcf-card__title">';
        echo '<a href="' . e($detailUrl) . '">' . e($title) . '</a>';
        echo '</h3>';

        echo '<div class="pcf-card__meta">';
        if ($date !== '') {
            echo '<span class="pcf-card__date">' . e($date) . '</span>';
        }
        if ($price !== '') {
            echo '<span class="pcf-card__price">' . e($price) . '</span>';
        }
        echo '</div>';

        echo '<div class="pcf-card__actions">';
        echo '<a href="' . e($detailUrl) . '" class="pcf-card__btn pcf-card__btn--detail">詳細</a>';
        echo '<a href="' . e($outUrl) . '" target="_blank" rel="nofollow noopener noreferrer" class="pcf-card__btn pcf-card__btn--fanza">FANZA</a>';
        echo '</div>';

        echo '</div>';
        echo '</article>';
    }
}

if (!function_exists('pcf_render_item_grid')) {
    function pcf_render_item_grid(array $items, array $options = []): void
    {
        if ($items === []) {
            return;
        }

        echo '<div class="pcf-item-grid">';
        foreach ($items as $item) {
            if (is_array($item)) {
                pcf_render_item_card($item, $options);
            }
        }
        echo '</div>';
    }
}

if (!function_exists('pcf_render_pagination')) {
    function pcf_render_pagination(array $pg, string $baseUrl, array $params = []): void
    {
        $currentPage = (int)($pg['page'] ?? 1);
        $totalPages = (int)($pg['total_pages'] ?? 1);

        if ($totalPages <= 1) {
            return;
        }

        echo '<nav class="pcf-pagination" aria-label="ページネーション">';

        if ($currentPage > 1) {
            $prevParams = $params;
            $prevParams['page'] = $currentPage - 1;
            echo '<a class="pcf-pagination__link" href="' . e($baseUrl . '?' . http_build_query($prevParams)) . '">前へ</a>';
        }

        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);

        if ($start > 1) {
            $firstParams = $params;
            $firstParams['page'] = 1;
            echo '<a class="pcf-pagination__link" href="' . e($baseUrl . '?' . http_build_query($firstParams)) . '">1</a>';
            if ($start > 2) {
                echo '<span class="pcf-pagination__ellipsis">…</span>';
            }
        }

        for ($p = $start; $p <= $end; $p++) {
            if ($p === $currentPage) {
                echo '<span class="pcf-pagination__current" aria-current="page">' . $p . '</span>';
            } else {
                $linkParams = $params;
                $linkParams['page'] = $p;
                echo '<a class="pcf-pagination__link" href="' . e($baseUrl . '?' . http_build_query($linkParams)) . '">' . $p . '</a>';
            }
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                echo '<span class="pcf-pagination__ellipsis">…</span>';
            }
            $lastParams = $params;
            $lastParams['page'] = $totalPages;
            echo '<a class="pcf-pagination__link" href="' . e($baseUrl . '?' . http_build_query($lastParams)) . '">' . $totalPages . '</a>';
        }

        if ($currentPage < $totalPages) {
            $nextParams = $params;
            $nextParams['page'] = $currentPage + 1;
            echo '<a class="pcf-pagination__link" href="' . e($baseUrl . '?' . http_build_query($nextParams)) . '">次へ</a>';
        }

        echo '</nav>';
    }
}

if (!function_exists('pcf_render_sample_movie_modal')) {
    function pcf_render_sample_movie_modal(): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        echo '<div id="sample-movie-modal" class="sample-movie-modal" aria-hidden="true">';
        echo '<div class="sample-movie-modal__overlay" data-movie-close="1"></div>';
        echo '<div class="sample-movie-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sample-movie-title">';
        echo '<button type="button" class="sample-movie-modal__close" data-movie-close="1" aria-label="閉じる">×</button>';
        echo '<div id="sample-movie-title" class="sample-movie-modal__title">サンプル動画</div>';
        echo '<div class="sample-movie-modal__frame-wrap">';
        echo '<iframe id="sample-movie-frame" class="sample-movie-modal__frame" src="about:blank" allow="autoplay; fullscreen" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-forms allow-presentation" title="サンプル動画プレイヤー"></iframe>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<script>';
        echo '(() => {';
        echo 'const modal = document.getElementById("sample-movie-modal");';
        echo 'const frame = document.getElementById("sample-movie-frame");';
        echo 'const titleNode = document.getElementById("sample-movie-title");';
        echo 'const closeButton = modal ? modal.querySelector("[data-movie-close=\'1\']") : null;';
        echo 'if (!modal || !frame || !titleNode || modal.dataset.bound === "1") return;';
        echo 'modal.dataset.bound = "1";';
        echo 'let returnFocus = null;';
        echo 'const allowedRoots = ["dmm.co.jp", "dmm.com", "fanza.co.jp"];';
        echo 'const allowedMovieUrl = (value) => { try { const parsed = new URL(String(value || ""), window.location.href); const host = (parsed.hostname || "").toLowerCase().replace(/\\.$/, ""); return /^https?:$/.test(parsed.protocol) && allowedRoots.some((root) => host === root || host.endsWith("." + root)) ? parsed.href : ""; } catch (_) { return ""; } };';
        echo 'const openMovie = (url, title, trigger) => { const safeUrl = allowedMovieUrl(url); if (!safeUrl) { console.warn("sample movie URL rejected"); return; } returnFocus = trigger instanceof HTMLElement ? trigger : null; titleNode.textContent = String(title || "").trim() || "サンプル動画"; modal.style.setProperty("--movie-modal-width", "900px"); frame.src = safeUrl; modal.classList.add("is-open"); modal.setAttribute("aria-hidden", "false"); if (closeButton instanceof HTMLElement) closeButton.focus(); };';
        echo 'const closeMovie = () => { modal.classList.remove("is-open"); modal.setAttribute("aria-hidden", "true"); frame.src = "about:blank"; modal.style.removeProperty("--movie-modal-width"); titleNode.textContent = "サンプル動画"; if (returnFocus instanceof HTMLElement) { returnFocus.focus(); returnFocus = null; } };';
        echo 'document.addEventListener("click", (event) => { if (!(event.target instanceof Element)) return; const trigger = event.target.closest(".sample-movie-trigger, [data-sample-movie-url]"); if (trigger && !trigger.disabled) { event.preventDefault(); const title = trigger.dataset.movieTitle || trigger.dataset.sampleMovieTitle || ""; const url = trigger.dataset.movieUrl || trigger.dataset.sampleMovieUrl || ""; openMovie(url, title, trigger); return; } if (event.target.closest("[data-movie-close=\'1\']")) { event.preventDefault(); closeMovie(); } });';
        echo 'document.addEventListener("keydown", (event) => { if (event.key === "Escape" && modal.classList.contains("is-open")) closeMovie(); });';
        echo '})();';
        echo '</script>';
    }
}

if (!function_exists('pcf_render_entity_access_ranking')) {
    function pcf_render_entity_access_ranking(
        string $title,
        array $tabs,
        string $activePeriod,
        callable $tabUrlBuilder,
        array $rows,
        callable $rowUrlBuilder,
        string $emptyMessage,
        string $description = '各ページへのアクセスをもとに集計しています。'
    ): void {
        $isMultiPeriod = false;
        foreach (array_keys($tabs) as $k) {
            if (array_key_exists((string)$k, $rows)) {
                $isMultiPeriod = true;
                break;
            }
        }

        $formattedByPeriod = [];
        if ($isMultiPeriod) {
            foreach ($tabs as $tabKey => $tabConfig) {
                $periodKey = (string)$tabKey;
                $periodRows = is_array($rows[$periodKey] ?? null) ? $rows[$periodKey] : [];
                $displayRows = [];
                foreach ($periodRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $name = trim((string)($row['name'] ?? ''));
                    $url = trim((string)$rowUrlBuilder($row));
                    if ($name === '' || $url === '') {
                        continue;
                    }
                    $displayRows[] = [
                        'name' => $name,
                        'url' => $url,
                        'score' => max(0, (int)($row['access_count'] ?? 0)),
                    ];
                }
                $formattedByPeriod[$periodKey] = $displayRows;
            }
        } else {
            $displayRows = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = trim((string)($row['name'] ?? ''));
                $url = trim((string)$rowUrlBuilder($row));
                if ($name === '' || $url === '') {
                    continue;
                }
                $displayRows[] = [
                    'name' => $name,
                    'url' => $url,
                    'score' => max(0, (int)($row['access_count'] ?? 0)),
                ];
            }
            $formattedByPeriod[$activePeriod] = $displayRows;
        }

        echo '<section id="access-ranking" class="block pcf-item-ranking">';
        echo '<div class="pcf-item-ranking__heading"><div>';
        echo '<p class="pcf-item-ranking__eyebrow">ACCESS RANKING</p>';
        echo '<h2 class="section-title">' . e($title) . '</h2>';
        echo '</div><p class="pcf-item-ranking__description">' . e($description) . '</p></div>';
        echo '<nav class="pcf-item-ranking__tabs" aria-label="ランキング期間">';
        foreach ($tabs as $tabKey => $tabConfig) {
            $period = (string)$tabKey;
            $label = is_array($tabConfig) ? trim((string)($tabConfig['label'] ?? '')) : '';
            if ($label === '') {
                continue;
            }
            $isActive = $activePeriod === $period;
            echo '<button type="button" class="pcf-item-ranking__tab' . ($isActive ? ' is-active' : '') . '"' . ($isActive ? ' aria-current="page"' : '') . ' data-rank-period="' . e($period) . '">' . e($label) . '</button>';
        }
        echo '</nav>';

        foreach ($tabs as $tabKey => $tabConfig) {
            $period = (string)$tabKey;
            $isActive = $activePeriod === $period;
            $periodDisplayRows = $formattedByPeriod[$period] ?? [];
            $displayStyle = $isActive ? '' : ' style="display:none;"';
            echo '<div class="pcf-item-ranking__panel" data-rank-panel="' . e($period) . '"' . $displayStyle . '>';
            if ($periodDisplayRows === []) {
                pcf_render_empty($emptyMessage);
            } else {
                echo '<ol class="pcf-item-ranking__list">';
                foreach ($periodDisplayRows as $index => $row) {
                    echo '<li class="pcf-item-ranking__row' . ($index < 3 ? ' is-top' : '') . '">';
                    echo '<span class="pcf-item-ranking__position">' . e((string)($index + 1)) . '</span>';
                    echo '<a class="pcf-item-ranking__title" href="' . e((string)$row['url']) . '">' . e((string)$row['name']) . '</a>';
                    echo '<span class="pcf-item-ranking__metrics"><span>ランキング点</span><strong>' . e((string)$row['score']) . ' pt</strong></span>';
                    echo '</li>';
                }
                echo '</ol>';
            }
            echo '</div>';
        }

        echo '<script>(() => {';
        echo 'const s = document.getElementById("access-ranking"); if (!s) return;';
        echo 'const tabs = s.querySelectorAll(".pcf-item-ranking__tab[data-rank-period]");';
        echo 'const panels = s.querySelectorAll("[data-rank-panel]");';
        echo 'if (!tabs.length || !panels.length) return;';
        echo 'tabs.forEach((tab) => {';
        echo 'tab.addEventListener("click", (e) => {';
        echo 'e.preventDefault();';
        echo 'const p = tab.getAttribute("data-rank-period");';
        echo 'tabs.forEach((t) => {';
        echo 'const active = t === tab;';
        echo 't.classList.toggle("is-active", active);';
        echo 'if (active) { t.setAttribute("aria-current", "page"); } else { t.removeAttribute("aria-current"); }';
        echo '});';
        echo 'panels.forEach((panel) => {';
        echo 'panel.style.display = panel.getAttribute("data-rank-panel") === p ? "" : "none";';
        echo '});';
        echo '});';
        echo '});';
        echo '})();</script>';

        echo '</section>';
    }
}

if (!function_exists('pcf_render_empty')) {
    function pcf_render_empty(string $message): void
    {
        echo '<div class="pcf-empty-state">';
        echo '<p class="pcf-empty-state__text">' . e($message) . '</p>';
        echo '</div>';
    }
}

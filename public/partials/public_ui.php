<?php
declare(strict_types=1);

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
        $title = trim((string)($item['title'] ?? ''));
        $packageImage = pcf_pick_card_image($item);
        $affiliateUrl = pcf_out_url($id, (string)($options['position'] ?? 'card'));
        $detailUrl = public_url('item.php') . '?id=' . rawurlencode((string)$id);

        $dateReleased = trim((string)($item['date_released'] ?? ''));
        $price = (int)($item['price'] ?? 0);

        $sampleMovieUrl = '';
        if (!empty($item['sample_movie_url'])) {
            $sampleMovieUrl = (string)$item['sample_movie_url'];
        } elseif (!empty($item['sample_movie_url_pc'])) {
            $sampleMovieUrl = (string)$item['sample_movie_url_pc'];
        }

        echo '<article class="pcf-card" data-item-id="' . e((string)$id) . '">';
        echo '<div class="pcf-card__thumb-wrap">';
        echo '<a href="' . e($detailUrl) . '" class="pcf-card__thumb-link" aria-label="' . e($title) . 'の詳細へ">';
        if ($packageImage !== '') {
            echo '<img class="pcf-card__thumb" src="' . e($packageImage) . '" alt="' . e($title) . '" loading="lazy" decoding="async">';
        } else {
            echo '<div class="pcf-card__no-thumb">NO IMAGE</div>';
        }
        echo '</a>';

        if ($sampleMovieUrl !== '') {
            echo '<button type="button" class="pcf-card__quick-play" data-sample-movie-url="' . e($sampleMovieUrl) . '" data-sample-movie-title="' . e($title) . '" aria-label="サンプル動画を再生">';
            echo '▶ 再生';
            echo '</button>';
        }

        echo '</div>';

        echo '<div class="pcf-card__body">';
        echo '<h3 class="pcf-card__title">';
        echo '<a href="' . e($detailUrl) . '">' . e($title) . '</a>';
        echo '</h3>';

        echo '<div class="pcf-card__meta">';
        if ($dateReleased !== '' && $dateReleased !== '0000-00-00') {
            echo '<span class="pcf-card__date">' . e($dateReleased) . '</span>';
        }
        if ($price > 0) {
            echo '<span class="pcf-card__price">¥' . number_format($price) . '〜</span>';
        }
        echo '</div>';

        echo '<div class="pcf-card__actions">';
        echo '<a href="' . e($detailUrl) . '" class="pcf-card__btn pcf-card__btn--detail">詳細</a>';
        echo '<a href="' . e($affiliateUrl) . '" target="_blank" rel="nofollow noopener noreferrer" class="pcf-card__btn pcf-card__btn--fanza">FANZA</a>';
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
        echo '<div id="sample-movie-modal" class="sample-movie-modal" aria-hidden="true" style="display:none;">';
        echo '<div class="sample-movie-modal__overlay" data-movie-close="1"></div>';
        echo '<div class="sample-movie-modal__content" role="dialog" aria-modal="true">';
        echo '<button type="button" class="sample-movie-modal__close" data-movie-close="1" aria-label="閉じる">×</button>';
        echo '<div class="sample-movie-modal__player-wrap">';
        echo '<video id="sample-movie-player" controls playsinline preload="none"></video>';
        echo '</div>';
        echo '<p id="sample-movie-title" class="sample-movie-modal__title"></p>';
        echo '</div>';
        echo '</div>';
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

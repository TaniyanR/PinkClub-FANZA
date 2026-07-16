(() => {
  'use strict';

  const STORAGE_KEY = 'pcf_recently_viewed_v1';
  const MAX_STORED = 20;
  const MAX_RENDERED = 10;

  const storageAvailable = () => {
    try {
      const testKey = '__pcf_storage_test__';
      localStorage.setItem(testKey, '1');
      localStorage.removeItem(testKey);
      return true;
    } catch (_) {
      return false;
    }
  };

  if (!storageAvailable()) return;

  const safeUrl = (value) => {
    try {
      const url = new URL(String(value || ''), window.location.origin);
      return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
    } catch (_) {
      return '';
    }
  };

  const readHistory = () => {
    try {
      const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      if (!Array.isArray(parsed)) return [];
      return parsed.filter((entry) => entry && Number.isInteger(entry.id) && entry.id > 0);
    } catch (_) {
      return [];
    }
  };

  const writeHistory = (history) => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(0, MAX_STORED)));
      return true;
    } catch (_) {
      return false;
    }
  };

  const idsFromLinks = (filename) => {
    const ids = [];
    document.querySelectorAll(`a[href*="${filename}?id="]`).forEach((link) => {
      try {
        const id = Number.parseInt(new URL(link.href, window.location.origin).searchParams.get('id') || '', 10);
        if (Number.isInteger(id) && id > 0 && !ids.includes(id)) ids.push(id);
      } catch (_) {}
    });
    return ids.slice(0, 10);
  };

  const recordCurrentItem = () => {
    const path = window.location.pathname;
    if (!path.endsWith('/item.php') && !path.endsWith('item.php')) return;

    const id = Number.parseInt(new URLSearchParams(window.location.search).get('id') || '', 10);
    if (!Number.isInteger(id) || id <= 0) return;

    const rawTitle = document.querySelector('meta[property="og:title"]')?.content
      || document.querySelector('h1')?.textContent
      || document.title;
    const title = String(rawTitle || '').replace(/\s*\|\s*PinkClub.*$/i, '').trim();
    if (!title) return;

    const image = safeUrl(document.querySelector('meta[property="og:image"]')?.content || '');
    const url = safeUrl(window.location.href);
    if (!url) return;

    const previous = readHistory();
    const existing = previous.find((entry) => entry.id === id);
    const now = Date.now();
    const record = {
      version: 1,
      id,
      title: title.slice(0, 300),
      image,
      url,
      viewedAt: now,
      viewCount: Math.min(999, Number(existing?.viewCount || 0) + 1),
      actresses: idsFromLinks('actress.php'),
      genres: idsFromLinks('genre.php'),
      makers: idsFromLinks('maker.php'),
      series: idsFromLinks('series_detail.php').concat(idsFromLinks('series_one.php')).slice(0, 10)
    };

    writeHistory([record, ...previous.filter((entry) => entry.id !== id)]);
  };

  const createElement = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };

  const renderHistory = () => {
    const section = document.getElementById('pcf-recently-viewed');
    const list = document.getElementById('pcf-recent-list');
    const clearButton = document.getElementById('pcf-recent-clear');
    if (!section || !list || !clearButton) return;

    const history = readHistory().slice(0, MAX_RENDERED);
    list.replaceChildren();

    if (history.length === 0) {
      section.hidden = true;
      return;
    }

    history.forEach((entry) => {
      const article = createElement('article', 'pcf-recent__card');
      const imageLink = createElement('a');
      imageLink.href = safeUrl(entry.url) || '#';
      imageLink.setAttribute('aria-label', entry.title);

      if (entry.image) {
        const image = createElement('img', 'pcf-recent__card-image');
        image.src = safeUrl(entry.image);
        image.alt = entry.title;
        image.loading = 'lazy';
        image.decoding = 'async';
        imageLink.appendChild(image);
      } else {
        const noImage = createElement('div', 'pcf-recent__card-image', '画像なし');
        noImage.style.display = 'grid';
        noImage.style.placeItems = 'center';
        imageLink.appendChild(noImage);
      }

      const titleLink = createElement('a', 'pcf-recent__card-title', entry.title);
      titleLink.href = safeUrl(entry.url) || '#';

      const actions = createElement('div', 'pcf-recent__card-actions');
      const openLink = createElement('a', 'pcf-recent__open', 'もう一度見る');
      openLink.href = safeUrl(entry.url) || '#';
      const removeButton = createElement('button', 'pcf-recent__remove', '削除');
      removeButton.type = 'button';
      removeButton.dataset.recentRemoveId = String(entry.id);
      removeButton.setAttribute('aria-label', `${entry.title}を履歴から削除`);

      actions.append(openLink, removeButton);
      article.append(imageLink, titleLink, actions);
      list.appendChild(article);
    });

    section.hidden = false;

    list.querySelectorAll('[data-recent-remove-id]').forEach((button) => {
      button.addEventListener('click', () => {
        const id = Number.parseInt(button.dataset.recentRemoveId || '', 10);
        writeHistory(readHistory().filter((entry) => entry.id !== id));
        renderHistory();
      });
    });

    clearButton.onclick = () => {
      if (!window.confirm('閲覧履歴をすべて削除しますか？')) return;
      localStorage.removeItem(STORAGE_KEY);
      renderHistory();
    };
  };

  recordCurrentItem();
  renderHistory();
  window.addEventListener('storage', (event) => {
    if (event.key === STORAGE_KEY) renderHistory();
  });
})();

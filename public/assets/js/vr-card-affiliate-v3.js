(() => {
  'use strict';

  const VR_TITLE_PATTERN = /(?:【|\[|［)?\s*VR\s*(?:】|\]|］)?/i;

  const getItemLink = (card) => Array.from(card.querySelectorAll('a[href]')).find((link) => {
    try {
      const url = new URL(link.href, window.location.href);
      return /\/item\.php$/i.test(url.pathname) && /^\d+$/.test(url.searchParams.get('id') || '');
    } catch (_) {
      return false;
    }
  });

  const getTitle = (card) => {
    const node = card.querySelector('.rail-card__title, .pcf-dm-card__title, h2, h3, h4');
    return (node?.textContent || '').trim();
  };

  const getMovieControl = (card) => Array.from(card.querySelectorAll('button, span, a'))
    .find((node) => (node.textContent || '').trim() === 'サンプル動画');

  const affiliateUrl = (itemLink) => {
    const itemUrl = new URL(itemLink.href, window.location.href);
    const itemId = itemUrl.searchParams.get('id') || '';
    itemUrl.pathname = itemUrl.pathname.replace(/item\.php$/i, 'vr_affiliate.php');
    itemUrl.search = '';
    itemUrl.searchParams.set('id', itemId);
    itemUrl.hash = '';
    return itemUrl.toString();
  };

  const convertCard = (card) => {
    if (card.dataset.vrAffiliateReady === '1') {
      return;
    }

    const title = getTitle(card);
    if (!VR_TITLE_PATTERN.test(title)) {
      return;
    }

    const itemLink = getItemLink(card);
    const movieControl = getMovieControl(card);
    if (!itemLink || !movieControl) {
      return;
    }

    const link = document.createElement('a');
    link.className = movieControl.className
      .replace(/\bis-disabled\b/g, '')
      .replace(/\bsample-button--disabled\b/g, '')
      .trim();
    link.classList.add('sample-button--enabled');
    link.href = affiliateUrl(itemLink);
    link.target = '_blank';
    link.rel = 'noopener noreferrer sponsored';
    link.textContent = '元サイトで見る';
    link.setAttribute('aria-label', `${title}をFANZAで見る`);

    if (movieControl instanceof HTMLButtonElement) {
      movieControl.disabled = false;
    }
    movieControl.replaceWith(link);
    card.dataset.vrAffiliateReady = '1';
  };

  const scan = (root = document) => {
    root.querySelectorAll?.('a[href*="item.php?id="]').forEach((itemLink) => {
      const card = itemLink.closest('.rail-card, .pcf-dm-card, article, li, [class*="card"]');
      if (card) {
        convertCard(card);
      }
    });
  };

  scan();

  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      for (const node of mutation.addedNodes) {
        if (node instanceof Element) {
          scan(node);
          const card = node.closest?.('.rail-card, .pcf-dm-card, article, li, [class*="card"]');
          if (card) {
            convertCard(card);
          }
        }
      }
    }
  });

  observer.observe(document.body, { childList: true, subtree: true });
})();

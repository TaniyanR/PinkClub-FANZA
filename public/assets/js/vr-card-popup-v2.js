(() => {
  'use strict';

  const VR_TITLE_PATTERN = /(?:【|\[|［)?\s*VR\s*(?:】|\]|］)?/i;

  const itemLinkFromCard = (card) => Array.from(card.querySelectorAll('a[href]')).find((link) => {
    try {
      const url = new URL(link.href, window.location.href);
      return /\/item\.php$/i.test(url.pathname) && /^\d+$/.test(url.searchParams.get('id') || '');
    } catch (_) {
      return false;
    }
  });

  const cardTitle = (card) => {
    const titleNode = card.querySelector(
      '.rail-card__title, .pcf-dm-card__title, [class*="card"][class*="title"], h2, h3, h4'
    );
    return (titleNode?.textContent || '').trim();
  };

  const movieControl = (card) => Array.from(card.querySelectorAll('button, span, a'))
    .find((element) => (element.textContent || '').trim() === 'サンプル動画');

  const playerUrlFromCard = (card) => {
    const itemLink = itemLinkFromCard(card);
    if (!itemLink) {
      return '';
    }

    try {
      const itemUrl = new URL(itemLink.href, window.location.href);
      const itemId = itemUrl.searchParams.get('id') || '';
      const playerUrl = new URL('vr_sample.php', itemUrl);
      playerUrl.searchParams.set('id', itemId);
      return playerUrl.toString();
    } catch (_) {
      return '';
    }
  };

  const enableCard = (card) => {
    if (card.dataset.vrCardPopupV2 === '1') {
      return;
    }

    const title = cardTitle(card);
    if (!VR_TITLE_PATTERN.test(title)) {
      return;
    }

    const currentControl = movieControl(card);
    const playerUrl = playerUrlFromCard(card);
    if (!currentControl || !playerUrl) {
      return;
    }

    let button = currentControl;
    if (!(currentControl instanceof HTMLButtonElement)) {
      button = document.createElement('button');
      button.type = 'button';
      button.className = currentControl.className;
      button.textContent = 'サンプル動画';
      currentControl.replaceWith(button);
    }

    button.disabled = false;
    button.classList.remove('is-disabled', 'sample-button--disabled');
    button.classList.add('sample-movie-trigger');
    if (button.classList.contains('sample-button')) {
      button.classList.add('sample-button--enabled');
    }
    button.dataset.vrPopupV2 = '1';
    button.dataset.movieUrl = playerUrl;
    button.dataset.movieTitle = title;
    button.setAttribute('aria-label', `${title}のFANZA公式VRサンプルを別ウィンドウで再生する`);
    card.dataset.vrCardPopupV2 = '1';
  };

  const scan = (root = document) => {
    const cards = new Set();
    root.querySelectorAll?.('a[href*="item.php?id="]').forEach((link) => {
      const card = link.closest('.rail-card, .pcf-dm-card, article, li, [class*="card"]');
      if (card) {
        cards.add(card);
      }
    });
    cards.forEach(enableCard);
  };

  const openPopup = (url) => {
    const availableWidth = Math.max(640, window.screen.availWidth || 1200);
    const availableHeight = Math.max(480, window.screen.availHeight || 800);
    const width = Math.min(1200, Math.max(720, availableWidth - 120));
    const height = Math.min(800, Math.max(540, availableHeight - 120));
    const left = Math.max(0, Math.round((availableWidth - width) / 2));
    const top = Math.max(0, Math.round((availableHeight - height) / 2));
    const features = [
      `width=${width}`,
      `height=${height}`,
      `left=${left}`,
      `top=${top}`,
      'resizable=yes',
      'scrollbars=yes',
    ].join(',');

    const popup = window.open(url, 'fanzaVrSamplePlayer', features);
    if (popup) {
      popup.focus();
    }
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-vr-popup-v2="1"]');
    if (!trigger || trigger.disabled) {
      return;
    }

    const url = trigger.dataset.movieUrl || '';
    if (!url) {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    openPopup(url);
  }, true);

  scan();
  window.addEventListener('pageshow', () => scan());

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node instanceof Element) {
          scan(node);
        }
      });
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });
})();

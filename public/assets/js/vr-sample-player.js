(() => {
  'use strict';

  const VR_TITLE_PATTERN = /(?:【|\[|［)?\s*VR\s*(?:】|\]|］)?/i;
  const processedCards = new WeakSet();

  const itemLinkFromCard = (card) => Array.from(card.querySelectorAll('a[href]')).find((link) => {
    try {
      const url = new URL(link.href, window.location.href);
      return /\/item\.php$/i.test(url.pathname) && /^\d+$/.test(url.searchParams.get('id') || '');
    } catch (_) {
      return false;
    }
  });

  const localPlayerUrlFromCard = (card) => {
    const itemLink = itemLinkFromCard(card);
    if (!itemLink) {
      return '';
    }

    try {
      const itemUrl = new URL(itemLink.href, window.location.href);
      const itemId = itemUrl.searchParams.get('id') || '';
      itemUrl.pathname = itemUrl.pathname.replace(/item\.php$/i, 'vr_sample.php');
      itemUrl.search = '';
      itemUrl.searchParams.set('id', itemId);
      itemUrl.hash = '';
      return itemUrl.toString();
    } catch (_) {
      return '';
    }
  };

  const cardTitle = (card) => {
    const titleNode = card.querySelector(
      '.rail-card__title, .pcf-dm-card__title, [class*="card"][class*="title"], h2, h3, h4'
    );
    return (titleNode?.textContent || '').trim();
  };

  const findMovieControl = (card) => Array.from(card.querySelectorAll('button, span, a'))
    .find((element) => (element.textContent || '').trim() === 'サンプル動画');

  const enableMovieControl = (currentControl, playerUrl, title) => {
    let movieButton = currentControl;
    if (!(currentControl instanceof HTMLButtonElement)) {
      movieButton = document.createElement('button');
      movieButton.type = 'button';
      movieButton.className = currentControl.className.replace(/\bis-disabled\b/g, '').trim();
      movieButton.textContent = 'サンプル動画';
      currentControl.replaceWith(movieButton);
    }

    movieButton.disabled = false;
    movieButton.classList.remove('is-disabled', 'sample-button--disabled');
    if (movieButton.classList.contains('sample-button')) {
      movieButton.classList.add('sample-button--enabled');
    }
    movieButton.classList.add('sample-movie-trigger');
    movieButton.dataset.movieUrl = playerUrl;
    movieButton.dataset.movieTitle = title;
    movieButton.dataset.vrPopupPlayer = '1';
    movieButton.setAttribute('aria-label', `${title}のFANZA公式VRサンプルを別ウィンドウで再生する`);
  };

  const inspectVrCard = (card) => {
    if (processedCards.has(card)) {
      return;
    }
    processedCards.add(card);

    const title = cardTitle(card);
    if (!VR_TITLE_PATTERN.test(title)) {
      return;
    }

    const currentControl = findMovieControl(card);
    const playerUrl = localPlayerUrlFromCard(card);
    if (!currentControl || !playerUrl) {
      return;
    }

    enableMovieControl(currentControl, playerUrl, title);
  };

  const findCandidateCards = (root = document) => {
    const cards = new Set();
    root.querySelectorAll?.('a[href*="item.php?id="]').forEach((link) => {
      const card = link.closest('.rail-card, .pcf-dm-card, article, li, [class*="card"]');
      if (card) {
        cards.add(card);
      }
    });
    return cards;
  };

  const inspectAllCards = (root = document) => {
    findCandidateCards(root).forEach(inspectVrCard);
  };

  const openVrPopup = (playerUrl) => {
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
      'noopener=yes',
    ].join(',');

    const popup = window.open(playerUrl, 'fanzaVrSamplePlayer', features);
    if (popup) {
      popup.focus();
    }
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.sample-movie-trigger[data-vr-popup-player="1"]');
    if (!trigger || trigger.disabled) {
      return;
    }

    const playerUrl = trigger.dataset.movieUrl || '';
    if (!playerUrl) {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    openVrPopup(playerUrl);
  }, true);

  const enableItemPageButton = () => {
    if (!/\/item\.php$/i.test(window.location.pathname)) {
      return;
    }

    const title = (document.querySelector('h1')?.textContent || document.title || '').trim();
    if (!VR_TITLE_PATTERN.test(title)) {
      return;
    }

    const itemId = new URL(window.location.href).searchParams.get('id') || '';
    if (!/^\d+$/.test(itemId)) {
      return;
    }

    const movieArea = document.querySelector('.pcf-item-sample-movie');
    if (!movieArea || movieArea.querySelector('[data-vr-popup-player="1"]')) {
      return;
    }

    const playerUrl = new URL('vr_sample.php', window.location.href);
    playerUrl.searchParams.set('id', itemId);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'sample-button sample-button--enabled sample-movie-trigger';
    button.dataset.movieUrl = playerUrl.toString();
    button.dataset.movieTitle = title;
    button.dataset.vrPopupPlayer = '1';
    button.textContent = 'サンプル動画';
    movieArea.replaceChildren(button);
  };

  inspectAllCards();
  enableItemPageButton();

  let scheduled = false;
  const observer = new MutationObserver((mutations) => {
    if (scheduled) {
      return;
    }
    scheduled = true;
    window.requestAnimationFrame(() => {
      scheduled = false;
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node instanceof Element) {
            inspectAllCards(node);
          }
        });
      });
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });
})();

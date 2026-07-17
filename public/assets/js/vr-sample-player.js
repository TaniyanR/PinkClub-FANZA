(() => {
  'use strict';

  const VR_TITLE_PATTERN = /(?:【|\[|［)?\s*VR\s*(?:】|\]|］)?/i;
  const CONTENT_ID_PATTERNS = [
    /\/digital\/video\/([^/?#]+)(?:\/|$)/i,
    /[?&](?:cid|id)=([a-z0-9_-]+)/i,
  ];

  const contentIdFromValue = (value) => {
    const text = String(value || '');
    for (const pattern of CONTENT_ID_PATTERNS) {
      const match = text.match(pattern);
      if (match && /^[a-z0-9_-]+$/i.test(match[1])) {
        return match[1];
      }
    }
    return '';
  };

  const officialPlayerUrl = (contentId) =>
    `https://www.dmm.co.jp/digital/-/vr-sample-player/=/cid=${encodeURIComponent(contentId)}/`;

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

  const cardTitle = (card) => (
    card.querySelector('.rail-card__title, .pcf-dm-card__title')?.textContent || ''
  ).trim();

  const findMovieControl = (card) => Array.from(card.querySelectorAll(
    '.sample-movie-trigger, .pcf-dm-card__button.is-disabled'
  )).find((element) => (element.textContent || '').trim() === 'サンプル動画');

  const enableVrCardMovies = () => {
    document.querySelectorAll('.rail-card, .pcf-dm-card').forEach((card) => {
      const title = cardTitle(card);
      if (!VR_TITLE_PATTERN.test(title)) {
        return;
      }

      const currentControl = findMovieControl(card);
      if (!currentControl) {
        return;
      }

      if (currentControl.dataset.vrPopupPlayer === '1') {
        return;
      }

      const playerUrl = localPlayerUrlFromCard(card);
      if (!playerUrl) {
        return;
      }

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
    });
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

  const enableVrItemMovie = () => {
    if (!/\/item\.php$/i.test(window.location.pathname)) {
      return;
    }

    const title = (document.querySelector('h1')?.textContent || document.title || '').trim();
    if (!VR_TITLE_PATTERN.test(title)) {
      return;
    }

    const movieArea = document.querySelector('.pcf-item-sample-movie');
    if (!movieArea || movieArea.querySelector('iframe')) {
      return;
    }

    const imageCandidates = [
      document.querySelector('[data-package-image="1"]')?.currentSrc,
      document.querySelector('[data-package-image="1"]')?.getAttribute('src'),
      document.querySelector('.pcf-item-sample-thumbs img')?.currentSrc,
      document.querySelector('.pcf-item-sample-thumbs img')?.getAttribute('src'),
      document.querySelector('meta[property="og:image"]')?.getAttribute('content'),
    ];

    let contentId = '';
    for (const imageUrl of imageCandidates) {
      contentId = contentIdFromValue(imageUrl);
      if (contentId) {
        break;
      }
    }

    if (!contentId) {
      return;
    }

    const iframe = document.createElement('iframe');
    iframe.title = 'VRサンプル動画プレイヤー';
    iframe.src = officialPlayerUrl(contentId);
    iframe.width = '560';
    iframe.height = '360';
    iframe.scrolling = 'no';
    iframe.allowFullscreen = true;
    iframe.setAttribute('allow', 'xr-spatial-tracking *; gyroscope *; accelerometer *; fullscreen *');
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    iframe.style.display = 'block';
    iframe.style.width = '100%';
    iframe.style.height = '100%';
    iframe.style.border = '0';

    movieArea.replaceChildren(iframe);
    movieArea.style.background = '#000';
    movieArea.style.color = '';
    movieArea.removeAttribute('aria-label');
  };

  const apply = () => {
    enableVrCardMovies();
    enableVrItemMovie();
  };

  apply();
  window.addEventListener('pageshow', apply);
})();

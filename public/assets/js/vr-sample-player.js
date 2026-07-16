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

  const localPlayerUrlFromCard = (card) => {
    const itemLink = Array.from(card.querySelectorAll('a[href]')).find((link) => {
      try {
        const url = new URL(link.href, window.location.href);
        return /\/item\.php$/i.test(url.pathname) && /^\d+$/.test(url.searchParams.get('id') || '');
      } catch (_) {
        return false;
      }
    });

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

  const enableVrCardMovies = (root = document) => {
    root.querySelectorAll?.('.pcf-dm-card').forEach((card) => {
      const titleElement = card.querySelector('.pcf-dm-card__title');
      const title = (titleElement?.textContent || '').trim();
      if (!VR_TITLE_PATTERN.test(title)) {
        return;
      }

      const disabledMovieButton = Array.from(card.querySelectorAll('.pcf-dm-card__button.is-disabled'))
        .find((element) => (element.textContent || '').trim() === 'サンプル動画');
      if (!disabledMovieButton) {
        return;
      }

      const playerUrl = localPlayerUrlFromCard(card);
      if (!playerUrl) {
        return;
      }

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'pcf-dm-card__button sample-movie-trigger';
      button.dataset.movieUrl = playerUrl;
      button.dataset.movieTitle = title;
      button.textContent = 'サンプル動画';
      disabledMovieButton.replaceWith(button);
    });
  };

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

  enableVrCardMovies();
  enableVrItemMovie();
})();

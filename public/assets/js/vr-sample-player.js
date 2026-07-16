(() => {
  'use strict';

  const isItemPage = /\/item\.php$/i.test(window.location.pathname);
  if (!isItemPage) {
    return;
  }

  const title = (document.querySelector('h1')?.textContent || document.title || '').trim();
  if (!/(?:【|\[|［)?\s*VR\s*(?:】|\]|］)?/i.test(title)) {
    return;
  }

  const movieArea = document.querySelector('.pcf-item-sample-movie');
  if (!movieArea || movieArea.querySelector('iframe')) {
    return;
  }

  const imageCandidates = [
    document.querySelector('[data-package-image="1"]')?.getAttribute('src'),
    document.querySelector('.pcf-item-sample-thumbs img')?.getAttribute('src'),
    document.querySelector('meta[property="og:image"]')?.getAttribute('content'),
  ].filter(Boolean);

  let contentId = '';
  for (const imageUrl of imageCandidates) {
    const match = String(imageUrl).match(/\/digital\/video\/([^/]+)\//i);
    if (match && /^[a-z0-9_-]+$/i.test(match[1])) {
      contentId = match[1];
      break;
    }
  }

  if (!contentId) {
    return;
  }

  const iframe = document.createElement('iframe');
  iframe.title = 'VRサンプル動画プレイヤー';
  iframe.src = `https://www.dmm.co.jp/digital/-/vr-sample-player/=/cid=${encodeURIComponent(contentId)}/`;
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
})();

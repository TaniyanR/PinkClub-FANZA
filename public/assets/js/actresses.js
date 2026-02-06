document.addEventListener('DOMContentLoaded', () => {
  const grid = document.querySelector('[data-actresses-grid]');
  if (!grid) return;

  const countEl = document.querySelector('[data-actresses-count]');
  const emptyEl = document.querySelector('[data-actresses-empty]');
  const searchInput = document.querySelector('[data-actresses-search]');
  const sortSelect = document.querySelector('[data-actresses-sort]');
  const perPageSelect = document.querySelector('[data-actresses-per-page]');

  const names = [
    '葵 さくら',
    '宮下 玲奈',
    '天音 まひな',
    '渚 みつき',
    '石原 希',
    '三上 悠亜',
    '篠田 ゆう',
    '川北 メイサ',
    '河北 彩花',
    '桜空 もも',
    '楪 カレン',
    '明里 つむぎ',
    '深田 えいみ',
    '波多野 結衣',
    '夢乃 あいか',
    '相沢 みなみ',
    '新名 あみん',
    '水川 スミレ',
    '瀬名 ひかり',
    '鈴村 あいり',
    '小野 六花',
    '羽咲 みはる',
    '美谷 朱里',
    '乙白 さやか',
  ];

  // 48件まで出せるようにダミーを拡張（表示件数48に対応）
  const actresses = Array.from({ length: 48 }, (_, i) => {
    const baseName = names[i % names.length];
    const suffix = i >= names.length ? ` ${i + 1}` : '';
    return {
      id: i + 1,
      name: `${baseName}${suffix}`,
      popularity: 48 - i,
      image: `https://picsum.photos/seed/actress-${i + 1}/400/540`,
      url: '#',
    };
  });

  const state = {
    query: '',
    sort: 'popular', // popular | new
    perPage: perPageSelect ? Number(perPageSelect.value) : 24,
  };

  const buildCard = (actress) => {
    const card = document.createElement('article');
    card.className = 'actress-card';

    // 画像リンク（CSSでは .actress-card-media がリンク扱い）
    const mediaLink = document.createElement('a');
    mediaLink.href = actress.url;
    mediaLink.className = 'actress-card-media';

    const img = document.createElement('img');
    img.src = actress.image;
    img.alt = actress.name;
    img.loading = 'lazy';
    img.decoding = 'async';
    img.addEventListener('error', () => img.remove()); // 失敗時は背景だけ残す

    mediaLink.appendChild(img);

    // 名前リンク
    const nameLink = document.createElement('a');
    nameLink.href = actress.url;
    nameLink.className = 'actress-name';
    nameLink.textContent = actress.name;

    card.appendChild(mediaLink);
    card.appendChild(nameLink);

    return card;
  };

  const render = () => {
    const query = state.query.trim().toLowerCase();

    let filtered = actresses.filter((a) => a.name.toLowerCase().includes(query));

    filtered.sort((a, b) => {
      if (state.sort === 'new') return b.id - a.id;
      return b.popularity - a.popularity;
    });

    const visible = filtered.slice(0, state.perPage);

    grid.innerHTML = '';
    visible.forEach((a) => grid.appendChild(buildCard(a)));

    if (countEl) countEl.textContent = String(visible.length);
    if (emptyEl) emptyEl.hidden = visible.length !== 0;
  };

  // init state from controls
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      state.query = e.target.value || '';
      render();
    });
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', (e) => {
      state.sort = e.target.value || 'popular';
      render();
    });
  }

  if (perPageSelect) {
    perPageSelect.addEventListener('change', (e) => {
      state.perPage = Number(e.target.value) || 24;
      render();
    });
  }

  render();
});

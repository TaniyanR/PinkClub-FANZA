const grid = document.querySelector('[data-list-grid]');
const countEl = document.querySelector('[data-list-count]');

if (grid && countEl) {
    const items = Array.from({ length: 24 }, (_, index) => ({
        id: index + 1,
        title: `サンプル作品タイトル ${index + 1}｜魅力が伝わる長めのダミータイトル`,
        image: `https://picsum.photos/seed/fanza-${index + 10}/640/480`,
    }));

    countEl.textContent = `${items.length}件`;

    items.forEach((item) => {
        const card = document.createElement('article');
        card.className = 'product-card';

        card.innerHTML = `
            <a class="card-media" href="#" aria-label="${item.title}">
                <img src="${item.image}" alt="${item.title}">
            </a>
            <div class="card-body">
                <a class="card-title" href="#">${item.title}</a>
                <div class="card-actions">
                    <a class="card-btn" href="#">サンプル動画</a>
                    <a class="card-btn" href="#">サンプル画像</a>
                    <a class="card-link" href="https://example.com" target="_blank" rel="noopener">FANZAで購入</a>
                </div>
            </div>
        `;

        const img = card.querySelector('img');
        const media = card.querySelector('.card-media');
        if (img && media) {
            img.addEventListener('error', () => {
                media.classList.add('is-empty');
            });
        }

        grid.appendChild(card);
    });
}

const pageLinks = document.querySelectorAll('.pagination .page-btn');
pageLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
        event.preventDefault();
        window.alert('ページネーションはダミーです。');
    });
});

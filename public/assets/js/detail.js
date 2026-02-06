const packageMedia = {
    title: 'サンプル作品パッケージ',
    image: 'https://picsum.photos/640/960?random=21',
    link: 'https://example.com'
};

const sampleGrid = Array.from({ length: 6 }, (_, index) => ({
    image: `https://picsum.photos/960/540?random=${index + 31}`,
    alt: `サンプル画像${index + 1}`
}));

const relatedGrid = Array.from({ length: 6 }, (_, index) => ({
    title: `関連作品タイトルのダミー ${index + 1}：ときめく夜のストーリー`,
    image: `https://picsum.photos/640/900?random=${index + 81}`,
    link: '/item.php',
    buy: 'https://example.com'
}));

const mountPackageMedia = () => {
    const container = document.querySelector('[data-package-media]');
    if (!container) {
        return;
    }

    container.innerHTML = `
        <a href="${packageMedia.link}" target="_blank" rel="noopener noreferrer">
            <img src="${packageMedia.image}" alt="${packageMedia.title}" loading="lazy" data-fallback>
        </a>
    `;
};

const mountSampleGrid = () => {
    const container = document.querySelector('[data-sample-grid]');
    if (!container) {
        return;
    }

    container.innerHTML = sampleGrid
        .map(
            (item) => `
                <div class="sample-card">
                    <img src="${item.image}" alt="${item.alt}" loading="lazy" data-fallback>
                </div>
            `
        )
        .join('');
};

const mountRelatedGrid = () => {
    const container = document.querySelector('[data-related-grid]');
    if (!container) {
        return;
    }

    container.innerHTML = relatedGrid
        .map(
            (item) => `
                <article class="related-card">
                    <div class="related-media">
                        <a href="${item.link}">
                            <img src="${item.image}" alt="${item.title}" loading="lazy" data-fallback>
                        </a>
                    </div>
                    <a href="${item.link}" class="related-title">${item.title}</a>
                    <div class="related-actions">
                        <a class="btn-outline" href="${item.link}">そのページへ</a>
                        <a class="btn-cta" href="${item.buy}" target="_blank" rel="noopener noreferrer">FANZAで購入</a>
                    </div>
                </article>
            `
        )
        .join('');
};

const applyImageFallback = () => {
    document.querySelectorAll('img[data-fallback]').forEach((img) => {
        img.addEventListener('error', () => {
            img.style.display = 'none';
        });
    });
};

mountPackageMedia();
mountSampleGrid();
mountRelatedGrid();
applyImageFallback();

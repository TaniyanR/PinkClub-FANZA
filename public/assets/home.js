const createElement = (tag, className) => {
    const element = document.createElement(tag);
    if (className) {
        element.className = className;
    }
    return element;
};

const buildImage = (src, altText, onError) => {
    if (!src) {
        return null;
    }
    const img = document.createElement('img');
    img.src = src;
    img.alt = altText;
    img.addEventListener('error', () => {
        img.remove();
        if (onError) {
            onError();
        }
    });
    return img;
};

const buildProductCard = (item) => {
    const card = createElement('article', 'product-card');
    const media = createElement('a', 'product-media');
    media.href = item.link;
    const image = buildImage(item.image, item.title, () => media.classList.add('media-empty'));
    if (image) {
        media.appendChild(image);
    }
    const title = createElement('div', 'product-title');
    title.textContent = item.title;

    const actions = createElement('div', 'product-actions');
    const sampleMovie = createElement('a', 'btn btn-outline');
    sampleMovie.href = item.sampleMovie;
    sampleMovie.textContent = 'サンプル動画';
    const sampleImage = createElement('a', 'btn btn-outline');
    sampleImage.href = item.sampleImage;
    sampleImage.textContent = 'サンプル画像';
    const buyLink = createElement('a', 'btn btn-primary');
    buyLink.href = item.link;
    buyLink.textContent = 'FANZAで購入';

    actions.append(sampleMovie, sampleImage, buyLink);

    card.append(media, title, actions);
    return card;
};

const buildActressCard = (item) => {
    const card = createElement('article', 'actress-card');
    const media = createElement('div', 'actress-media');
    const image = buildImage(item.image, item.name, () => media.classList.add('media-empty'));
    if (image) {
        media.appendChild(image);
    }
    const name = createElement('div', 'product-title');
    name.textContent = item.name;
    const button = createElement('a', 'btn btn-outline');
    button.href = item.link;
    button.textContent = '女優ページへ';

    card.append(media, name, button);
    return card;
};

const buildTileCard = (item) => {
    const card = createElement('article', 'tile-card');
    const media = createElement('a', 'tile-media');
    media.href = item.link;
    const image = buildImage(item.image, item.name, () => media.classList.add('media-empty'));
    if (image) {
        media.appendChild(image);
    }
    const title = createElement('div', 'tile-title');
    title.textContent = item.name;
    const button = createElement('a', 'btn btn-outline');
    button.href = item.link;
    button.textContent = 'そのページへ';

    card.append(media, title, button);
    return card;
};

const generateItems = (count, prefix, options = {}) => {
    return Array.from({ length: count }, (_, index) => {
        const number = String(index + 1).padStart(2, '0');
        return {
            title: `${prefix} ${number}`,
            name: `${prefix} ${number}`,
            image: options.noImage && options.noImage(index) ? '' : `https://picsum.photos/seed/${encodeURIComponent(prefix + number)}/600/338`,
            link: '#',
            sampleMovie: '#',
            sampleImage: '#',
        };
    });
};

const shuffle = (array) => {
    const target = [...array];
    for (let i = target.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));
        [target[i], target[j]] = [target[j], target[i]];
    }
    return target;
};

document.addEventListener('DOMContentLoaded', () => {
    const newTop = generateItems(4, '新着');
    const newBottom = generateItems(6, '新着');
    const pickupTop = generateItems(4, 'ピックアップ');
    const pickupBottom = generateItems(6, 'ピックアップ');
    const actresses = generateItems(5, '女優');
    const genreItems = shuffle(generateItems(18, 'ジャンル'));
    const seriesItems = shuffle(generateItems(18, 'シリーズ'));
    const makerItems = shuffle(generateItems(18, 'メーカー', {
        noImage: (index) => index % 4 === 0,
    }));

    const fillGrid = (selector, items, builder) => {
        const container = document.querySelector(selector);
        if (!container) {
            return;
        }
        items.forEach((item) => container.appendChild(builder(item)));
    };

    fillGrid('[data-grid="new-top"]', newTop, buildProductCard);
    fillGrid('[data-grid="new-bottom"]', newBottom, buildProductCard);
    fillGrid('[data-grid="pickup-top"]', pickupTop, buildProductCard);
    fillGrid('[data-grid="pickup-bottom"]', pickupBottom, buildProductCard);
    fillGrid('[data-grid="actress"]', actresses, buildActressCard);

    const fillRows = (selector, items) => {
        const wrapper = document.querySelector(selector);
        if (!wrapper) {
            return;
        }
        for (let rowIndex = 0; rowIndex < 3; rowIndex += 1) {
            const row = createElement('div', 'tile-row');
            const rowItems = items.slice(rowIndex * 6, rowIndex * 6 + 6);
            rowItems.forEach((item) => row.appendChild(buildTileCard(item)));
            wrapper.appendChild(row);
        }
    };

    fillRows('[data-rows="genre"]', genreItems);
    fillRows('[data-rows="series"]', seriesItems);
    fillRows('[data-rows="maker"]', makerItems);
});

const actresses = Array.from({ length: 48 }, (_, index) => {
    const number = index + 1;
    return {
        id: number,
        name: `サンプル女優 ${number}`,
        image: `https://via.placeholder.com/360x480?text=Actress+${number}`,
        url: '#'
    };
});

const grid = document.querySelector('[data-actress-grid]');
const searchInput = document.querySelector('[data-actress-search]');
const limitSelect = document.querySelector('[data-actress-limit]');
const countLabel = document.querySelector('[data-actress-count]');

const buildCard = (actress) => {
    const card = document.createElement('article');
    card.className = 'actress-card';

    const thumbLink = document.createElement('a');
    thumbLink.href = actress.url;
    thumbLink.className = 'actress-thumb';

    const image = document.createElement('img');
    image.src = actress.image;
    image.alt = actress.name;
    image.loading = 'lazy';
    image.addEventListener('error', () => {
        image.remove();
    });

    thumbLink.appendChild(image);

    const nameLink = document.createElement('a');
    nameLink.href = actress.url;
    nameLink.className = 'actress-name';
    nameLink.textContent = actress.name;

    card.appendChild(thumbLink);
    card.appendChild(nameLink);

    return card;
};

const render = () => {
    if (!grid) {
        return;
    }

    const keyword = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const limit = limitSelect ? Number(limitSelect.value) : 24;

    const filtered = actresses.filter((actress) => actress.name.toLowerCase().includes(keyword));
    const visible = filtered.slice(0, limit);

    grid.innerHTML = '';
    visible.forEach((actress) => {
        grid.appendChild(buildCard(actress));
    });

    if (countLabel) {
        countLabel.textContent = String(visible.length);
    }
};

if (searchInput) {
    searchInput.addEventListener('input', render);
}

if (limitSelect) {
    limitSelect.addEventListener('change', render);
}

render();

document.querySelectorAll('.sidebar-actress-thumb img').forEach((image) => {
    image.addEventListener('error', () => {
        image.remove();
    });
});

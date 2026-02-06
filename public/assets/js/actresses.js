document.addEventListener('DOMContentLoaded', () => {
    const grid = document.querySelector('[data-actresses-grid]');
    if (!grid) {
        return;
    }

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
        '乙白 さやか'
    ];

    const actresses = names.map((name, index) => ({
        id: index + 1,
        name,
        popularity: names.length - index,
        image: `https://picsum.photos/seed/actress-${index + 1}/400/540`
    }));

    const state = {
        query: '',
        sort: 'popular',
        perPage: 24
    };

    const buildCard = (actress) => {
        const card = document.createElement('article');
        card.className = 'actress-card';

        const mediaLink = document.createElement('a');
        mediaLink.href = '#';
        mediaLink.className = 'actress-card-media';

        const img = document.createElement('img');
        img.src = actress.image;
        img.alt = actress.name;
        img.loading = 'lazy';
        img.addEventListener('error', () => {
            img.remove();
        });

        mediaLink.appendChild(img);

        const nameLink = document.createElement('a');
        nameLink.href = '#';
        nameLink.className = 'actress-name';
        nameLink.textContent = actress.name;

        card.appendChild(mediaLink);
        card.appendChild(nameLink);

        return card;
    };

    const render = () => {
        const query = state.query.trim().toLowerCase();
        let filtered = actresses.filter((actress) =>
            actress.name.toLowerCase().includes(query)
        );

        filtered = filtered.sort((a, b) => {
            if (state.sort === 'new') {
                return b.id - a.id;
            }
            return b.popularity - a.popularity;
        });

        filtered = filtered.slice(0, state.perPage);

        grid.innerHTML = '';
        filtered.forEach((actress) => {
            grid.appendChild(buildCard(actress));
        });

        if (countEl) {
            countEl.textContent = String(filtered.length);
        }

        if (emptyEl) {
            emptyEl.hidden = filtered.length !== 0;
        }
    };

    if (searchInput) {
        searchInput.addEventListener('input', (event) => {
            state.query = event.target.value;
            render();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', (event) => {
            state.sort = event.target.value;
            render();
        });
    }

    if (perPageSelect) {
        perPageSelect.addEventListener('change', (event) => {
            state.perPage = Number(event.target.value);
            render();
        });
    }

    render();
});

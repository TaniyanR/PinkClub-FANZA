const buildSeriesData = () => {
    const list = [];
    for (let i = 1; i <= 48; i += 1) {
        list.push({
            id: i,
            name: `シリーズタイトル ${i} 〜きらめく夜の物語〜`,
            image: i % 5 === 0 ? "https://example.com/missing.jpg" : `https://picsum.photos/seed/series-${i}/400/300`,
        });
    }
    return list;
};

const createSeriesCard = (series) => {
    const card = document.createElement("article");
    card.className = "series-card";

    const media = document.createElement("div");
    media.className = "series-card__media";

    const fallback = document.createElement("span");
    fallback.className = "series-card__fallback";
    fallback.textContent = series.name;

    if (series.image) {
        const img = document.createElement("img");
        img.alt = series.name;
        img.src = series.image;
        img.addEventListener("load", () => {
            media.classList.add("has-image");
        });
        img.addEventListener("error", () => {
            img.remove();
            media.classList.remove("has-image");
        });
        media.appendChild(img);
    }

    media.appendChild(fallback);

    const body = document.createElement("div");
    body.className = "series-card__body";

    const titleLink = document.createElement("a");
    titleLink.className = "series-card__title";
    titleLink.href = "/series_one.php";
    titleLink.textContent = series.name;

    const button = document.createElement("a");
    button.className = "series-card__button";
    button.href = "/series_one.php";
    button.textContent = "そのページへ";

    body.appendChild(titleLink);
    body.appendChild(button);

    card.appendChild(media);
    card.appendChild(body);

    return card;
};

const filterSeries = (seriesList, query) => {
    if (!query) {
        return seriesList;
    }
    const keyword = query.trim().toLowerCase();
    if (!keyword) {
        return seriesList;
    }
    return seriesList.filter((series) => series.name.toLowerCase().includes(keyword));
};

const sortSeries = (seriesList, mode) => {
    const sorted = [...seriesList];
    if (mode === "new") {
        sorted.reverse();
    }
    return sorted;
};

const renderSeriesGrid = ({ grid, seriesList, count, totalLabel }) => {
    grid.innerHTML = "";
    const sliced = seriesList.slice(0, count);
    sliced.forEach((series) => grid.appendChild(createSeriesCard(series)));
    if (totalLabel) {
        totalLabel.textContent = `${sliced.length}件`;
    }
};

document.addEventListener("DOMContentLoaded", () => {
    const grid = document.querySelector("[data-series-grid]");
    if (!grid) {
        return;
    }

    const seriesList = buildSeriesData();
    const totalLabel = document.querySelector("[data-series-total]");
    const searchInput = document.querySelector("[data-series-search]");
    const sortSelect = document.querySelector("[data-series-sort]");
    const countSelect = document.querySelector("[data-series-count]");

    const update = () => {
        const keyword = searchInput ? searchInput.value : "";
        const mode = sortSelect ? sortSelect.value : "popular";
        const count = countSelect ? Number(countSelect.value) : 24;
        const filtered = filterSeries(seriesList, keyword);
        const sorted = sortSeries(filtered, mode);
        renderSeriesGrid({ grid, seriesList: sorted, count, totalLabel });
    };

    if (searchInput) {
        searchInput.addEventListener("input", update);
    }
    if (sortSelect) {
        sortSelect.addEventListener("change", update);
    }
    if (countSelect) {
        countSelect.addEventListener("change", update);
    }

    update();
});

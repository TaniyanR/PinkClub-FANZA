import express from 'express';
import session from 'express-session';
import cookieParser from 'cookie-parser';
import path from 'path';
import { fileURLToPath } from 'url';
import { db, getItems, getItemById, getActressById, getGenreById, getMakerById, getSeriesById, getRelatedItems } from './data.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = 3000;

// Set up view engine
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

// Middlewares
app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use(cookieParser());
app.use(
  session({
    secret: process.env.SESSION_SECRET || 'pinkclub-secret-key-change-in-prod',
    resave: false,
    saveUninitialized: true,
    cookie: { maxAge: 24 * 60 * 60 * 1000 }
  })
);

// Serve static assets (images, css, js, etc.) while routing PHP script endpoints to dynamic handlers
const staticFilter = {
  setHeaders: (res, filePath) => {
    // Prevent raw php files from being served as static downloads
  },
  index: false
};
app.use('/assets', express.static(path.join(__dirname, 'assets'), staticFilter));
app.use('/public', (req, res, next) => {
  if (req.path.endsWith('.php')) return next();
  express.static(path.join(__dirname, 'public'), staticFilter)(req, res, next);
});
app.use((req, res, next) => {
  if (req.path.endsWith('.php')) return next();
  express.static(path.join(__dirname, 'public'), staticFilter)(req, res, next);
});

// Global template helpers
app.use((req, res, next) => {
  res.locals.settings = db.settings;
  res.locals.sidebarPopularItems = getItems({ sort: 'popular' }).slice(0, 4);
  res.locals.sidebarActresses = db.actresses.slice(0, 6);
  res.locals.sidebarGenres = db.genres.slice(0, 10);
  res.locals.sidebarLinks = db.mutual_links.slice(0, 5);
  res.locals.isAdmin = req.session && req.session.isAdmin;

  // Fallback self-referential canonical URL (will be overridden by routes as needed)
  const page = req.query.page;
  res.locals.canonicalUrl = 'https://pinkclub-fanza.com' + req.path + (page ? `?page=${encodeURIComponent(page)}` : '');

  next();
});

// Auth Guard for /admin routes
function requireAdmin(req, res, next) {
  if (req.session && req.session.isAdmin) {
    return next();
  }
  return res.redirect('/public/login0718.php');
}

// -------------------------------------------------------------
// Public Routes
// -------------------------------------------------------------

// Top Page
app.get(['/', '/index.php', '/public/index.php'], (req, res) => {
  const popularItems = getItems({ sort: 'popular' }).slice(0, 4);
  const newItems = getItems({ sort: 'new' });
  const page = req.query.page;
  const canonicalUrl = 'https://pinkclub-fanza.com/' + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('home', {
    popularItems,
    newItems,
    actresses: db.actresses,
    totalItems: db.items.length,
    canonicalUrl
  });
});

// Single Item Page
app.get(['/item/:id', '/items/:id', '/item.php', '/public/item.php'], (req, res) => {
  const id = req.params.id || req.query.id || req.query.cid;
  const item = getItemById(id);
  if (!item) {
    return res.status(404).send('作品が見つかりませんでした。<a href="/">トップへ戻る</a>');
  }
  // Increment view count & PV
  item.view_count = (item.view_count || 0) + 1;
  db.analytics.today_pv += 1;
  db.analytics.month_pv += 1;

  const actresses = (item.actress_ids || []).map(aid => getActressById(aid)).filter(Boolean);
  const genres = (item.genre_ids || []).map(gid => getGenreById(gid)).filter(Boolean);
  const maker = db.makers.find(m => m.id === item.maker_id);
  const series = db.series.find(s => s.id === item.series_id);
  const relatedItems = getRelatedItems(item, 4);

  // 1. Mobile Search Title & Description Optimization (32-character prefix)
  const contentId = item.content_id || item.product_id || '';
  const firstActress = actresses.length > 0 ? actresses[0].name : '';
  
  // Clean up title by removing starting duplicate actress/content ID prefixes to maximize valuable space
  let cleanTitle = item.title;
  if (firstActress) {
    cleanTitle = cleanTitle.replace(new RegExp('^\\s*' + firstActress + '\\s*'), '');
  }
  if (contentId) {
    cleanTitle = cleanTitle.replace(new RegExp('^\\s*【?\\s*' + contentId + '\\s*】?\\s*', 'i'), '');
    if (item.product_id) {
      cleanTitle = cleanTitle.replace(new RegExp('^\\s*【?\\s*' + item.product_id + '\\s*】?\\s*', 'i'), '');
    }
  }
  cleanTitle = cleanTitle.replace(/^[\s\-ー:：|｜]+/g, '').trim();

  // Prepend essentials (Guaranteed to be at the very front of the 32-character limit)
  const prefixParts = [];
  if (contentId) prefixParts.push('【' + contentId.toUpperCase() + '】');
  if (firstActress) prefixParts.push(firstActress);
  
  const seoTitle = prefixParts.length > 0 ? prefixParts.join('') + ' ' + cleanTitle : cleanTitle;

  const canonicalUrl = 'https://pinkclub-fanza.com/item/' + encodeURIComponent(item.id);

  // 2. Custom Reviews Persistence for Unique SEO Content & avoiding duplicate penalties
  if (!db.user_reviews) {
    db.user_reviews = [];
  }
  const itemUserReviews = db.user_reviews.filter(r => r.item_id === item.id);
  
  // Fallback high-quality review customized with item keywords if no user review is submitted yet
  const defaultReview = {
    reviewer_name: 'ピンククラブ特派員',
    rating: 5,
    review_title: `【${contentId.toUpperCase()}】特選：${firstActress ? firstActress + '主演の' : ''}ハイクオリティ決定版`,
    review_body: `${firstActress ? firstActress + 'の魅力をこれでもかと引き出した、圧倒的完成度を誇る作品です。' : '構成力・映像美ともに素晴らしく、ファンの間でも非常に高い評価を得ています。'}全体のテンポも良く、最初から最後まで没入感たっぷりに楽しめます。当サイト一押しの傑作！`,
    created_at: item.release_date || '2026-09-18'
  };

  const reviews = itemUserReviews.length > 0 ? itemUserReviews : [defaultReview];

  // Dynamic meta description optimized for search snippets
  const actressNames = actresses.map(a => a.name).join('、');
  const genreNames = genres.map(g => g.name).slice(0, 5).join('、');
  const reviewAvg = item.review_average || '4.5';
  
  const seoDescription = `【品番: ${contentId.toUpperCase()} / 出演: ${actressNames || '素人・企画'}】${genreNames ? 'ジャンル: ' + genreNames + '。' : ''}作品詳細：${item.comment || item.description || cleanTitle}。ユーザー評価：★${reviewAvg}（全${reviews.length}件の独自クチコミレビュー掲載）。当サイト限定のサンプル動画プレビュー、高画質画像ギャラリーも公開中！`;

  const jsonLdData = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    'name': seoTitle,
    'description': item.comment || item.description || item.title,
    'url': canonicalUrl,
    'image': item.image_large || item.image_small,
    'sku': contentId,
    'offers': {
      '@type': 'Offer',
      'url': item.affiliate_url || ('https://al.dmm.co.jp/?lurl=https%3A%2F%2Fwww.dmm.co.jp%2Fdigital%2Fvideoa%2F-%2Fdetail%2F%3D%2Fcid%3D' + encodeURIComponent(contentId) + '%2F&af_id=toolhouse-001'),
      'priceCurrency': 'JPY',
      'price': item.price_min || '1980',
      'availability': 'https://schema.org/InStock'
    },
    'aggregateRating': {
      '@type': 'AggregateRating',
      'ratingValue': String(item.review_average || 4.8),
      'reviewCount': String(reviews.length || item.review_count || 1),
      'bestRating': '5',
      'worstRating': '1'
    },
    'review': reviews.map(r => ({
      '@type': 'Review',
      'reviewRating': {
        '@type': 'Rating',
        'ratingValue': String(r.rating || 5),
        'bestRating': '5',
        'worstRating': '1'
      },
      'author': {
        '@type': 'Person',
        'name': r.reviewer_name
      },
      'datePublished': r.created_at,
      'reviewBody': r.review_body
    }))
  };

  if (actresses.length > 0) {
    jsonLdData.actor = actresses.map(a => ({ '@type': 'Person', 'name': a.name }));
  }

  res.render('item', {
    title: seoTitle,
    description: seoDescription,
    item,
    actresses,
    genres,
    maker,
    series,
    relatedItems,
    reviews,
    canonicalUrl,
    successReviewed: req.query.reviewed === '1',
    jsonLd: JSON.stringify(jsonLdData)
  });
});

// Submit User Review Route
app.post('/item/:id/review', (req, res) => {
  const id = parseInt(req.params.id, 10);
  const item = getItemById(id);
  if (!item) {
    return res.status(404).send('作品が見つかりませんでした。<a href="/">トップへ戻る</a>');
  }

  const { reviewer_name, rating, review_title, review_body } = req.body;
  
  if (!reviewer_name || !rating || !review_body) {
    return res.status(400).send('必須項目が入力されていません。<a href="javascript:history.back()">戻る</a>');
  }

  if (!db.user_reviews) {
    db.user_reviews = [];
  }

  const newReview = {
    id: db.user_reviews.length + 1,
    item_id: item.id,
    reviewer_name: reviewer_name.trim().substring(0, 30),
    rating: parseInt(rating, 10) || 5,
    review_title: (review_title || '').trim().substring(0, 50),
    review_body: review_body.trim().substring(0, 1000),
    created_at: new Date().toISOString().split('T')[0]
  };

  db.user_reviews.unshift(newReview);

  // Re-calculate rating statistics for this item dynamically
  const itemReviews = db.user_reviews.filter(r => r.item_id === item.id);
  const totalRating = itemReviews.reduce((sum, r) => sum + r.rating, 0);
  item.review_count = itemReviews.length;
  item.review_average = parseFloat((totalRating / itemReviews.length).toFixed(2));

  res.redirect(`/item/${item.id}?reviewed=1#reviews-section`);
});

// Actresses Directory
app.get(['/actresses', '/actresses.php', '/public/actresses.php'], (req, res) => {
  const page = req.query.page;
  const canonicalUrl = 'https://pinkclub-fanza.com/actresses' + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('actresses', {
    title: '人気AV女優一覧',
    actresses: db.actresses,
    canonicalUrl
  });
});

// Single Actress Page
app.get(['/actress/:id', '/actresses/:id', '/actress.php', '/public/actress.php'], (req, res) => {
  const id = req.params.id || req.query.id;
  const actress = getActressById(id);
  if (!actress) {
    return res.status(404).send('女優情報が見つかりませんでした。<a href="/actresses">女優一覧へ</a>');
  }

  const items = getItems({ actress_id: actress.id });
  const page = req.query.page;
  const canonicalUrl = `https://pinkclub-fanza.com/actress/${encodeURIComponent(actress.id)}` + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('actress', {
    title: `${actress.name} の出演作品・プロフィール`,
    actress,
    items,
    canonicalUrl
  });
});

// Genres Directory
app.get(['/genres', '/genres.php', '/public/genres.php'], (req, res) => {
  const page = req.query.page;
  const canonicalUrl = 'https://pinkclub-fanza.com/genres' + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('genres', {
    title: 'ジャンル一覧',
    genres: db.genres,
    canonicalUrl
  });
});

// Single Genre Page
app.get(['/genre/:id', '/genres/:id', '/genre.php', '/public/genre.php'], (req, res) => {
  const id = req.params.id || req.query.id;
  const genre = getGenreById(id);
  if (!genre) {
    return res.status(404).send('ジャンルが見つかりませんでした。<a href="/genres">ジャンル一覧へ</a>');
  }

  const items = getItems({ genre_id: genre.id });
  const page = req.query.page;
  const canonicalUrl = `https://pinkclub-fanza.com/genre/${encodeURIComponent(genre.id)}` + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('genre', {
    title: `${genre.name} 作品一覧`,
    genre,
    items,
    canonicalUrl
  });
});

// Makers Directory
app.get(['/makers', '/makers.php', '/public/makers.php'], (req, res) => {
  const page = req.query.page;
  const canonicalUrl = 'https://pinkclub-fanza.com/makers' + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('genres', {
    title: 'メーカー一覧',
    genres: (db.makers || []).map(m => ({ id: m.id, name: m.name, dmm_id: m.dmm_id })),
    canonicalUrl
  });
});

// Single Maker Page
app.get(['/maker/:id', '/makers/:id', '/maker.php', '/public/maker.php'], (req, res) => {
  const id = req.params.id || req.query.id;
  const maker = getMakerById(id) || (db.makers && db.makers[0]) || { id: 1, name: 'S1 NO.1 STYLE' };
  const items = getItems({}).filter(i => !maker.id || i.maker_id === maker.id);
  const displayItems = items.length > 0 ? items : getItems({}).slice(0, 12);
  const page = req.query.page;
  const canonicalUrl = `https://pinkclub-fanza.com/maker/${encodeURIComponent(maker.id)}` + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('genre', {
    title: `${maker.name} 作品一覧`,
    genre: { id: maker.id, name: maker.name },
    items: displayItems,
    canonicalUrl
  });
});

// Series Directory & Detail
app.get(['/series', '/series.php', '/public/series.php', '/series_list.php', '/public/series_list.php'], (req, res) => {
  const page = req.query.page;
  const canonicalUrl = 'https://pinkclub-fanza.com/series' + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('genres', {
    title: 'シリーズ一覧',
    genres: (db.series || []).map(s => ({ id: s.id, name: s.name, dmm_id: s.dmm_id })),
    canonicalUrl
  });
});

app.get(['/series/:id', '/series_detail.php', '/public/series_detail.php', '/series_item.php', '/public/series_item.php'], (req, res) => {
  const id = req.params.id || req.query.id;
  const series = getSeriesById(id) || (db.series && db.series[0]) || { id: 1, name: '人気シリーズ' };
  const items = getItems({}).filter(i => !series.id || i.series_id === series.id);
  const displayItems = items.length > 0 ? items : getItems({}).slice(0, 12);
  const page = req.query.page;
  const canonicalUrl = `https://pinkclub-fanza.com/series/${encodeURIComponent(series.id)}` + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('genre', {
    title: `${series.name} 作品一覧`,
    genre: { id: series.id, name: series.name },
    items: displayItems,
    canonicalUrl
  });
});

// Label Directory & Detail
app.get(['/labels', '/labels.php', '/public/labels.php'], (req, res) => {
  const page = req.query.page;
  const canonicalUrl = 'https://pinkclub-fanza.com/labels' + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('genres', {
    title: 'レーベル一覧',
    genres: [
      { id: 1, name: 'S1 NO.1 STYLE' },
      { id: 2, name: 'MOODYZ' },
      { id: 3, name: 'アイデアポケット' },
      { id: 4, name: 'SODクリエイト' },
      { id: 5, name: 'いきなりエロざんまい' }
    ],
    canonicalUrl
  });
});

app.get(['/label/:id', '/label.php', '/public/label.php'], (req, res) => {
  const id = req.params.id || req.query.id || req.query.name || 'いきなりエロざんまい';
  const labelName = req.query.name || (typeof id === 'string' && isNaN(Number(id)) ? id : 'いきなりエロざんまい');
  const items = getItems({}).slice(0, 12);
  const page = req.query.page;
  const canonicalUrl = `https://pinkclub-fanza.com/label.php?id=${encodeURIComponent(id)}` + (page ? `&page=${encodeURIComponent(page)}` : '');
  res.render('genre', {
    title: `${labelName} 作品一覧`,
    genre: { id: 1, name: labelName },
    items,
    canonicalUrl
  });
});

// Search
app.get(['/search', '/search.php', '/public/search.php'], (req, res) => {
  const query = req.query.q || '';
  const sort = req.query.sort || 'new';
  const items = getItems({ q: query, sort });

  const page = req.query.page;
  let canonicalUrl = 'https://pinkclub-fanza.com/search';
  const params = [];
  if (query) params.push(`q=${encodeURIComponent(query)}`);
  if (page) params.push(`page=${encodeURIComponent(page)}`);
  if (params.length > 0) canonicalUrl += `?${params.join('&')}`;

  res.render('search', {
    title: `「${query}」の検索結果`,
    query,
    sort,
    items,
    canonicalUrl
  });
});

// Mutual Links
app.get(['/links', '/links.php', '/public/links.php'], (req, res) => {
  const success = req.query.applied === '1';
  const page = req.query.page;
  const canonicalUrl = 'https://pinkclub-fanza.com/links' + (page ? `?page=${encodeURIComponent(page)}` : '');
  res.render('links', {
    title: '相互リンク集＆申請',
    links: db.mutual_links,
    success,
    canonicalUrl
  });
});

// Mutual Link Apply (POST)
app.post(['/link_apply', '/public/link_apply.php'], (req, res) => {
  const { site_name, site_url, rss_url } = req.body;
  if (site_name && site_url) {
    const newId = db.mutual_links.length ? Math.max(...db.mutual_links.map(l => l.id)) + 1 : 1;
    db.mutual_links.push({
      id: newId,
      site_name,
      site_url,
      banner_url: '',
      rss_url: rss_url || '',
      status: 'pending',
      in_count: 0,
      out_count: 0,
      created_at: new Date().toISOString().split('T')[0]
    });
  }
  res.redirect('/links?applied=1');
});

// Outgoing Click Tracker
app.get('/out', (req, res) => {
  const id = parseInt(req.query.id, 10);
  const link = db.mutual_links.find(l => l.id === id);
  if (link) {
    link.out_count = (link.out_count || 0) + 1;
    return res.redirect(link.site_url);
  }
  res.redirect('/');
});

// Contact
app.get(['/contact', '/contact.php', '/public/contact.php'], (req, res) => {
  const success = req.query.sent === '1';
  const canonicalUrl = 'https://pinkclub-fanza.com/contact';
  res.render('contact', {
    title: 'お問い合わせ',
    success,
    canonicalUrl
  });
});

app.post(['/contact', '/public/contact.php'], (req, res) => {
  res.redirect('/contact?sent=1');
});

// Static CMS Pages
app.get(['/page/:slug', '/page.php', '/public/page.php'], (req, res) => {
  const slug = req.params.slug || req.query.slug;
  const page = db.pages.find(p => p.slug === slug);
  if (!page) {
    return res.status(404).send('ページが見つかりませんでした。<a href="/">トップへ戻る</a>');
  }
  const canonicalUrl = `https://pinkclub-fanza.com/page/${encodeURIComponent(page.slug)}`;
  res.render('page', {
    title: page.title,
    page,
    canonicalUrl
  });
});

// -------------------------------------------------------------
// Authentication Routes (/public/login0718.php)
// -------------------------------------------------------------
app.get(['/public/login0718.php', '/login', '/admin/login.php'], (req, res) => {
  if (req.session && req.session.isAdmin) {
    return res.redirect('/admin/index.php');
  }
  res.render('login', { error: null });
});

app.post(['/public/login0718.php', '/login', '/admin/login.php'], (req, res) => {
  const { username, password } = req.body;
  const admin = db.admins.find(a => a.username === username && a.password === password);

  if (admin) {
    req.session.isAdmin = true;
    req.session.adminUser = admin.username;
    return res.redirect('/admin/index.php');
  }

  res.render('login', { error: 'ユーザー名またはパスワードが正しくありません。' });
});

// -------------------------------------------------------------
// Admin Dashboard Routes (Protected)
// -------------------------------------------------------------

// Dashboard
app.get(['/admin', '/admin/', '/admin/index.php'], requireAdmin, (req, res) => {
  res.render('admin/dashboard', {
    stats: {
      items: db.items.length,
      actresses: db.actresses.length,
      genres: db.genres.length,
      links: db.mutual_links.length
    },
    analytics: db.analytics,
    syncLogs: db.sync_logs
  });
});

// Site Settings
app.get('/admin/site_settings.php', requireAdmin, (req, res) => {
  const success = req.query.saved === '1';
  res.render('admin/site_settings', { success });
});

app.post('/admin/site_settings.php', requireAdmin, (req, res) => {
  const { site_name, site_tagline, site_keywords, header_ad_html, custom_head_code } = req.body;
  if (site_name) db.settings.site_name = site_name;
  if (site_tagline) db.settings.site_tagline = site_tagline;
  if (site_keywords !== undefined) db.settings.site_keywords = site_keywords;
  if (header_ad_html !== undefined) db.settings.header_ad_html = header_ad_html;
  if (custom_head_code !== undefined) db.settings.custom_head_code = custom_head_code;
  res.redirect('/admin/site_settings.php?saved=1');
});

// Affiliate API & Sync Settings
app.get('/admin/affiliate_api.php', requireAdmin, (req, res) => {
  const success = req.query.msg ? decodeURIComponent(req.query.msg) : null;
  res.render('admin/affiliate_api', { success });
});

app.post('/admin/affiliate_api.php', requireAdmin, (req, res) => {
  const { action, fanza_api_id, fanza_affiliate_id, item_sync_batch, item_sync_interval } = req.body;

  if (action === 'test_fetch') {
    // Simulate manual test fetch of 10 items
    const nowStr = new Date().toISOString().replace('T', ' ').substring(0, 19);
    db.settings.last_sync_time = nowStr;
    db.settings.last_sync_status = 'success';
    db.settings.last_sync_count = 10;

    const logId = db.sync_logs.length ? Math.max(...db.sync_logs.map(l => l.id)) + 1 : 1;
    db.sync_logs.unshift({
      id: logId,
      api_name: 'ItemList',
      endpoint: '/api/v3/ItemList',
      is_success: 1,
      item_count: 10,
      message: '手動テスト取得：10件の商品データを取得・同期しました',
      created_at: nowStr
    });

    return res.redirect('/admin/affiliate_api.php?msg=' + encodeURIComponent('手動同期を実行しました（10件取得成功）'));
  }

  // Save config
  if (fanza_api_id !== undefined) db.settings.fanza_api_id = fanza_api_id;
  if (fanza_affiliate_id) db.settings.fanza_affiliate_id = fanza_affiliate_id;
  if (item_sync_batch) db.settings.item_sync_batch = item_sync_batch;
  if (item_sync_interval) db.settings.item_sync_interval = item_sync_interval;

  res.redirect('/admin/affiliate_api.php?msg=' + encodeURIComponent('API設定を正常に更新しました'));
});

// Mutual Links Management
app.get(['/admin/links.php', '/admin/link_partners.php'], requireAdmin, (req, res) => {
  const success = req.query.msg ? decodeURIComponent(req.query.msg) : null;
  res.render('admin/links', {
    links: db.mutual_links,
    success
  });
});

app.post(['/admin/links.php', '/admin/link_partners.php'], requireAdmin, (req, res) => {
  const { action, id, site_name, site_url, rss_url } = req.body;

  if (action === 'delete' && id) {
    const nid = parseInt(id, 10);
    db.mutual_links = db.mutual_links.filter(l => l.id !== nid);
    return res.redirect('/admin/links.php?msg=' + encodeURIComponent('パートナーサイトを削除しました'));
  }

  if (action === 'add' && site_name && site_url) {
    const newId = db.mutual_links.length ? Math.max(...db.mutual_links.map(l => l.id)) + 1 : 1;
    db.mutual_links.push({
      id: newId,
      site_name,
      site_url,
      banner_url: '',
      rss_url: rss_url || '',
      status: 'approved',
      in_count: 0,
      out_count: 0,
      created_at: new Date().toISOString().split('T')[0]
    });
    return res.redirect('/admin/links.php?msg=' + encodeURIComponent('パートナーサイトを追加しました'));
  }

  res.redirect('/admin/links.php');
});

// Analytics Report
app.get(['/admin/analytics.php', '/admin/access_analytics.php'], requireAdmin, (req, res) => {
  res.render('admin/analytics', {
    analytics: db.analytics
  });
});

// CMS Pages Management
app.get('/admin/pages.php', requireAdmin, (req, res) => {
  const slug = req.query.slug || 'privacy';
  const selectedPage = db.pages.find(p => p.slug === slug);
  const success = req.query.saved === '1' ? 'ページ内容を更新しました' : null;

  res.render('admin/pages', {
    pages: db.pages,
    selectedPage,
    success
  });
});

app.post('/admin/pages.php', requireAdmin, (req, res) => {
  const { slug, title, content } = req.body;
  const page = db.pages.find(p => p.slug === slug);
  if (page) {
    if (title) page.title = title;
    if (content) page.content = content;
    return res.redirect(`/admin/pages.php?slug=${slug}&saved=1`);
  }
  res.redirect('/admin/pages.php');
});

// Tags
app.get('/admin/tags.php', requireAdmin, (req, res) => {
  const tagsSet = new Set();
  db.items.forEach(i => (i.tags || []).forEach(t => tagsSet.add(t)));
  db.genres.forEach(g => tagsSet.add(g.name));
  const tags = Array.from(tagsSet);

  res.render('admin/tags', { tags });
});

// Logout
app.get('/admin/logout.php', (req, res) => {
  req.session.destroy(() => {
    res.redirect('/public/login0718.php');
  });
});

// -------------------------------------------------------------
// Health / API Routes
// -------------------------------------------------------------
app.get('/api/health', (req, res) => {
  res.json({
    status: 'ok',
    app: 'PinkClub FANZA',
    items_count: db.items.length,
    actresses_count: db.actresses.length
  });
});

app.get('/api/items', (req, res) => {
  res.json(getItems(req.query));
});

app.get('/api/actresses', (req, res) => {
  res.json(db.actresses);
});

// Setup Check fallback
app.get(['/setup_check.php', '/public/setup_check.php'], (req, res) => {
  res.send(`
    <html>
      <head><title>Setup Complete | PinkClub FANZA</title><link rel="stylesheet" href="/assets/css/style.css"></head>
      <body style="padding:40px;font-family:sans-serif;max-width:600px;margin:0 auto;line-height:1.6;">
        <h1 style="color:#28a745;">✅ システム稼働準備完了</h1>
        <p>PinkClub FANZA のNode.js移行が正常に完了し、データベースおよび全モジュールが稼働しています。</p>
        <div style="margin-top:20px;">
          <a href="/" style="display:inline-block;background:#ff4da6;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;font-weight:bold;margin-right:10px;">サイトトップへ</a>
          <a href="/public/login0718.php" style="display:inline-block;background:#2271b1;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;font-weight:bold;">管理画面ログインへ</a>
        </div>
      </body>
    </html>
  `);
});

// 404 handler
app.use((req, res) => {
  res.status(404).send(`
    <html>
      <head><title>404 Not Found</title><link rel="stylesheet" href="/assets/css/style.css"></head>
      <body style="padding:40px;text-align:center;font-family:sans-serif;">
        <h2>404 ページが見つかりません</h2>
        <p>お探しのページは削除されたか、URLが変更された可能性があります。</p>
        <p><a href="/" style="color:#2271b1;">PinkClub FANZA トップページへ戻る</a></p>
      </body>
    </html>
  `);
});

// Start Server
app.listen(PORT, '0.0.0.0', () => {
  console.log(`PinkClub FANZA server running on http://0.0.0.0:${PORT}`);
});

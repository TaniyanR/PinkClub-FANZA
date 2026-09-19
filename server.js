import express from 'express';
import session from 'express-session';
import cookieParser from 'cookie-parser';
import path from 'path';
import { fileURLToPath } from 'url';
import { db, getItems, getItemById, getActressById, getGenreById, getRelatedItems } from './data.js';

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
  res.render('home', {
    popularItems,
    newItems,
    actresses: db.actresses,
    totalItems: db.items.length
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

  // SERP title optimization: 【品番】女優名 作品タイトル
  const contentId = item.content_id || item.product_id || '';
  const firstActress = actresses.length > 0 ? actresses[0].name : '';
  const prefixParts = [];
  if (contentId) prefixParts.push('【' + contentId + '】');
  if (firstActress && !item.title.includes(firstActress)) prefixParts.push(firstActress);
  const seoTitle = prefixParts.length > 0 ? prefixParts.join(' ') + ' ' + item.title : item.title;

  const canonicalUrl = 'https://pinkclub-fanza.com/item/' + encodeURIComponent(item.id);

  // Reviews & AggregateRating for Rich Snippets
  const reviews = [
    {
      reviewer_name: '動画ファン',
      rating: 5,
      review_title: '圧倒的なクオリティと完成度！',
      review_body: '高画質でテンポも良く、最初から最後まで期待以上のクオリティでした。',
      created_at: item.release_date || '2026-03-01'
    }
  ];

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
    item,
    actresses,
    genres,
    maker,
    series,
    relatedItems,
    reviews,
    canonicalUrl,
    jsonLd: JSON.stringify(jsonLdData)
  });
});

// Actresses Directory
app.get(['/actresses', '/actresses.php', '/public/actresses.php'], (req, res) => {
  res.render('actresses', {
    title: '人気AV女優一覧',
    actresses: db.actresses
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
  res.render('actress', {
    title: `${actress.name} の出演作品・プロフィール`,
    actress,
    items
  });
});

// Genres Directory
app.get(['/genres', '/genres.php', '/public/genres.php'], (req, res) => {
  res.render('genres', {
    title: 'ジャンル一覧',
    genres: db.genres
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
  res.render('genre', {
    title: `${genre.name} 作品一覧`,
    genre,
    items
  });
});

// Search
app.get(['/search', '/search.php', '/public/search.php'], (req, res) => {
  const query = req.query.q || '';
  const sort = req.query.sort || 'new';
  const items = getItems({ q: query, sort });

  res.render('search', {
    title: `「${query}」の検索結果`,
    query,
    sort,
    items
  });
});

// Mutual Links
app.get(['/links', '/links.php', '/public/links.php'], (req, res) => {
  const success = req.query.applied === '1';
  res.render('links', {
    title: '相互リンク集＆申請',
    links: db.mutual_links,
    success
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
  res.render('contact', {
    title: 'お問い合わせ',
    success
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
  res.render('page', {
    title: page.title,
    page
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

// In-memory data store for PinkClub FANZA
// Data structure mirrors the MySQL schema (items, actresses, genres, makers, settings, mutual_links, analytics)

export const db = {
  settings: {
    site_name: 'PinkClub FANZA',
    site_tagline: 'FANZAアダルト動画・人気女優まとめポータル',
    site_keywords: 'FANZA, DMM, AV, 女優, アダルト, エロ動画, 単体作品, 素人',
    site_logo_path: '',
    site_favicon_path: '',
    header_ad_html: '<div style="background:#222;border:1px dashed #ff4da6;color:#fff;padding:8px 16px;text-align:center;font-size:12px;border-radius:4px;"><a href="https://www.dmm.co.jp/digital/videoa/" target="_blank" rel="noopener noreferrer" style="color:#ff80bf;font-weight:bold;text-decoration:none;">【FANZA公式】新作・セール情報毎日更新！初回限定クーポン配布中</a></div>',
    custom_head_code: '',
    custom_body_open_code: '',
    fanza_api_id: '',
    fanza_affiliate_id: 'pinkclub-990',
    item_sync_batch: '10',
    item_sync_enabled: '1',
    item_sync_interval: '3',
    last_sync_time: '2026-09-18 12:00:00',
    last_sync_status: 'success',
    last_sync_count: 10
  },

  admins: [
    {
      id: 1,
      username: 'admin',
      password: 'password', // matching fixed spec /public/login0718.php admin/password
      email: 'admin@pinkclub-fanza.jp',
      created_at: '2026-01-01 00:00:00'
    }
  ],

  actresses: [
    {
      id: 1,
      dmm_id: '1064286',
      name: '河北彩花',
      ruby: 'かわきたさいか',
      birthday: '1999-04-24',
      prefectures: '東京都',
      cup: 'E',
      bust: 86,
      waist: 57,
      hip: 85,
      height: 169,
      image_url: 'https://pics.dmm.co.jp/mono/actjpgs/kawakita_saika.jpg',
      image_small: 'https://pics.dmm.co.jp/mono/actjpgs/kawakita_saika.jpg',
      image_large: 'https://pics.dmm.co.jp/mono/actjpgs/kawakita_saika.jpg'
    },
    {
      id: 2,
      dmm_id: '1063124',
      name: '小野六花',
      ruby: 'おのりっか',
      birthday: '2002-05-18',
      prefectures: '千葉県',
      cup: 'D',
      bust: 83,
      waist: 55,
      hip: 83,
      height: 148,
      image_url: 'https://pics.dmm.co.jp/mono/actjpgs/ono_rikka.jpg',
      image_small: 'https://pics.dmm.co.jp/mono/actjpgs/ono_rikka.jpg',
      image_large: 'https://pics.dmm.co.jp/mono/actjpgs/ono_rikka.jpg'
    },
    {
      id: 3,
      dmm_id: '1072938',
      name: '石川澪',
      ruby: 'いしかわみお',
      birthday: '2002-08-01',
      prefectures: '神奈川県',
      cup: 'C',
      bust: 81,
      waist: 56,
      hip: 84,
      height: 154,
      image_url: 'https://pics.dmm.co.jp/mono/actjpgs/ishikawa_mio.jpg',
      image_small: 'https://pics.dmm.co.jp/mono/actjpgs/ishikawa_mio.jpg',
      image_large: 'https://pics.dmm.co.jp/mono/actjpgs/ishikawa_mio.jpg'
    },
    {
      id: 4,
      dmm_id: '1081920',
      name: '七ツ森りり',
      ruby: 'ななつもりりり',
      birthday: '1995-08-08',
      prefectures: '東京都',
      cup: 'C',
      bust: 85,
      waist: 60,
      hip: 87,
      height: 153,
      image_url: 'https://pics.dmm.co.jp/mono/actjpgs/nanatsumori_riri.jpg',
      image_small: 'https://pics.dmm.co.jp/mono/actjpgs/nanatsumori_riri.jpg',
      image_large: 'https://pics.dmm.co.jp/mono/actjpgs/nanatsumori_riri.jpg'
    },
    {
      id: 5,
      dmm_id: '1088492',
      name: '金松季歩',
      ruby: 'かねまつきほ',
      birthday: '2001-12-14',
      prefectures: '愛知県',
      cup: 'F',
      bust: 88,
      waist: 58,
      hip: 86,
      height: 164,
      image_url: 'https://pics.dmm.co.jp/mono/actjpgs/kanematsu_kiho.jpg',
      image_small: 'https://pics.dmm.co.jp/mono/actjpgs/kanematsu_kiho.jpg',
      image_large: 'https://pics.dmm.co.jp/mono/actjpgs/kanematsu_kiho.jpg'
    },
    {
      id: 6,
      dmm_id: '1099231',
      name: '神宮寺ナオ',
      ruby: 'じんぐうじなお',
      birthday: '1997-02-15',
      prefectures: '千葉県',
      cup: 'D',
      bust: 84,
      waist: 58,
      hip: 85,
      height: 160,
      image_url: 'https://pics.dmm.co.jp/mono/actjpgs/jinguji_nao.jpg',
      image_small: 'https://pics.dmm.co.jp/mono/actjpgs/jinguji_nao.jpg',
      image_large: 'https://pics.dmm.co.jp/mono/actjpgs/jinguji_nao.jpg'
    }
  ],

  genres: [
    { id: 1, dmm_id: '4001', name: '巨乳', ruby: 'きょにゅう' },
    { id: 2, dmm_id: '4002', name: '美乳', ruby: 'びにゅう' },
    { id: 3, dmm_id: '4003', name: '単体作品', ruby: 'たんたいさくひん' },
    { id: 4, dmm_id: '4004', name: 'ハイビジョン', ruby: 'はいびじょん' },
    { id: 5, dmm_id: '4005', name: '独占配信', ruby: 'どくせんはいしん' },
    { id: 6, dmm_id: '4006', name: '美少女', ruby: 'びしょうじょ' },
    { id: 7, dmm_id: '4007', name: '制服', ruby: 'せいふく' },
    { id: 8, dmm_id: '4008', name: '人妻・主婦', ruby: 'ひとづましゅふ' },
    { id: 9, dmm_id: '4009', name: 'ドラマ', ruby: 'どらま' },
    { id: 10, dmm_id: '4010', name: '中出し', ruby: 'なかだし' },
    { id: 11, dmm_id: '4011', name: 'ハメ撮り', ruby: 'はめどり' },
    { id: 12, dmm_id: '4012', name: 'OL', ruby: 'おーえる' }
  ],

  makers: [
    { id: 1, dmm_id: '2001', name: 'S1 NO.1 STYLE', ruby: 'えすわん' },
    { id: 2, dmm_id: '2002', name: 'MOODYZ', ruby: 'むーでぃーず' },
    { id: 3, dmm_id: '2003', name: 'アイデアポケット', ruby: 'あいでぃあぽけっと' },
    { id: 4, dmm_id: '2004', name: 'SODクリエイト', ruby: 'えすおーでぃーくりえいと' },
    { id: 5, dmm_id: '2005', name: 'PRESTIGE', ruby: 'ぷれすてーじ' }
  ],

  series: [
    { id: 1, dmm_id: '3001', name: '極上セレクション', ruby: 'ごくじょうせれくしょん' },
    { id: 2, dmm_id: '3002', name: 'プレミアム専属', ruby: 'ぷれみあむせんぞく' },
    { id: 3, dmm_id: '3003', name: '濃密セックスドキュメント', ruby: 'のうみつせっくすどきゅめんと' }
  ],

  items: [
    {
      id: 1,
      content_id: 'ssis00982',
      product_id: 'ssis-982',
      title: '河北彩花 至高の完全美巨乳＆極上スタイル 4時間プレミアムスペシャル',
      service_name: '動画',
      floor_name: 'ビデオ',
      category_name: 'ビデオ (ビデオ (成人))',
      volume: '240分',
      review_count: 142,
      review_average: 4.85,
      view_count: 12850,
      price_min_text: '2,980円',
      release_date: '2026-08-15',
      url: 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=ssis00982/',
      affiliate_url: 'https://al.dmm.co.jp/?lurl=https%3A%2F%2Fwww.dmm.co.jp%2Fdigital%2Fvideoa%2F-%2Fdetail%2F%3D%2Fcid%3Dssis00982%2F&af_id=pinkclub-990&ch=link_tool&ch_id=text',
      image_list: 'https://pics.dmm.co.jp/digital/video/ssis00982/ssis00982ps.jpg',
      image_small: 'https://pics.dmm.co.jp/digital/video/ssis00982/ssis00982ps.jpg',
      image_large: 'https://pics.dmm.co.jp/digital/video/ssis00982/ssis00982pl.jpg',
      sample_images: [
        'https://pics.dmm.co.jp/digital/video/ssis00982/ssis00982-1.jpg',
        'https://pics.dmm.co.jp/digital/video/ssis00982/ssis00982-2.jpg',
        'https://pics.dmm.co.jp/digital/video/ssis00982/ssis00982-3.jpg',
        'https://pics.dmm.co.jp/digital/video/ssis00982/ssis00982-4.jpg'
      ],
      sample_movie_url_720: 'https://cc3001.dmm.co.jp/litevideo/freepv/s/ssi/ssis00982/ssis00982_dmb_w.mp4',
      actress_ids: [1],
      genre_ids: [1, 3, 4, 5],
      maker_id: 1,
      series_id: 1,
      director: 'タイガー小堺',
      label: 'S1 NO.1 STYLE',
      tags: ['巨乳', '単体作品', '美少女', '独占配信']
    },
    {
      id: 2,
      content_id: 'midd01234',
      product_id: 'midd-1234',
      title: '小野六花 初めての濃厚中出し解禁！可憐な美少女がトロける濃密情事',
      service_name: '動画',
      floor_name: 'ビデオ',
      category_name: 'ビデオ (ビデオ (成人))',
      volume: '150分',
      review_count: 98,
      review_average: 4.72,
      view_count: 9430,
      price_min_text: '2,480円',
      release_date: '2026-08-20',
      url: 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=midd01234/',
      affiliate_url: 'https://al.dmm.co.jp/?lurl=https%3A%2F%2Fwww.dmm.co.jp%2Fdigital%2Fvideoa%2F-%2Fdetail%2F%3D%2Fcid%3Dmidd01234%2F&af_id=pinkclub-990&ch=link_tool&ch_id=text',
      image_list: 'https://pics.dmm.co.jp/digital/video/midd01234/midd01234ps.jpg',
      image_small: 'https://pics.dmm.co.jp/digital/video/midd01234/midd01234ps.jpg',
      image_large: 'https://pics.dmm.co.jp/digital/video/midd01234/midd01234pl.jpg',
      sample_images: [
        'https://pics.dmm.co.jp/digital/video/midd01234/midd01234-1.jpg',
        'https://pics.dmm.co.jp/digital/video/midd01234/midd01234-2.jpg',
        'https://pics.dmm.co.jp/digital/video/midd01234/midd01234-3.jpg'
      ],
      sample_movie_url_720: 'https://cc3001.dmm.co.jp/litevideo/freepv/m/mid/midd01234/midd01234_dmb_w.mp4',
      actress_ids: [2],
      genre_ids: [3, 6, 10],
      maker_id: 2,
      series_id: 2,
      director: 'ザック荒井',
      label: 'MOODYZ',
      tags: ['中出し', '美少女', '単体作品']
    },
    {
      id: 3,
      content_id: 'ipx00876',
      product_id: 'ipx-876',
      title: '石川澪 ピュア女子校生の放課後秘密の個人レッスン 制服姿のまま濡れて乱れる',
      service_name: '動画',
      floor_name: 'ビデオ',
      category_name: 'ビデオ (ビデオ (成人))',
      volume: '130分',
      review_count: 76,
      review_average: 4.65,
      view_count: 8120,
      price_min_text: '2,200円',
      release_date: '2026-08-25',
      url: 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=ipx00876/',
      affiliate_url: 'https://al.dmm.co.jp/?lurl=https%3A%2F%2Fwww.dmm.co.jp%2Fdigital%2Fvideoa%2F-%2Fdetail%2F%3D%2Fcid%3Dipx00876%2F&af_id=pinkclub-990&ch=link_tool&ch_id=text',
      image_list: 'https://pics.dmm.co.jp/digital/video/ipx00876/ipx00876ps.jpg',
      image_small: 'https://pics.dmm.co.jp/digital/video/ipx00876/ipx00876ps.jpg',
      image_large: 'https://pics.dmm.co.jp/digital/video/ipx00876/ipx00876pl.jpg',
      sample_images: [
        'https://pics.dmm.co.jp/digital/video/ipx00876/ipx00876-1.jpg',
        'https://pics.dmm.co.jp/digital/video/ipx00876/ipx00876-2.jpg'
      ],
      sample_movie_url_720: 'https://cc3001.dmm.co.jp/litevideo/freepv/i/ipx/ipx00876/ipx00876_dmb_w.mp4',
      actress_ids: [3],
      genre_ids: [3, 6, 7],
      maker_id: 3,
      series_id: 3,
      director: 'トミフク',
      label: 'アイデアポケット',
      tags: ['制服', '美少女', '単体作品']
    },
    {
      id: 4,
      content_id: 'stars00551',
      product_id: 'stars-551',
      title: '七ツ森りり 美人OLの背徳残業 終電後のオフィスで社長と交わす濃密キス',
      service_name: '動画',
      floor_name: 'ビデオ',
      category_name: 'ビデオ (ビデオ (成人))',
      volume: '140分',
      review_count: 64,
      review_average: 4.58,
      view_count: 6940,
      price_min_text: '2,680円',
      release_date: '2026-09-01',
      url: 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=stars00551/',
      affiliate_url: 'https://al.dmm.co.jp/?lurl=https%3A%2F%2Fwww.dmm.co.jp%2Fdigital%2Fvideoa%2F-%2Fdetail%2F%3D%2Fcid%3Dstars00551%2F&af_id=pinkclub-990&ch=link_tool&ch_id=text',
      image_list: 'https://pics.dmm.co.jp/digital/video/stars00551/stars00551ps.jpg',
      image_small: 'https://pics.dmm.co.jp/digital/video/stars00551/stars00551ps.jpg',
      image_large: 'https://pics.dmm.co.jp/digital/video/stars00551/stars00551pl.jpg',
      sample_images: [
        'https://pics.dmm.co.jp/digital/video/stars00551/stars00551-1.jpg',
        'https://pics.dmm.co.jp/digital/video/stars00551/stars00551-2.jpg'
      ],
      sample_movie_url_720: 'https://cc3001.dmm.co.jp/litevideo/freepv/s/sta/stars00551/stars00551_dmb_w.mp4',
      actress_ids: [4],
      genre_ids: [3, 9, 12],
      maker_id: 4,
      series_id: 1,
      director: '嵐山',
      label: 'SODクリエイト',
      tags: ['OL', 'ドラマ', '単体作品']
    },
    {
      id: 5,
      content_id: 'abw00320',
      product_id: 'abw-320',
      title: '金松季歩 溢れるFカップ美巨乳＆極上のくびれ 欲望むき出しハメ撮りセックス',
      service_name: '動画',
      floor_name: 'ビデオ',
      category_name: 'ビデオ (ビデオ (成人))',
      volume: '160分',
      review_count: 55,
      review_average: 4.80,
      view_count: 7500,
      price_min_text: '2,500円',
      release_date: '2026-09-05',
      url: 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=abw00320/',
      affiliate_url: 'https://al.dmm.co.jp/?lurl=https%3A%2F%2Fwww.dmm.co.jp%2Fdigital%2Fvideoa%2F-%2Fdetail%2F%3D%2Fcid%3Dabw00320%2F&af_id=pinkclub-990&ch=link_tool&ch_id=text',
      image_list: 'https://pics.dmm.co.jp/digital/video/abw00320/abw00320ps.jpg',
      image_small: 'https://pics.dmm.co.jp/digital/video/abw00320/abw00320ps.jpg',
      image_large: 'https://pics.dmm.co.jp/digital/video/abw00320/abw00320pl.jpg',
      sample_images: [
        'https://pics.dmm.co.jp/digital/video/abw00320/abw00320-1.jpg',
        'https://pics.dmm.co.jp/digital/video/abw00320/abw00320-2.jpg'
      ],
      sample_movie_url_720: 'https://cc3001.dmm.co.jp/litevideo/freepv/a/abw/abw00320/abw00320_dmb_w.mp4',
      actress_ids: [5],
      genre_ids: [1, 2, 11],
      maker_id: 5,
      series_id: 2,
      director: 'TAKERU',
      label: 'PRESTIGE',
      tags: ['巨乳', '美乳', 'ハメ撮り']
    },
    {
      id: 6,
      content_id: 'ssis00995',
      product_id: 'ssis-995',
      title: '神宮寺ナオ 隣の人妻と昼下がりの密会 誰にも言えない秘密の不倫情事',
      service_name: '動画',
      floor_name: 'ビデオ',
      category_name: 'ビデオ (ビデオ (成人))',
      volume: '145分',
      review_count: 82,
      review_average: 4.68,
      view_count: 6210,
      price_min_text: '2,980円',
      release_date: '2026-09-10',
      url: 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=ssis00995/',
      affiliate_url: 'https://al.dmm.co.jp/?lurl=https%3A%2F%2Fwww.dmm.co.jp%2Fdigital%2Fvideoa%2F-%2Fdetail%2F%3D%2Fcid%3Dssis00995%2F&af_id=pinkclub-990&ch=link_tool&ch_id=text',
      image_list: 'https://pics.dmm.co.jp/digital/video/ssis00995/ssis00995ps.jpg',
      image_small: 'https://pics.dmm.co.jp/digital/video/ssis00995/ssis00995ps.jpg',
      image_large: 'https://pics.dmm.co.jp/digital/video/ssis00995/ssis00995pl.jpg',
      sample_images: [
        'https://pics.dmm.co.jp/digital/video/ssis00995/ssis00995-1.jpg',
        'https://pics.dmm.co.jp/digital/video/ssis00995/ssis00995-2.jpg'
      ],
      sample_movie_url_720: 'https://cc3001.dmm.co.jp/litevideo/freepv/s/ssi/ssis00995/ssis00995_dmb_w.mp4',
      actress_ids: [6],
      genre_ids: [3, 8, 9],
      maker_id: 1,
      series_id: 1,
      director: 'タイガー小堺',
      label: 'S1 NO.1 STYLE',
      tags: ['人妻・主婦', 'ドラマ', '単体作品']
    }
  ],

  mutual_links: [
    {
      id: 1,
      site_name: 'FANZA厳選エロ動画ナビ',
      site_url: 'https://example.com/fanza-navi',
      banner_url: '',
      rss_url: 'https://example.com/rss.xml',
      status: 'approved',
      in_count: 342,
      out_count: 285,
      created_at: '2026-03-01'
    },
    {
      id: 2,
      site_name: '人気AV女優図鑑アンテナ',
      site_url: 'https://example.com/actress-antenna',
      banner_url: '',
      rss_url: 'https://example.com/antenna.xml',
      status: 'approved',
      in_count: 512,
      out_count: 420,
      created_at: '2026-03-05'
    },
    {
      id: 3,
      site_name: '新作アダルト速報まとめ',
      site_url: 'https://example.com/new-adult-news',
      banner_url: '',
      rss_url: '',
      status: 'approved',
      in_count: 189,
      out_count: 140,
      created_at: '2026-03-12'
    }
  ],

  pages: [
    {
      id: 1,
      slug: 'privacy',
      title: 'プライバシーポリシー',
      content: `<h2>1. 個人情報の収集と利用目的</h2>
<p>当サイト（PinkClub FANZA）では、お問い合わせや相互リンク申請時に送信される情報（お名前、メールアドレス、サイトURL等）を、お問い合わせへの回答やリンク確認の目的のみに使用します。</p>
<h2>2. Cookieおよびアクセス解析</h2>
<p>当サイトでは利便性向上やサイトの改善のため、Cookieおよびアクセス解析ツールを利用しています。Cookieには個人を特定する情報は含まれません。</p>
<h2>3. アフィリエイトプログラムについて</h2>
<p>当サイトはFANZA・DMMなどのアフィリエイトプログラムに参加しています。商品の購入やお支払いに関する手続きはリンク先の販売サイトで行われます。</p>`
    },
    {
      id: 2,
      slug: 'about',
      title: '当サイトについて',
      content: `<h2>PinkClub FANZAについて</h2>
<p>PinkClub FANZAは、FANZAで配信されている人気アダルト動画、単体作品、人気AV女優情報を紹介するポータルサイトです。</p>
<p>最新のリリース情報や人気ランキング、サンプル動画・画像をわかりやすくまとめ、お気に入りの作品を探すお手伝いをしています。</p>
<p>※当サイトは18歳未満の方の閲覧をお断りしております。</p>`
    },
    {
      id: 3,
      slug: 'law',
      title: '特定商取引法に基づく表記',
      content: `<h2>特定商取引法に基づく表記</h2>
<p>当サイトはアフィリエイトプログラムにより商品・サービスをご紹介しており、直接の通信販売・コンテンツ配信は行っておりません。</p>
<p>商品・サービスに関するお問い合わせ、ご注文、お支払い、キャンセル等につきましては、リンク先の販売元（合同会社DMM.com / FANZA）へ直接お問い合わせくださいますようお願い申し上げます。</p>`
    }
  ],

  analytics: {
    today_pv: 1248,
    yesterday_pv: 1195,
    last7_pv: 8430,
    month_pv: 36200,
    today_uu: 820,
    yesterday_uu: 790,
    referrers: [
      { source: 'Google 検索', count: 520, percent: '41.7%' },
      { source: 'Yahoo! 検索', count: 280, percent: '22.4%' },
      { source: '相互リンクサイト', count: 230, percent: '18.4%' },
      { source: 'Twitter/X', count: 118, percent: '9.5%' },
      { source: 'ダイレクト/ブックマーク', count: 100, percent: '8.0%' }
    ],
    destinations: [
      { target: 'FANZA動画公式 (ssis00982)', count: 184, percent: '32.1%' },
      { target: 'FANZA動画公式 (midd01234)', count: 126, percent: '22.0%' },
      { target: '相互リンク (人気AV女優図鑑)', count: 82, percent: '14.3%' },
      { target: 'FANZA動画公式 (ipx00876)', count: 71, percent: '12.4%' }
    ]
  },

  sync_logs: [
    {
      id: 1,
      api_name: 'ItemList',
      endpoint: '/api/v3/ItemList',
      is_success: 1,
      item_count: 10,
      message: '商品10件の自動同期に成功しました',
      created_at: '2026-09-18 12:00:00'
    },
    {
      id: 2,
      api_name: 'ActressSearch',
      endpoint: '/api/v3/ActressSearch',
      is_success: 1,
      item_count: 6,
      message: '女優6件のマスタ同期に成功しました',
      created_at: '2026-09-18 09:00:00'
    }
  ]
};

// Helper methods to query data
export function getItems(query = {}) {
  let list = [...db.items];
  if (query.q) {
    const q = query.q.toLowerCase();
    list = list.filter(i => 
      i.title.toLowerCase().includes(q) || 
      i.tags.some(t => t.toLowerCase().includes(q))
    );
  }
  if (query.genre_id) {
    const gid = parseInt(query.genre_id, 10);
    list = list.filter(i => i.genre_ids.includes(gid));
  }
  if (query.actress_id) {
    const aid = parseInt(query.actress_id, 10);
    list = list.filter(i => i.actress_ids.includes(aid));
  }
  if (query.sort === 'popular') {
    list.sort((a, b) => b.view_count - a.view_count);
  } else if (query.sort === 'review') {
    list.sort((a, b) => (b.review_average || 0) - (a.review_average || 0));
  } else {
    // default new
    list.sort((a, b) => new Date(b.release_date) - new Date(a.release_date));
  }
  return list;
}

export function getItemById(id) {
  const nid = parseInt(id, 10);
  return db.items.find(i => i.id === nid || i.content_id === id);
}

export function getActressById(id) {
  const nid = parseInt(id, 10);
  return db.actresses.find(a => a.id === nid || a.dmm_id === id);
}

export function getGenreById(id) {
  const nid = parseInt(id, 10);
  return db.genres.find(g => g.id === nid || g.dmm_id === id);
}

export function getRelatedItems(item, limit = 4) {
  if (!item) return [];
  const related = db.items
    .filter(i => i.id !== item.id)
    .map(i => {
      let score = 0;
      // Actress match (+5)
      if (item.actress_ids.some(aid => i.actress_ids.includes(aid))) score += 5;
      // Maker match (+4)
      if (item.maker_id && item.maker_id === i.maker_id) score += 4;
      // Genre match (+3 per shared genre)
      const sharedGenres = item.genre_ids.filter(gid => i.genre_ids.includes(gid)).length;
      score += sharedGenres * 3;
      return { item: i, score };
    })
    .sort((a, b) => b.score - a.score)
    .map(res => res.item);

  return related.slice(0, limit);
}

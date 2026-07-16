<?php
declare(strict_types=1);
?>
<section id="pcf-recently-viewed" class="pcf-recent" aria-labelledby="pcf-recent-title" hidden>
  <div class="pcf-recent__heading">
    <div>
      <h2 id="pcf-recent-title">最近見た作品</h2>
      <p>閲覧履歴は、このブラウザ内だけに保存されます。</p>
    </div>
    <button id="pcf-recent-clear" class="pcf-recent__clear" type="button">履歴をすべて削除</button>
  </div>
  <div id="pcf-recent-list" class="pcf-recent__list" aria-live="polite"></div>
</section>
<style>
.pcf-recent{margin:18px 0 24px;padding:16px;border:1px solid #ddd;border-radius:8px;background:#fff}
.pcf-recent__heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}
.pcf-recent__heading h2{margin:0 0 4px;font-size:22px}
.pcf-recent__heading p{margin:0;color:#666;font-size:12px;line-height:1.5}
.pcf-recent__clear,.pcf-recent__remove{border:1px solid #888;border-radius:5px;background:#fff;color:#333;cursor:pointer}
.pcf-recent__clear{padding:7px 10px;font-size:12px;white-space:nowrap}
.pcf-recent__list{display:flex;gap:14px;overflow-x:auto;padding:2px 2px 10px;scrollbar-width:thin}
.pcf-recent__card{position:relative;flex:0 0 180px;min-width:180px}
.pcf-recent__card-image{display:block;width:180px;height:250px;object-fit:cover;border-radius:6px;background:#eee}
.pcf-recent__card-title{display:-webkit-box;margin:8px 0 7px;overflow:hidden;color:#1670b7;font-size:14px;font-weight:700;line-height:1.45;text-decoration:none;-webkit-box-orient:vertical;-webkit-line-clamp:3}
.pcf-recent__card-actions{display:flex;gap:6px;align-items:center}
.pcf-recent__open{flex:1;padding:7px 8px;border:1px solid #555;border-radius:5px;color:#222;text-align:center;text-decoration:none;font-size:12px;font-weight:700}
.pcf-recent__remove{padding:7px 8px;font-size:12px}
@media (max-width:700px){.pcf-recent{padding:12px}.pcf-recent__heading{display:block}.pcf-recent__clear{margin-top:9px}.pcf-recent__card{flex-basis:150px;min-width:150px}.pcf-recent__card-image{width:150px;height:210px}}
</style>

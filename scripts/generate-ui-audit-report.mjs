import fs from 'fs';
import path from 'path';

const rootDir = process.cwd();
const auditDir = path.join(rootDir, 'artifacts', 'ui-audit');
const reportPath = path.join(auditDir, 'report.json');
const outputPath = path.join(auditDir, 'index.html');

if (!fs.existsSync(reportPath)) {
  console.error(`Missing report file: ${reportPath}`);
  process.exit(1);
}

const raw = fs.readFileSync(reportPath, 'utf8');
const entries = JSON.parse(raw);

const normalizedEntries = entries.map((entry) => {
  const screenshotAbs = String(entry.screenshot || '');
  const screenshotRel = screenshotAbs
    ? path.relative(auditDir, screenshotAbs).replaceAll('\\', '/')
    : '';
  const overflowNodes = Array.isArray(entry?.overflow?.overflows)
    ? entry.overflow.overflows.length
    : 0;
  const docOverflow = Boolean(
    entry?.overflow &&
      Number(entry.overflow.docScrollWidth || 0) > Number(entry.overflow.docClientWidth || 0)
  );

  return {
    ...entry,
    screenshotRel,
    overflowNodes,
    docOverflow,
    hasError: Boolean(entry.error),
  };
});

const pages = [...new Set(normalizedEntries.map((e) => e.page))].sort();
const locales = [...new Set(normalizedEntries.map((e) => e.locale))].sort();
const viewports = [...new Set(normalizedEntries.map((e) => e.viewport))].sort((a, b) => Number(a) - Number(b));

const html = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>UI Audit Report</title>
  <style>
    :root {
      --bg: #f4f6f8;
      --card: #ffffff;
      --text: #112233;
      --muted: #5e6a79;
      --border: #dbe2ea;
      --accent: #116b75;
      --danger: #b42323;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Segoe UI, Tahoma, Arial, sans-serif;
      color: var(--text);
      background: linear-gradient(180deg, #f9fbfc 0%, var(--bg) 100%);
    }
    .container {
      width: min(1400px, 96vw);
      margin: 0 auto;
      padding: 16px 0 28px;
    }
    .header {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px;
      margin-bottom: 12px;
    }
    h1 {
      margin: 0 0 10px;
      font-size: 22px;
    }
    .stats {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin: 0 0 10px;
    }
    .pill {
      background: #edf4f6;
      border: 1px solid #c9dde1;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 12px;
      font-weight: 700;
    }
    .filters {
      display: grid;
      grid-template-columns: repeat(5, minmax(140px, 1fr));
      gap: 8px;
    }
    .filters input, .filters select {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid var(--border);
      border-radius: 10px;
      background: #fff;
      font: inherit;
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 12px;
    }
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 10px;
      display: grid;
      gap: 8px;
    }
    .meta {
      display: grid;
      gap: 4px;
      font-size: 12px;
    }
    .meta strong { font-size: 14px; }
    .line {
      color: var(--muted);
      word-break: break-word;
    }
    .badge {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
      border: 1px solid #d1dae4;
      background: #f6f8fb;
      margin-right: 4px;
    }
    .badge.err {
      border-color: #efb4b4;
      background: #fff0f0;
      color: var(--danger);
    }
    .badge.warn {
      border-color: #f0d59b;
      background: #fff7e8;
      color: #8a5a00;
    }
    img {
      width: 100%;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: #fff;
    }
    details {
      border: 1px dashed #d8e1ec;
      border-radius: 10px;
      padding: 6px 8px;
      background: #fcfdff;
      font-size: 12px;
    }
    summary { cursor: pointer; font-weight: 700; }
    pre {
      white-space: pre-wrap;
      word-break: break-word;
      margin: 8px 0 0;
      color: #344251;
    }
    @media (max-width: 980px) {
      .filters { grid-template-columns: 1fr 1fr; }
      .grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>UI Audit Report</h1>
      <div class="stats" id="stats"></div>
      <div class="filters">
        <select id="pageFilter"><option value="">All pages</option></select>
        <select id="localeFilter"><option value="">All locales</option></select>
        <select id="viewportFilter"><option value="">All viewports</option></select>
        <select id="statusFilter">
          <option value="">All status</option>
          <option value="ok">No error</option>
          <option value="error">Has error</option>
          <option value="docOverflow">Doc overflow</option>
        </select>
        <input id="searchInput" placeholder="Search title/url/text..." />
      </div>
    </div>
    <div class="grid" id="grid"></div>
  </div>
  <script>
    const data = ${JSON.stringify(normalizedEntries)};
    const pages = ${JSON.stringify(pages)};
    const locales = ${JSON.stringify(locales)};
    const viewports = ${JSON.stringify(viewports)};

    const pageFilter = document.getElementById('pageFilter');
    const localeFilter = document.getElementById('localeFilter');
    const viewportFilter = document.getElementById('viewportFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');
    const grid = document.getElementById('grid');
    const stats = document.getElementById('stats');

    for (const p of pages) pageFilter.insertAdjacentHTML('beforeend', '<option value="' + p + '">' + p + '</option>');
    for (const l of locales) localeFilter.insertAdjacentHTML('beforeend', '<option value="' + l + '">' + l + '</option>');
    for (const v of viewports) viewportFilter.insertAdjacentHTML('beforeend', '<option value="' + v + '">' + v + '</option>');

    function esc(v) {
      return String(v ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
    }

    function renderStats(items) {
      const total = items.length;
      const errors = items.filter(i => i.hasError).length;
      const docOverflow = items.filter(i => i.docOverflow).length;
      const overflowNodes = items.reduce((sum, i) => sum + Number(i.overflowNodes || 0), 0);
      stats.innerHTML = [
        'Total: ' + total,
        'Errors: ' + errors,
        'Doc overflow: ' + docOverflow,
        'Overflow nodes (sum): ' + overflowNodes
      ].map(v => '<span class="pill">' + esc(v) + '</span>').join('');
    }

    function render() {
      const q = searchInput.value.trim().toLowerCase();
      const filtered = data.filter(item => {
        if (pageFilter.value && item.page !== pageFilter.value) return false;
        if (localeFilter.value && item.locale !== localeFilter.value) return false;
        if (viewportFilter.value && item.viewport !== viewportFilter.value) return false;
        if (statusFilter.value === 'ok' && item.hasError) return false;
        if (statusFilter.value === 'error' && !item.hasError) return false;
        if (statusFilter.value === 'docOverflow' && !item.docOverflow) return false;
        if (!q) return true;
        const hay = [
          item.page, item.locale, item.viewport, item.title, item.url, item.path,
          item.error, ...(item.overflow?.overflows || []).map(x => x.text || '')
        ].join(' ').toLowerCase();
        return hay.includes(q);
      });

      renderStats(filtered);

      grid.innerHTML = filtered.map(item => {
        const badges = [
          '<span class="badge">' + esc(item.locale) + '</span>',
          '<span class="badge">' + esc(item.viewport) + '</span>',
          item.hasError ? '<span class="badge err">error</span>' : '',
          item.docOverflow ? '<span class="badge warn">doc overflow</span>' : '',
          Number(item.overflowNodes || 0) > 0 ? '<span class="badge warn">nodes: ' + esc(item.overflowNodes) + '</span>' : ''
        ].join('');

        const overflowDetail = item.overflow
          ? '<details><summary>Overflow details</summary><pre>' + esc(JSON.stringify(item.overflow, null, 2)) + '</pre></details>'
          : '';

        const errorDetail = item.error
          ? '<details open><summary>Error</summary><pre>' + esc(item.error) + '</pre></details>'
          : '';

        const image = item.screenshotRel
          ? '<a href="' + esc(item.screenshotRel) + '" target="_blank" rel="noopener noreferrer"><img src="' + esc(item.screenshotRel) + '" loading="lazy" alt="' + esc(item.page) + '"></a>'
          : '<div class="line">No screenshot path</div>';

        return '<article class="card">'
          + '<div class="meta">'
          + '<strong>' + esc(item.page) + '</strong>'
          + '<div>' + badges + '</div>'
          + '<div class="line">Path: ' + esc(item.path || '-') + '</div>'
          + '<div class="line">Title: ' + esc(item.title || '-') + '</div>'
          + '<div class="line">URL: ' + esc(item.url || '-') + '</div>'
          + '</div>'
          + image
          + errorDetail
          + overflowDetail
          + '</article>';
      }).join('');
    }

    for (const el of [pageFilter, localeFilter, viewportFilter, statusFilter, searchInput]) {
      el.addEventListener('input', render);
      el.addEventListener('change', render);
    }

    render();
  </script>
</body>
</html>`;

fs.writeFileSync(outputPath, html, 'utf8');
console.log(`Generated: ${outputPath}`);

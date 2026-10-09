// Compares the DOM skeleton (tag#id.classes, nesting) of gramiyum.in pages
// with the same paths on this test copy, and prints the differences.
//
//   node scripts/compare-dom.mjs [baseUrl]      (default http://localhost:8081)
//
// Text, attributes other than id/class, and repeated content (product lists,
// description paragraphs) are ignored — this checks selector compatibility.

const LOCAL = process.argv[2] || 'http://localhost:8081';
const LIVE = 'https://gramiyum.in';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130 Safari/537.36';

// Simple product present on both sites (natural-turmeric-powder).
const CART_ITEM = 4701;

const PAGES = [
  { name: 'header', path: '/product/pure-cow-ghee/', from: '<header', to: '</header>' },
  { name: 'product (variable)', path: '/product/pure-cow-ghee/', from: '</header>', to: '<div class="tbay-ywfbt-wrapper' },
  { name: 'product (simple)', path: '/product/natural-turmeric-powder/', from: '</header>', to: '<div class="tbay-ywfbt-wrapper' },
  { name: 'category', path: '/product-category/cow-ghee/', from: '</header>', to: '<div class="products products-grid' },
  { name: 'category card', path: '/product-category/masala-and-spices/podi-varities/', from: '<div class="products products-grid', to: '</div></div></div></div></div></div></div>' },
  { name: 'shop', path: '/shop/', from: '</header>', to: '<div class="products products-grid' },
  { name: 'cart (empty)', path: '/cart/', from: '</header>', to: '<footer' },
  // Logged-out visitor with one item in the cart (no order is placed).
  { name: 'cart (1 item)', path: '/cart/', from: '</header>', to: '<footer', cart: CART_ITEM },
  { name: 'checkout', path: '/checkout/', from: '</header>', to: '<footer', cart: CART_ITEM },
];

const VOID = new Set(['img', 'input', 'br', 'hr', 'meta', 'link', 'source', 'wbr']);
const NOISE = /^(p|ol|li|strong|span|br|em|ul|b|i|h[1-6]|bdi|del|ins|a|svg|path|circle)$/;

function skeleton(html, from, to) {
  const a = html.indexOf(from);
  if (a < 0) return [`(marker not found: ${from})`];
  const b = to ? html.indexOf(to, a + from.length) : -1;
  const region = html.slice(a, b < 0 ? html.length : b)
    .replace(/<script[\s\S]*?<\/script>/g, '').replace(/<style[\s\S]*?<\/style>/g, '')
    .replace(/<svg[\s\S]*?<\/svg>/g, '').replace(/<!--[\s\S]*?-->/g, '');
  const out = [];
  let depth = 0;
  for (const [, close, tag, attrs] of region.matchAll(/<(\/?)([a-zA-Z0-9]+)([^>]*)>/g)) {
    if (close) { depth = Math.max(0, depth - 1); continue; }
    const id = (attrs.match(/\sid=["']([^"']*)["']/) || [])[1];
    const cls = ((attrs.match(/\sclass=["']([^"']*)["']/) || [])[1] || '').trim().split(/\s+/).filter(Boolean)
      .filter((c) => !/^(wp-image|size|attachment)-/.test(c)).join('.');
    if (!NOISE.test(tag) || id || cls) {
      // Per-render random ids on both sites: quantity_<uniqid>, GreenMart menu-1-XXXXX etc.
      const stableId = id && id.replace(/^quantity_\w+$/, 'quantity_X').replace(/^(menu-1|mobile-category|category-\d)-[A-Za-z0-9]{5}$/, '$1-RAND');
      out.push('  '.repeat(depth) + tag + (stableId ? '#' + stableId : '') + (cls ? '.' + cls : ''));
    }
    if (!VOID.has(tag.toLowerCase()) && !attrs.trim().endsWith('/')) depth++;
  }
  return out;
}

// Minimal LCS line diff.
function diff(a, b) {
  const n = a.length, m = b.length;
  const dp = Array.from({ length: n + 1 }, () => new Uint16Array(m + 1));
  for (let i = n - 1; i >= 0; i--) for (let j = m - 1; j >= 0; j--)
    dp[i][j] = a[i] === b[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
  const out = [];
  let i = 0, j = 0;
  while (i < n || j < m) {
    if (i < n && j < m && a[i] === b[j]) { i++; j++; }
    else if (j < m && (i === n || dp[i][j + 1] >= dp[i + 1][j])) out.push('+ ' + b[j++]);
    else out.push('- ' + a[i++]);
  }
  return out;
}

const get = async (url, cookie = '') => (await fetch(url, { headers: { 'User-Agent': UA, Cookie: cookie } })).text();

// New WooCommerce session with one product in the cart; returns its Cookie header.
async function cartSession(base, productId) {
  const res = await fetch(`${base}/?add-to-cart=${productId}`, { headers: { 'User-Agent': UA }, redirect: 'manual' });
  return res.headers.getSetCookie().map((c) => c.split(';')[0]).join('; ');
}
const sessions = {};
const cookieFor = async (base, id) => (id ? (sessions[base + id] ??= await cartSession(base, id)) : '');

let total = 0;
for (const p of PAGES) {
  const [live, local] = await Promise.all([
    get(LIVE + p.path, await cookieFor(LIVE, p.cart)),
    get(LOCAL + p.path, await cookieFor(LOCAL, p.cart)),
  ]);
  const d = diff(skeleton(live, p.from, p.to), skeleton(local, p.from, p.to));
  total += d.length;
  console.log(`\n=== ${p.name}  ${p.path}  — ${d.length ? d.length + ' differing lines' : 'identical'}`);
  if (d.length) console.log(d.slice(0, 60).join('\n') + (d.length > 60 ? `\n… ${d.length - 60} more` : ''));
}
console.log(`\n${total} differing lines in total ( - only on gramiyum.in, + only here )`);

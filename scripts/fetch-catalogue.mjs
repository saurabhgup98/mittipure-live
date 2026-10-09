// Pulls the subset of gramiyum.in's catalogue we mirror (10 categories,
// 2 products each) from its public WooCommerce Store API and writes
// scripts/seed-data.json, which seed.php then loads into WordPress.
//
//   node scripts/fetch-catalogue.mjs
//
// Only names/slugs/prices/descriptions/attributes are kept; product images
// are deliberately NOT copied (seed.php generates placeholders).

import { writeFile } from 'node:fs/promises';

const BASE = 'https://gramiyum.in/wp-json/wc/store/v1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130 Safari/537.36';
const PER_CATEGORY = 2;

// Order = mega-menu order. Parents must come before children.
const CATEGORY_SLUGS = [
  'traditional-rice-varieties',
  'cow-ghee',
  'millets',
  'dried-nuts-and-seeds',
  'masala-and-spices',
  'podi-varities',
  'cold-pressed-oil',
  'diabetic-friendly',
  'snacks',
  'buy-natural-combo-foods-online',
];

// Linked directly from the header nav, so it must exist. On gramiyum.in it
// lives in "Festive combo"; we file it under Combo Boxes to stay at 10.
const PINNED = { 'buy-natural-combo-foods-online': ['sweet-and-snack-combo-pack'] };

async function get(path) {
  const res = await fetch(BASE + path, { headers: { 'User-Agent': UA } });
  if (!res.ok) throw new Error(`${res.status} ${path}`);
  return res.json();
}

const decode = (s) => s
  .replace(/&#8211;/g, '–').replace(/&#8217;/g, '’').replace(/&amp;/g, '&')
  .replace(/&#038;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, "'");

// Their HTML is kept (it is what renders in the tabs), minus their images/iframes.
const cleanHtml = (s) => s.replace(/<img[^>]*>/gi, '').replace(/<iframe[\s\S]*?<\/iframe>/gi, '');

const money = (minor) => (minor === '' || minor == null ? '' : (Number(minor) / 100).toFixed(2));

// Stock is not exposed directly; add_to_cart.maximum is min(stock, 9999).
const stockOf = (p) => (p.add_to_cart && p.add_to_cart.maximum < 9999 ? p.add_to_cart.maximum : null);

async function toSeedProduct(p, categorySlug) {
  const out = {
    source_id: p.id,
    name: decode(p.name),
    slug: p.slug,
    type: p.type,
    sku: p.sku,
    // Its gramiyum.in categories that we also seed (always includes categorySlug).
    categories: [...new Set([categorySlug, ...p.categories.map((c) => c.slug).filter((c) => CATEGORY_SLUGS.includes(c))])],
    image_count: Math.min(p.images.length, 4),
    stock_quantity: stockOf(p),
    tags: p.tags.map((t) => ({ name: decode(t.name), slug: t.slug })),
    regular_price: money(p.prices.regular_price),
    sale_price: p.on_sale ? money(p.prices.sale_price) : '',
    short_description: cleanHtml(p.short_description),
    description: cleanHtml(p.description),
    weight: p.weight,
    attributes: [],
    variations: [],
  };
  if (p.type === 'variable') {
    // Option order and the pre-selected option are only visible in the
    // rendered product page (radio inputs), not in the Store API.
    const page = await (await fetch(p.permalink, { headers: { 'User-Agent': UA } })).text();
    out.default_attributes = {};
    out.attributes = p.attributes
      .filter((a) => a.has_variations)
      .map((a) => {
        const radios = [...page.matchAll(new RegExp(`<input type="radio" name="attribute_${a.taxonomy}" value="([^"]+)"([^>]*)><label[^>]*>([^<]+)</label>`, 'g'))];
        const bySlug = Object.fromEntries(a.terms.map((t) => [t.slug, decode(t.name)]));
        const checked = radios.find((r) => /checked/.test(r[2]));
        if (checked) out.default_attributes[a.taxonomy] = checked[1];
        const options = radios.length ? radios.map((r) => bySlug[r[1]] || decode(r[3])) : a.terms.map((t) => decode(t.name));
        return { name: decode(a.name), taxonomy: a.taxonomy, options };
      });
    for (const v of p.variations) {
      const vp = await get(`/products/${v.id}`);
      out.variations.push({
        source_id: v.id,
        sku: vp.sku,
        attributes: Object.fromEntries(v.attributes.map((a) => [decode(a.name), a.value])),
        regular_price: money(vp.prices.regular_price),
        sale_price: vp.on_sale ? money(vp.prices.sale_price) : '',
        in_stock: vp.is_in_stock,
        stock_quantity: stockOf(vp),
      });
    }
  }
  return out;
}

const allCats = await get('/products/categories?per_page=100');
const bySlug = Object.fromEntries(allCats.map((c) => [c.slug, c]));
const byId = Object.fromEntries(allCats.map((c) => [c.id, c]));

const categories = CATEGORY_SLUGS.map((slug) => {
  const c = bySlug[slug];
  if (!c) throw new Error(`category ${slug} not found`);
  return {
    name: decode(c.name),
    slug: c.slug,
    parent: c.parent ? byId[c.parent].slug : '',
    description: c.description || '',
  };
});

const products = [];
const taken = new Set();
for (const cat of categories) {
  const pinned = PINNED[cat.slug] || [];
  const picks = [];
  for (const slug of pinned) {
    const [p] = await get(`/products?slug=${slug}`);
    if (p) picks.push(p);
  }
  const list = await get(`/products?category=${bySlug[cat.slug].id}&per_page=20&orderby=popularity`);
  for (const p of list) {
    if (picks.length >= PER_CATEGORY) break;
    // Skip products already used elsewhere, and products that also sit in one
    // of our child categories (they belong to the child's list instead).
    if (taken.has(p.slug) || picks.some((x) => x.slug === p.slug)) continue;
    if (!p.is_purchasable || !p.is_in_stock) continue;
    const inOurChild = p.categories.some((pc) => categories.some((c) => c.slug === pc.slug && c.parent === cat.slug));
    if (inOurChild) continue;
    picks.push(p);
  }
  for (const p of picks) {
    taken.add(p.slug);
    products.push(await toSeedProduct(p, cat.slug));
  }
  console.log(`${cat.slug}: ${picks.map((p) => `${p.slug} (${p.type})`).join(', ')}`);
}

await writeFile(new URL('./seed-data.json', import.meta.url), JSON.stringify({ categories, products }, null, 2));
console.log(`wrote ${categories.length} categories, ${products.length} products`);

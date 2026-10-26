/**
 * shop-sim — شبیه‌ساز «پروژه فروشگاه» (کلاینت API آپدیت‌شاپ)
 * ------------------------------------------------------------------
 * این سرویس دقیقاً همان کاری را می‌کند که پروژه فروشگاه شما باید بکند،
 * به‌عنوان مرجع پیاده‌سازی (Laravel) و ابزار تست E2E:
 *
 *   ۱) GET  /                          → لیست پکیج‌ها از API (با is_purchased)
 *   ۲) GET  /buy/:slug?plan=ID&gw=KEY  → POST purchase با callback_url خودمان → ریدایرکت به payment_url
 *   ۳) GET/POST /payment/callback      → درگاه برمی‌گردد → POST verify با هدرهای احراز → لایسنس
 *   ۴) GET  /result?trx=...            → نمایش لایسنس + دکمه دانلود
 *   ۵) GET  /download/:slug            → دانلود ZIP با هدرهای احراز از سمت سرور (proxy)
 *
 * معادل‌های Laravel برای هر بخش در کامنت‌های هر route مشخص شده است.
 */

const PORT = 3001;

// ⚙️ تنظیمات اتصال — معادل config('packages.api') در فروشگاه شما
const PANEL_BASE = process.env.PANEL_BASE ?? 'http://localhost:8000';
const API = `${PANEL_BASE}/api/v1`;
const UPDATE_CODE = process.env.UPDATE_CODE ?? '1F61148198FD';   // معادل packages.api.token
const PROJECT_URL = process.env.PROJECT_URL ?? 'https://ali-shop.ir'; // معادل packages.api.project_key (دامنه پروژه خودتان)
const DEFAULT_GATEWAY = process.env.DEFAULT_GATEWAY ?? 'local';  // درگاه پیش‌فرض خرید (null = پیش‌فرض پنل/zarinpal)
const PUBLIC_BASE = process.env.PUBLIC_BASE ?? `http://localhost:${PORT}`; // آدرس عمومی همین سرویس (برای callback_url)

// 🗄 حافظه وضعیت — معادل جدول installed_modules در فروشگاه شما
// (برای شبیه‌سازی واقعی‌تر، در فایل state.json ذخیره می‌شود — مانند دیتابیس)
type LicenseInfo = {
  slug: string; name: string; license_key: string; expires_at: string | null;
  days_remaining: number | null; is_unlimited: boolean; version: string | null;
  download_token?: string | null; trx?: string; purchased_at: string;
};
const STATE_FILE = `${import.meta.dir}/state.json`;
type PersistedState = { licenses: LicenseInfo[]; trxMap: [string, string][] };
const installedModules = new Map<string, LicenseInfo>(); // key = slug
const trxToSlug = new Map<string, string>();

function loadState() {
  try {
    const raw = JSON.parse(Bun.file(STATE_FILE).text()) as PersistedState;
    (raw.licenses ?? []).forEach((l) => installedModules.set(l.slug, l));
    (raw.trxMap ?? []).forEach(([k, v]) => trxToSlug.set(k, v));
    console.log(`state loaded: ${installedModules.size} licenses, ${trxToSlug.size} transactions`);
  } catch { /* اولین اجرا */ }
}
function saveState() {
  const state: PersistedState = {
    licenses: [...installedModules.values()],
    trxMap: [...trxToSlug.entries()],
  };
  Bun.write(STATE_FILE, JSON.stringify(state, null, 2));
}
loadState();

// 🔧 هلپر احراز هویت API — هدرهایی که هر درخواست سمت سرور باید بفرستد
function apiHeaders(extra: Record<string, string> = {}): Record<string, string> {
  return {
    'Authorization': `Bearer ${UPDATE_CODE}`,
    'X-Project-Url': PROJECT_URL,
    'Accept': 'application/json',
    ...extra,
  };
}

async function apiFetch(path: string, init: RequestInit = {}) {
  const res = await fetch(`${API}${path}`, {
    ...init,
    headers: apiHeaders((init.headers as Record<string, string>) ?? {}),
  });
  const text = await res.text();
  let json: any = null;
  try { json = JSON.parse(text); } catch { /* not json */ }
  return { res, json, text };
}

// ---------------------------------------------------------------- HTML shell
const CSS = `
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Tahoma, Arial, sans-serif; background: #f4f6f9; color: #1f2937; min-height: 100vh; display: flex; flex-direction: column; }
  .topbar { background: #0f766e; color: #fff; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 10; }
  .topbar .brand { font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px; }
  .topbar .tag { background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3); border-radius: 999px; font-size: 11px; padding: 3px 10px; }
  main { max-width: 900px; width: 100%; margin: 0 auto; padding: 24px 16px; flex: 1; }
  .hero { background: #fff; border-radius: 14px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-bottom: 20px; border-right: 4px solid #0f766e; }
  .hero h1 { font-size: 18px; margin-bottom: 6px; }
  .hero p { font-size: 13px; color: #6b7280; line-height: 2; }
  .hero code { background: #f3f4f6; padding: 1px 6px; border-radius: 4px; font-size: 11.5px; direction: ltr; display: inline-block; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
  .card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.06); display: flex; flex-direction: column; }
  .card-head { padding: 14px 16px 10px; border-bottom: 1px solid #f3f4f6; }
  .card-head h3 { font-size: 15px; }
  .card-head .slug { font-size: 11px; color: #9ca3af; direction: ltr; text-align: left; }
  .card-body { padding: 12px 16px; flex: 1; }
  .plan { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; font-size: 13px; border-bottom: 1px dashed #f3f4f6; }
  .plan:last-child { border: 0; }
  .plan .price { font-weight: 700; color: #0f766e; }
  .plan .price.free { color: #059669; }
  .btn { display: inline-block; text-align: center; border: 0; border-radius: 8px; padding: 9px 14px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
  .btn-buy { background: #0f766e; color: #fff; }
  .btn-buy:hover { background: #115e59; }
  .btn-dl { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
  .btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 6px; }
  .card-foot { padding: 12px 16px; border-top: 1px solid #f3f4f6; background: #fafafa; }
  .card-foot .btn { width: 100%; }
  .badge { display: inline-block; border-radius: 999px; padding: 2px 10px; font-size: 11px; font-weight: 700; }
  .badge-ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
  .badge-exp { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
  .license-box { background: #0f172a; color: #a5f3fc; border-radius: 8px; padding: 12px 14px; direction: ltr; text-align: left; font-family: monospace; font-size: 13px; margin: 8px 0; word-break: break-all; }
  .result { max-width: 560px; margin: 40px auto; }
  .result-card { background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
  .result-head { padding: 22px; text-align: center; color: #fff; }
  .result-head.ok { background: linear-gradient(135deg, #059669, #047857); }
  .result-head.fail { background: linear-gradient(135deg, #dc2626, #b91c1c); }
  .result-head .icon { font-size: 40px; }
  .result-head h2 { font-size: 17px; margin-top: 8px; }
  .result-body { padding: 22px; }
  .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #f3f4f6; font-size: 13.5px; }
  .row .k { color: #6b7280; }
  .row .v { font-weight: 700; }
  .result-note { margin-top: 14px; background: #f0fdfa; border: 1px solid #99f6e4; color: #115e59; border-radius: 8px; padding: 12px; font-size: 12.5px; line-height: 2; }
  .alert { border-radius: 10px; padding: 14px; margin-bottom: 16px; font-size: 13.5px; line-height: 1.9; }
  .alert-err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
  .alert-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
  footer { background: #111827; color: #6b7280; text-align: center; padding: 14px; font-size: 11.5px; margin-top: auto; }
  .empty { text-align: center; padding: 60px 0; color: #9ca3af; }
  .spinner-msg { text-align: center; padding: 50px 0; color: #6b7280; }
  /* انتخابگر درگاه پرداخت — کارت با لوگو و عنوان */
  .gw-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; margin-top: 10px; }
  .gw-card { position: relative; display: flex; flex-direction: column; align-items: center; gap: 8px; background: #fff; border: 2px solid #e5e7eb; border-radius: 14px; padding: 14px 10px 12px; cursor: pointer; transition: all .15s ease; }
  .gw-card:hover { border-color: #9ca3af; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.07); }
  .gw-card.selected { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.18); }
  .gw-card input { position: absolute; opacity: 0; pointer-events: none; }
  .gw-logo { width: 120px; height: 48px; display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #eef0f3; border-radius: 10px; overflow: hidden; }
  .gw-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
  .gw-logo .fallback { font-size: 22px; }
  .gw-name { font-size: 12.5px; font-weight: 700; color: #374151; display: flex; align-items: center; gap: 5px; text-align: center; }
  .gw-test { background: #fef3c7; color: #92400e; border-radius: 6px; padding: 1px 6px; font-size: 9.5px; font-weight: 800; }
  .gw-check { position: absolute; top: 8px; right: 8px; width: 18px; height: 18px; border-radius: 50%; background: #0f766e; color: #fff; display: none; align-items: center; justify-content: center; font-size: 11px; }
  .gw-card.selected .gw-check { display: flex; }
  .checkout-summary { display: flex; justify-content: space-between; align-items: center; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 10px; padding: 12px 16px; margin: 14px 0; font-size: 13.5px; }
  .checkout-summary .amount { font-size: 16px; font-weight: 800; color: #0f766e; }
`;

function page(title: string, body: string, flash?: { type: 'ok' | 'err', msg: string }) {
  const flashHtml = flash
    ? `<div class="alert ${flash.type === 'ok' ? 'alert-ok' : 'alert-err'}">${flash.msg}</div>`
    : '';
  return `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title} — شبیه‌ساز فروشگاه</title>
<style>${CSS}</style>
</head>
<body>
  <div class="topbar">
    <div class="brand">🛒 شبیه‌ساز پروژه فروشگاه <span class="tag">shop-sim</span></div>
    <div class="tag" dir="ltr">${PROJECT_URL} → ${PANEL_BASE}</div>
  </div>
  <main>${flashHtml}${body}</main>
  <footer>مرجع پیاده‌سازی یکپارچه‌سازی API آپدیت‌شاپ — خرید درگاه‌دار، تأیید، لایسنس و دانلود</footer>
</body>
</html>`;
}

const fa = (n: number | null | undefined) =>
  n === null || n === undefined ? '-' : n.toLocaleString('fa-IR');

// ---------------------------------------------------------------- 1) لیست پکیج‌ها
// معادل Laravel: PackageApiService::listPackages() → صفحه «پکیج‌ها» در پنل فروشگاه
async function listPage(): Promise<Response> {
  const { json, res } = await apiFetch('/packages?per_page=50');
  if (!res.ok || !json?.data) {
    return new Response(page('خطا', `<div class="empty">⚠️ خطا در دریافت پکیج‌ها از API:<br><code>${JSON.stringify(json)}</code></div>`), { status: 502, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }

  const cards = (json.data as any[]).map((p) => {
    // اطلاعات لایسنس: اولویت با پاسخ API (purchased_license) — دقیقاً همان چیزی که فروشگاه واقعی استفاده می‌کند
    const apiLic: any = p.purchased_license ?? p.installed_license ?? null;
    const lic = installedModules.get(p.slug) ?? (apiLic ? {
      slug: p.slug, name: p.name, license_key: apiLic.license_key,
      expires_at: apiLic.expires_at ?? null, days_remaining: apiLic.days_remaining ?? null,
      is_unlimited: apiLic.is_unlimited ?? !apiLic.expires_at, version: null,
      purchased_at: '',
    } : undefined);
    const daysLeft: number | null | undefined = apiLic?.days_remaining ?? lic?.days_remaining;
    const unlimited = apiLic ? (apiLic.is_unlimited ?? !apiLic.expires_at) : lic?.is_unlimited;
    // حقیقت = پاسخ API (is_purchased) — state محلی فقط مکمل است
    const purchased = p.is_purchased === true;
    const plans: string[] = (p.active_pricing_plans ?? []).map((pl: any) => {
      const price = Number(pl.final_price ?? pl.discount_price ?? pl.price ?? 0);
      const free = price <= 0 || p.is_free;
      return `<div class="plan">
        <span>${pl.name}</span>
        <span class="price ${free ? 'free' : ''}">${free ? 'رایگان' : fa(price) + ' ریال'}</span>
        ${purchased ? '' : `<a class="btn btn-buy btn-sm" href="/checkout/${p.slug}?plan=${pl.id}">خرید</a>`}
      </div>`;
    }).join('');

    const licBadge = purchased
      ? `<span class="badge ${daysLeft != null && daysLeft < 15 ? 'badge-exp' : 'badge-ok'}">✓ خریداری‌شده${daysLeft != null ? ` — ${fa(daysLeft)} روز مانده` : (unlimited ? ' — نامحدود' : '')}</span>`
      : `<span class="badge" style="background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb">خریداری نشده</span>`;

    return `<div class="card">
      <div class="card-head">
        <h3>${p.name}</h3>
        <div class="slug">${p.slug}</div>
        <div style="margin-top:6px">${licBadge}</div>
      </div>
      <div class="card-body">${plans || '<div class="plan"><span>طرحی ثبت نشده</span></div>'}</div>
      <div class="card-foot">
        ${purchased
          ? `<a class="btn btn-dl" href="/download/${p.slug}">⬇ دانلود پکیج${lic?.version ? ` (نسخه ${lic.version})` : ''}</a>`
          : `<span style="font-size:12px;color:#9ca3af;display:block;text-align:center">برای دانلود، ابتدا خرید کنید</span>`}
      </div>
    </div>`;
  }).join('');

  const body = `
    <div class="hero">
      <h1>📦 پکیج‌های قابل نصب در پروژه شما</h1>
      <p>
        این صفحه داده‌ها را از <code>GET /api/v1/packages</code> می‌گیرد و بر اساس فیلد
        <code>is_purchased</code> دکمه «خرید» یا «دانلود» نشان می‌دهد.
        خرید با <code>POST /api/v1/packages/{slug}/purchase</code> انجام می‌شود و
        <code>callback_url</code> به همین سرویس اشاره می‌کند.
      </p>
    </div>
    ${cards ? `<div class="grid">${cards}</div>` : '<div class="empty">پکیجی برای این پروژه ثبت نشده است.</div>'}`;

  return new Response(page('پکیج‌ها', body), { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

// ---------------------------------------------------------------- 2a) انتخاب درگاه (چک‌اوت)
// معادل Laravel: صفحه «انتخاب درگاه پرداخت» در فروشگاه خریدار —
// درگاه‌ها (کلید/عنوان/لوگو) از بلوک gateways در GET /api/v1/packages می‌آیند.
async function checkoutPage(slug: string, planId: number): Promise<Response> {
  const { json, res } = await apiFetch('/packages?per_page=50');

  if (!res.ok || !json?.data) {
    return new Response(page('خطا', `<div class="empty">⚠️ خطا در دریافت اطلاعات خرید.</div>`), { status: 502, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }

  const pkg = (json.data as any[]).find((p) => p.slug === slug);

  if (!pkg) {
    return new Response(page('خطا', `<div class="empty">پکیج یافت نشد.</div>`), { status: 404, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }

  const plan = (pkg.active_pricing_plans ?? []).find((pl: any) => Number(pl.id) === planId);
  const price = plan ? Number(plan.final_price ?? plan.discount_price ?? plan.price ?? 0) : Number(pkg.default_price ?? 0);
  const isFree = price <= 0 || pkg.is_free;
  const gateways: any[] = (json.gateways ?? []).filter((g) => !g.is_test || DEFAULT_GATEWAY === 'local');

  if (isFree) {
    // رایگان → مستقیم خرید بدون درگاه
    return new Response(null, { status: 302, headers: { Location: `/buy/${slug}?plan=${planId}` } });
  }

  if (!gateways.length) {
    return new Response(page('خطا', `<div class="result"><div class="alert alert-err">درگاه پرداخت فعالی از سمت پنل تنظیم نشده است.</div><a class="btn btn-buy" href="/">بازگشت</a></div>`), { status: 502, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }

  const defaultGw = gateways.some((g) => g.key === DEFAULT_GATEWAY) ? DEFAULT_GATEWAY : (gateways[0]?.key ?? '');

  const cards = gateways.map((g, i) => `
    <label class="gw-card ${g.key === defaultGw ? 'selected' : ''}">
      <input type="radio" name="gw" value="${g.key}" ${g.key === defaultGw ? 'checked' : ''} />
      <span class="gw-check">✓</span>
      <span class="gw-logo">
        ${g.logo
          ? `<img src="${g.logo}" alt="${g.title}" loading="lazy" onerror="this.outerHTML='<span class=\"fallback\">💳</span>'">`
          : '<span class="fallback">💳</span>'}
      </span>
      <span class="gw-name">${g.title ?? g.key}${g.is_test ? ' <span class="gw-test">تست</span>' : ''}</span>
    </label>`).join('');

  const body = `
    <div class="hero">
      <h1>💳 انتخاب درگاه پرداخت</h1>
      <p>پرداخت امن از طریق درگاه‌های زیر انجام می‌شود. درگاه‌ها (عنوان، لوگو و کلید) از بلوک
      <code>gateways</code> در <code>GET /api/v1/packages</code> می‌آیند — فروشگاه شما هم همین‌گونه نمایش بسازید.</p>
    </div>
    <div class="result" style="max-width:720px">
      <div class="result-card">
        <div class="result-body">
          <div class="row"><span class="k">پکیج</span><span class="v">${pkg.name} <span dir="ltr" style="color:#9ca3af;font-size:11px">${pkg.slug}</span></span></div>
          ${plan ? `<div class="row"><span class="k">طرح</span><span class="v">${plan.name}</span></div>` : ''}
          <div class="checkout-summary">
            <span>مبلغ قابل پرداخت</span>
            <span class="amount">${fa(price)} تومان</span>
          </div>

          <p style="font-size:13px;font-weight:700;margin:10px 0 2px">درگاه پرداخت را انتخاب کنید</p>
          <div class="gw-grid">${cards}</div>

          <div style="margin-top:18px;display:flex;gap:10px">
            <button id="payBtn" class="btn btn-buy" style="flex:1">ادامه و پرداخت</button>
            <a class="btn" style="background:#f3f4f6;color:#374151" href="/">انصراف</a>
          </div>
          <p style="font-size:11.5px;color:#9ca3af;margin-top:10px">پس از پرداخت به فروشگاه بازمی‌گردید و لایسنس به‌صورت خودکار صادر می‌شود.</p>
        </div>
      </div>
    </div>
    <script>
      // انتخاب کارت درگاه
      document.querySelectorAll('.gw-card').forEach((card) => {
        card.addEventListener('click', () => {
          document.querySelectorAll('.gw-card').forEach((c) => c.classList.remove('selected'));
          card.classList.add('selected');
          card.querySelector('input').checked = true;
        });
      });
      document.getElementById('payBtn').addEventListener('click', () => {
        const gw = document.querySelector('input[name=gw]:checked')?.value ?? '';
        const url = '/buy/${encodeURIComponent(slug)}?plan=${planId}' + (gw ? '&gw=' + encodeURIComponent(gw) : '');
        window.location.href = url;
      });
    </script>`;

  return new Response(page('انتخاب درگاه پرداخت', body), { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

// ---------------------------------------------------------------- 2) شروع خرید
// معادل Laravel: PackagePaymentController@buy → PackageApiService::purchase()
async function buyPage(slug: string, planId: number, gateway: string | null): Promise<Response> {
  const callbackUrl = `${PUBLIC_BASE}/payment/callback`; // ← آدرس برگشت درگاه، روی فروشگاه خودتان

  const { json, res } = await apiFetch(`/packages/${encodeURIComponent(slug)}/purchase`, {
    method: 'POST',
    headers: apiHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({
      callback_url: callbackUrl,
      pricing_plan_id: planId,
      ...(gateway ? { gateway } : {}),
    }),
  });

  // مسیر رایگان → لایسنس فوری بدون درگاه
  if (json?.is_free) {
    const info: LicenseInfo = {
      slug, name: slug, license_key: json.license_key, expires_at: json.expires_at ?? null,
      days_remaining: null, is_unlimited: !json.expires_at, version: null,
      download_token: json.download_token, purchased_at: new Date().toISOString(),
    };
    installedModules.set(slug, info);
    saveState();
    return new Response(null, { status: 302, headers: { Location: `/result?free=1&slug=${encodeURIComponent(slug)}` } });
  }

  if (!res.ok || !json?.payment_url) {
    const errMsg = json?.error ?? `HTTP ${res.status}`;
    return new Response(page('خطای خرید', `<div class="result"><div class="alert alert-err">شروع پرداخت ناموفق بود:<br><b>${errMsg}</b></div><a class="btn btn-buy" href="/">بازگشت به لیست</a></div>`), { status: 502, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }

  // ثبت تراکنش برای کال‌بک — معادل جدول purchased_packages در فروشگاه شما
  // نکته: در جریان جدیدِ «صفحه تسویه»، transaction_id هنوز صادر نشده (null) و
  // تراکنش واقعی پس از تأیید کاربر در صفحه تسویه ساخته می‌شود؛ شناسه واقعی
  // از طریق پارامترهای ریدایرکت کال‌بک به فروشگاه می‌رسد.
  if (json.transaction_id) {
    trxToSlug.set(String(json.transaction_id), slug);
  }
  saveState();

  // ریدایرکت مرورگر به صفحه تسویه پنل (جریان جدید): کاربر آنجا سفارش را می‌بیند،
  // کد تخفیف وارد می‌کند و با «پرداخت» وارد درگاه می‌شود یا با «انصراف» برمی‌گردد
  return new Response(null, { status: 302, headers: { Location: json.payment_url } });
}

// ---------------------------------------------------------------- 3) کال‌بک درگاه
// معادل Laravel: PackagePaymentController@callback (web route بدون CSRF) → PackageApiService::verifyPayment()
async function callbackPage(url: URL, method: string): Promise<Response> {
  const trx = url.searchParams.get('transactionId')
    ?? url.searchParams.get('transaction_id')
    ?? url.searchParams.get('Authority');
  const cancel = url.searchParams.get('cancel') === 'true';

  // انصراف کاربر در صفحه تسویه پنل (status=canceled) — قبل از ساخت تراکنش
  if (!trx && url.searchParams.get('status') === 'canceled') {
    return new Response(null, {
      status: 302,
      headers: { Location: `/result?canceled=1` },
    });
  }

  if (!trx) {
    return new Response(page('خطا', `<div class="result"><div class="alert alert-err">پارامتر تراکنش در بازگشت درگاه یافت نشد.</div><a class="btn btn-buy" href="/">بازگشت</a></div>`), { status: 400, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }

  const slug = trxToSlug.get(trx);

  // ⚠️ نکته: برای درایور آزمایشی local باید transactionId به‌صورت query هم ارسال شود (رفتار درایور).
  const q = new URLSearchParams({ transactionId: trx });
  if (cancel) q.set('cancel', 'true');

  const { json, res } = await apiFetch(`/payments/${encodeURIComponent(trx)}/verify?${q.toString()}`, {
    method: 'POST',
    headers: apiHeaders(),
  });

  if (json?.paid) {
    if (slug) {
      // ذخیره لایسنس — معادل رکورد installed_modules (slug + license_key + license_expires_at)
      installedModules.set(slug, {
        slug,
        name: slug,
        license_key: json.license_key,
        expires_at: json.expires_at ?? null,
        days_remaining: json.days_remaining ?? null,
        is_unlimited: !json.expires_at,
        version: json.version ?? null,
        download_token: json.download_token ?? null,
        trx,
        purchased_at: new Date().toISOString(),
      });
      saveState();
    }
    return new Response(null, { status: 302, headers: { Location: `/result?trx=${encodeURIComponent(trx)}&ok=1` } });
  }

  const reason = json?.error ?? json?.message ?? `HTTP ${res.status}`;
  return new Response(null, { status: 302, headers: { Location: `/result?trx=${encodeURIComponent(trx)}&fail=${encodeURIComponent(reason)}` } });
}

// ---------------------------------------------------------------- 4) نتیجه
function resultPage(url: URL): Response {
  const ok = url.searchParams.get('ok') === '1';
  const free = url.searchParams.get('free') === '1';
  const canceled = url.searchParams.get('canceled') === '1';
  const trx = url.searchParams.get('trx');
  const fail = url.searchParams.get('fail');
  const slug = url.searchParams.get('slug') ?? (trx ? trxToSlug.get(trx) : undefined);
  const lic = slug ? installedModules.get(slug) : undefined;

  // انصراف کاربر در صفحه تسویه پنل
  if (canceled) {
    const body = `<div class="result">
      <div class="result-card">
        <div class="result-head fail" style="background:#fffbeb">
          <div class="icon" style="background:#fef3c7">🚫</div>
          <h2 style="color:#92400e">خرید لغو شد</h2>
        </div>
        <div class="result-body">
          <div class="alert" style="background:#fef9c3;color:#854d0e;border:1px solid #fde047">شما در صفحه تسویه پنل از خرید انصراف دادید. مبلغی از حساب شما کسر نشده است.</div>
          <div style="margin-top:16px"><a class="btn btn-buy" href="/">تلاش مجدد از لیست پکیج‌ها</a></div>
        </div>
      </div>
    </div>`;
    return new Response(page('نتیجه خرید', body), { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }

  if (ok || free) {
    const body = `<div class="result">
      <div class="result-card">
        <div class="result-head ok">
          <div class="icon">✅</div>
          <h2>${free ? 'پکیج رایگان فعال شد' : 'پرداخت با موفقیت تأیید شد'}</h2>
        </div>
        <div class="result-body">
          <div class="row"><span class="k">پکیج</span><span class="v">${lic?.name ?? slug}</span></div>
          <div class="row"><span class="k">کلید لایسنس</span></div>
          <div class="license-box">${lic?.license_key ?? '-'}</div>
          <div class="row"><span class="k">تاریخ انقضا</span><span class="v">${lic?.is_unlimited ? 'نامحدود' : (lic?.expires_at ?? '-')}</span></div>
          ${lic?.days_remaining != null ? `<div class="row"><span class="k">روز باقی‌مانده</span><span class="v">${fa(lic.days_remaining)}</span></div>` : ''}
          ${lic?.version ? `<div class="row"><span class="k">نسخه</span><span class="v" dir="ltr">${lic.version}</span></div>` : ''}
          <div style="margin-top:16px;display:grid;gap:10px">
            <a class="btn btn-dl" href="/download/${lic?.slug ?? slug}">⬇ دانلود و نصب پکیج</a>
            <a class="btn" style="background:#f3f4f6;color:#374151" href="/">بازگشت به لیست پکیج‌ها</a>
          </div>
          <div class="result-note">
            💡 در فروشگاه واقعی: این لایسنس در جدول <b>installed_modules</b> ذخیره می‌شود،
            فایل ZIP دانلود و در پوشه <b>Modules/</b> نصب می‌شود و
            <b>LicenseGuard</b> به‌صورت دوره‌ای اعتبار آن را از <b>verify-license</b> چک می‌کند.
          </div>
        </div>
      </div>
    </div>`;
    return new Response(page('نتیجه خرید', body), { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }

  const body = `<div class="result">
    <div class="result-card">
      <div class="result-head fail">
        <div class="icon">❌</div>
        <h2>پرداخت ناموفق بود</h2>
      </div>
      <div class="result-body">
        <div class="alert alert-err">${fail ?? 'پرداخت تأیید نشد.'}</div>
        <div class="row"><span class="k">شماره تراکنش</span><span class="v" dir="ltr">${trx ?? '-'}</span></div>
        <div style="margin-top:16px"><a class="btn btn-buy" href="/">تلاش مجدد از لیست پکیج‌ها</a></div>
      </div>
    </div>
  </div>`;
  return new Response(page('نتیجه خرید', body), { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

// ---------------------------------------------------------------- 5) دانلود
// معادل Laravel: PackageApiService::downloadPackage() — دانلود سمت سرور با هدرهای احراز
async function downloadPage(slug: string): Promise<Response> {
  // اعمال لایسنس سمت پنل انجام می‌شود (403 فارسی اگر لایسنس فعال نباشد) —
  // دقیقاً مانند downloadPackage() در کیت لاراول
  const res = await fetch(`${API}/packages/${encodeURIComponent(slug)}/download`, {
    headers: apiHeaders(),
  });

  if (!res.ok) {
    let msg = `HTTP ${res.status}`;
    try { msg = (await res.json())?.error ?? msg; } catch { /* not json */ }
    return new Response(page('خطای دانلود', `<div class="result"><div class="alert alert-err">دانلود ناموفق:<br><b>${msg}</b></div><a class="btn btn-buy" href="/">بازگشت</a></div>`), { status: 502, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }

  const zip = await res.arrayBuffer();
  const version = res.headers.get('X-Package-Version') ?? '';
  return new Response(zip, {
    headers: {
      'Content-Type': 'application/zip',
      'Content-Disposition': `attachment; filename="${slug}-${version || 'package'}.zip"`,
      'Content-Length': String(zip.byteLength),
    },
  });
}

// ---------------------------------------------------------------- سرور
const server = Bun.serve({
  port: PORT,
  async fetch(req) {
    const url = new URL(req.url);
    const path = decodeURIComponent(url.pathname);

    try {
      if (path === '/' || path === '/packages') return await listPage();

      if (path.startsWith('/checkout/')) {
        const slug = path.slice(10).replace(/\/$/, '');
        const plan = Number(url.searchParams.get('plan'));
        if (!slug || !plan) return new Response('missing plan', { status: 400 });
        return await checkoutPage(slug, plan);
      }

      if (path.startsWith('/buy/')) {
        const slug = path.slice(5).replace(/\/$/, '');
        const plan = Number(url.searchParams.get('plan'));
        const gw = url.searchParams.get('gw') ?? DEFAULT_GATEWAY;
        if (!slug || !plan) return new Response('missing plan', { status: 400 });
        return await buyPage(slug, plan, gw || null);
      }

      if (path === '/payment/callback' && (req.method === 'GET' || req.method === 'POST')) {
        return await callbackPage(url, req.method);
      }

      if (path === '/result') return resultPage(url);

      if (path.startsWith('/download/')) {
        const slug = path.slice(10).replace(/\/$/, '');
        return await downloadPage(slug);
      }

      return new Response(page('۴۰۴', '<div class="empty">مسیر یافت نشد.</div>'), { status: 404, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
    } catch (e: any) {
      return new Response(page('خطای سرور', `<div class="result"><div class="alert alert-err">${e?.message ?? 'unknown'}</div></div>`), { status: 500, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
    }
  },
});

console.log(`shop-sim listening on :${PORT} → panel ${PANEL_BASE}`);

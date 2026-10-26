# OpShop Updater — REST API v1 · Storefront Build Specification

> **یادداشت فارسی (برای صاحب پروژه):** این فایل یک «دستور ساخت» کامل و مستقل است. آن را به هر
> ایجنت هوش مصنوعی (Claude، GPT، Cursor و…) بدهید تا سمتِ فروشگاهی (پروژهٔ خریدار) را کامل بسازد.
> ایجنت نباید به هیچ سورس دیگری نیاز داشته باشد — همهٔ قرارداد API، جریان‌ها، خطاها و نمونه‌کدها همین‌جاست.
>
> **English note (for the AI agent):** This document is a self-contained build specification.
> Read it fully before writing any code. Everything you need to integrate with the OpShop
> Updater panel is here: authentication rules, all 25 endpoints with real request/response
> schemas, end-to-end flows, error handling, security requirements, and working code examples.

---

## 1. Mission Brief — READ THIS FIRST

You are building a **complete customer-facing storefront** (the "client project", e.g. a
WordPress plugin, a Laravel site, or any web app) that consumes the **OpShop Updater panel's
REST API v1**. The panel is the server; your storefront is the client.

The panel already handles:

- Customer accounts and their secret API token (`update_code`)
- Product catalog storage (packages, templates, categories)
- Payment gateways (Zarinpal and others) and transaction verification
- License issuing and validation
- Versioned ZIP file storage and secure one-time download links
- SMS notifications

Your storefront must handle:

1. **Catalog pages** — list packages/templates/categories from the API
2. **Product detail pages** — versions, pricing plans, purchase state
3. **Checkout** — pricing plan + gateway selection, then redirect the user to `payment_url`
   (for paid packages this is now the panel's **checkout page**: the user reviews the order,
   can apply a discount code, and pays or cancels there)
4. **Payment result page** — receive the user back via `callback_url` and verify via API
5. **Licenses & downloads area** — show licenses, generate one-time download links
6. **Auto-update module** — `verify-license` → `check-update` → download → SHA-256 check

### Hard rules (violating any of these = failed build)

- **Never** expose the API token (`update_code`) in browser-visible code. All API calls happen
  server-side (PHP backend, Node backend, etc.), never from client-side JavaScript.
- **Never** store license keys or the token in public repos or client-side storage.
- **Always** verify downloaded files with the returned SHA-256 hash before installing.
- **Always** send both auth headers (`Authorization` + `X-Project-Url`) on protected endpoints.
- The storefront UI must be **fully RTL and Persian** (the platform's users are Persian speakers).

---

## 2. Glossary

| Term | Meaning |
|---|---|
| **Panel** | The OpShop Updater server that exposes this API (`https://<panel-host>/api/v1`) |
| **Customer** | The buyer account on the panel. One customer owns one `update_code` |
| **`update_code`** | The customer's secret API token. Sent as `Authorization: Bearer {update_code}` |
| **Package** | A sellable software item (plugin/module) with versions, pricing plans and licenses |
| **Template** | A sellable website theme. No licensing; normal vs. exclusive purchase |
| **Subscription Plan** | A recurring plan; its included packages become free while active |
| **Pricing Plan** | One price option of a package (`pricing_plan_id`) |
| **License** | Per-customer, per-package key with an expiry date (`LIC-XXXX-XXXX`) |
| **Transaction** | A payment identified by `transaction_id` (`tr_xxxxxxxx`) |
| **Gateway** | A payment provider (`zarinpal`, `local` for testing, …) |
| **Download token** | One-time, 15-minute URL token (`dl_...`) that streams the ZIP without auth headers |

---

## 3. Base URL & Conventions

```
BASE = https://<panel-host>/api/v1
```

- All responses are **JSON, UTF-8** (except binary downloads).
- Success = HTTP 200 with a JSON body.
- Errors = HTTP 4xx/5xx with `{"error": "پیام فارسی"}` — the message is Persian and safe to
  display to end users as-is.
- Pagination lives in `meta`: `{"current_page": 1, "last_page": 1, "total": 1, "per_page": 15}`.
  Default `per_page` is 15 (`?per_page=` on list endpoints).
- **Rate limit:** 120 requests/minute per IP across the whole API. Exceeding it → HTTP 429.
  Use exponential backoff on 429.
- Money amounts are integers in **Rials** (IRR), e.g. `980000`.

---

## 4. Authentication (exact rules)

The token is the customer's `update_code`. Three accepted forms, by priority:

| Priority | How | Notes |
|---|---|---|
| 1 (recommended) | `Authorization: Bearer {update_code}` | Use this everywhere |
| 2 | `X-Project-Key: {update_code}` | Alternative header |
| 3 | `?token={update_code}` | Query param — used by the legacy endpoints (§5.5) |

**Domain header (mandatory on all protected endpoints):**

```
X-Project-Url: https://your-site.com
```

Matching rules (implemented server-side in `PackageApiAuthService`):

- The **host** is extracted from the header (scheme, path and `www.` are stripped).
- It must equal the customer's registered website URL on the panel, **or** be a subdomain of it
  (`shop.example.com` is accepted when `example.com` is registered).
- Missing header → 403 `"هدر دامنه (X-Project-Url) ارسال نشده است."`
- Mismatch → 403 `"دسترسی از دامنه {host} مجاز نیست."`
- Invalid/disabled customer → 403 `"مشتری نامعتبر است."` / `"حساب مشتری غیرفعال است."`

> **Agent note:** make the base URL, token and project URL configuration values (`.env` /
> settings), never hard-coded. On token rejection (403), show an actionable message: the store
> owner must check the token or set the panel customer's "Website URL" to this domain.

---

## 5. Endpoint Reference (25 endpoints)

### 5.1 Packages (9)

#### `GET /packages` — list available packages

Returns active packages of projects the customer's subscription covers.

Query params: `search` (string, optional) · `category` (string slug, optional) · `per_page` (int, default 15)

```json
{
  "data": [
    {
      "id": 1,
      "name": "پکیج فروشگاهی",
      "slug": "shop-package",
      "short_description": "...",
      "thumbnail_url": "https://panel.example.com/uploads/packages/thumbnails/shop_thumb.png",
      "images": [
        { "id": 11, "url": "https://panel.example.com/uploads/packages/gallery/shop_1.png", "sort_order": 1 }
      ],
      "is_free": false,
      "is_purchased": true,
      "purchases_count": 12,
      "purchased_license": {
        "license_key": "LIC-XXXX-XXXX",
        "expires_at": "2026-05-01 00:00:00",
        "days_remaining": 92,
        "is_unlimited": false
      },
      "subscription": null,
      "category_details": {
        "id": 3, "name": "افزونه", "slug": "plugins",
        "parents": [{ "id": 1, "name": "وردپرس", "slug": "wordpress" }],
        "path": "وردپرس › افزونه",
        "path_slugs": ["wordpress", "plugins"]
      },
      "latest_version": { "version": "2.1.0", "file_size": 48219340 }
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1, "per_page": 15 },
  "gateways": [
    { "key": "zarinpal", "title": "زرین‌پال", "logo": "https://...", "is_test": false }
  ],
  "subscription_summary": {
    "has_active_subscription": true,
    "plan_name": "طرح طلایی",
    "expires_at": "2026-06-01 00:00:00",
    "days_remaining": 120
  }
}
```

UI mapping: `is_purchased=false` → "خرید" button; `true` → "دانلود". Use `category_details.path_slugs`
to build category trees/filters. Show `gateways` as selectable payment cards at checkout.

**Images are always absolute URLs** — `thumbnail_url` (featured image) and each item of `images`
(gallery, active only) are full `https://` links on the panel domain, so the buyer site can render
them directly with `<img src>` without building any path. Display them in the order of `sort_order`.

#### `GET /packages/{slug}` — package details

Full info: `active_versions` (list of `{version, ...}`), `active_pricing_plans`
(`[{id, title, final_price, is_one_time}]`), `thumbnail_url` + `images` (gallery with absolute
URLs, active only), purchase state. Requires the customer's
subscription to cover the package's project, otherwise 403.

#### `POST /packages/{slug}/purchase` — buy / issue license

Body (JSON): `pricing_plan_id` (int, required for paid) · `callback_url` (url, required for
paid) · `gateway` (string, optional — panel default if omitted, `local` for tests).

**Three outcomes:**

1. **Paid** → `200 {"checkout_url": "https://panel/checkout/package/42?expires=...&signature=...", "payment_url": "<same as checkout_url>", "transaction_id": null, "amount": 980000, "gateway": "zarinpal", "purchase_id": 42}`
   → redirect the user to `payment_url`. It now points to the panel's **checkout page** (signed
   link, valid 24h): the user sees the package name/image, the selected plan, the amount, and a
   **discount code field** (apply / remove), then clicks «پرداخت» (goes to the gateway — the
   gateway transaction is created at that moment, so `transaction_id` is issued only after the
   user confirms) or «انصراف» (order is canceled and the user is sent back to your
   `callback_url?status=canceled`). Keep backward compatibility: just follow `payment_url` as
   before — no change needed in your code.
   Note: `transaction_id` is `null` in this response; your project receives the real
   `transaction_id` via the `callback_url` redirect parameters after payment (unchanged).
2. **Free** (`package.is_free` or plan price 0) →
   `200 {"is_free": true, "license_key": "...", "expires_at": "...", "download_token": "dl_..."}`
3. **Covered by active subscription** (idempotent) →
   `200 {"is_free": true, "via_subscription": true, "plan": "طرح طلایی", "license_key": "...", "expires_at": "...", "days_remaining": 30, "download_token": "dl_...", "message": "این پکیج با اشتراک فعال شما رایگان است."}`

Validation errors: 422 `{"error": "انتخاب طرح قیمت‌گذاری الزامی است."}` (missing plan),
`{"error": "آدرس بازگشت (callback_url) برای پرداخت الزامی است."}` (missing callback),
invalid gateway → 422, one-time plan already used → 422.

#### `POST /payments/{transactionId}/verify` — verify payment

Call after the user returns from the gateway. If the purchase is still `pending`, the panel
attempts gateway verification itself.

- Success → `{"paid": true, "license_key": "...", "expires_at": "...", "days_remaining": 365, "download_token": "dl_...", "signature": "<sha256>", "version": "2.1.0"}`
- Not verified yet → 400 `{"paid": false, "error": "پرداخت هنوز تأیید نشده است."}` — poll with backoff.
- Unknown transaction → 404.

`signature` = SHA-256 of the latest version's ZIP.

#### `POST /packages/{slug}/verify-license` — validate license

Body: `{"license_key": "LIC-XXXX-XXXX"}`. Call on every app run of the client project.

```json
{
  "valid": true,
  "expires_at": "2026-05-01 00:00:00",
  "days_remaining": 92,
  "is_unlimited": false,
  "version": "2.1.0",
  "signature": "e3b0c442...",
  "download_token": "dl_a1b2c3d4..."
}
```

Fresh `download_token` is included on every valid check. Invalid license → `{"valid": false, ...}`.

#### `GET /packages/{slug}/check-update?current_version=1.0.0` — compare versions

```json
{
  "has_update": true,
  "latest_version": "2.1.0",
  "current_version": "1.0.0",
  "is_mandatory": false,
  "changelog": "نسخهٔ ۲.۱.۰ — ...",
  "what_added": "...",
  "what_changed": "...",
  "what_fixed": "...",
  "min_php_version": "8.2",
  "min_laravel_version": "11",
  "dependencies": "...",
  "file_size": 48219340
}
```

`has_update` uses PHP `version_compare`. Respect `is_mandatory` and `min_php_version` before installing.

#### `POST /packages/{slug}/download-url` — issue one-time download link

Optional query `version=2.1.0` (default = latest active).

```json
{
  "download_url": "https://panel.example.com/api/v1/packages/download/dl_a1b2c3d4...",
  "expires_in_seconds": 900,
  "is_one_time": true,
  "slug": "shop-package",
  "version": "2.1.0",
  "file_size": 48219340,
  "file_hash": "e3b0c442..."
}
```

The `download_url` works directly in a browser `<a href>` — **no auth headers needed**. It is
single-use and expires in 15 minutes. Generate a fresh one per download click.

#### `GET /packages/{slug}/download` — direct server-side download

Streams the ZIP (`Content-Disposition: attachment`) with headers
`X-Package-Version: 2.1.0` and `X-Package-Hash: <sha256>`. Requires auth headers — use from
your backend to proxy/process the file (save to disk, unzip, install).

#### `GET /packages/download/{dlToken}` — download by one-time token

The target of `download_url`. Token alone grants access (auth headers optional but allowed).
Headers `X-Package-Version` / `X-Package-Hash` are present here too.

### 5.2 Templates (8)

Same conventions as packages; **no licenses**. Two purchase types:
`purchase_type=normal` (default; repeat purchase → immediate `download_token`) and
`purchase_type=exclusive` (full transfer; after the first purchase the template is removed from
the store — `is_sold_exclusively`).

Every template item carries `thumbnail_url` (featured image) and `gallery`
(`[{id, url, sort_order}]`) — **both are full absolute URLs** on the panel domain; render them
with plain `<img src>` on the buyer site.

| Endpoint | Notes |
|---|---|
| `GET /templates` | `search`, `category` (int id), `per_page`. Items: `{id, name, slug, thumbnail_url, gallery, is_purchased, purchases_count, latest_version}` + `meta` + `gateways`. `thumbnail_url` = absolute featured-image URL; `gallery` = `[{id, url, sort_order}]` with absolute URLs |
| `GET /templates/{slug}` | Adds `price`, `discount_price`, `exclusive_price`, `is_sold_exclusively`, `active_versions`, `thumbnail_url`, `gallery` |
| `POST /templates/{slug}/purchase` | Body: `purchase_type` (normal\|exclusive), `callback_url` (if paid), `gateway`. Paid → `payment_url` flow. Repeat normal → direct `download_token` |
| `POST /templates/payments/{transactionId}/verify` | `{"paid": true, "download_token": "dl_...", "version": "1.4.2", "signature": "...", "message": "پرداخت با موفقیت تأیید شد."}` |
| `GET /templates/{slug}/check-update?current_version=1.4.0` | `{has_update, latest_version, current_version, changelog, file_size}` |
| `POST /templates/{slug}/download-url` | Same contract as packages |
| `GET /templates/{slug}/download` | Direct ZIP stream, auth required |
| `GET /templates/download/{dlToken}` | One-time token download |

### 5.3 Subscription Plans (4)

#### `GET /subscription-plans`

```json
{
  "data": [
    {
      "id": 2,
      "name": "طرح طلایی",
      "slug": "gold",
      "duration_months": 12,
      "duration_label": "یک‌ساله",
      "price": 4900000,
      "discount_price": 3900000,
      "final_price": 3900000,
      "is_free": false,
      "is_one_time": false,
      "has_durations": true,
      "durations": [
        { "id": 7, "duration_months": 1, "duration_label": "۱ ماه", "price": 490000, "discount_price": null, "final_price": 490000, "is_free": false, "sort_order": 1 },
        { "id": 8, "duration_months": 3, "duration_label": "۳ ماه", "price": 1290000, "discount_price": 100000, "final_price": 1190000, "is_free": false, "sort_order": 2 },
        { "id": 9, "duration_months": 12, "duration_label": "۱۲ ماه", "price": 4900000, "discount_price": 1000000, "final_price": 3900000, "is_free": false, "sort_order": 3 }
      ],
      "features": ["پشتیبانی ویژه", "آپدیت نامحدود"],
      "packages": [
        { "slug": "shop-package", "name": "پکیج فروشگاهی", "free_months": 6, "free_label": "۶ ماه رایگان" }
      ]
    }
  ],
  "gateways": [ { "key": "zarinpal", "title": "زرین‌پال", "logo": "https://...", "is_test": false } ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1, "per_page": 15 }
}
```

**Multi-duration plans**: a plan may define several validity periods (1/3/6/12 months…),
each with its own `price`/`discount_price`/`final_price`. Render one selectable option per entry
of `durations` and send the chosen `duration_months` at purchase. `duration_months: 0` = unlimited.
If `has_durations` is `false`, the plan uses its single default `final_price` and no
`duration_months` body param is needed.

#### `POST /subscription-plans/{slug}/purchase`

Body: `callback_url` (required), `gateway` (optional), `duration_months` (optional int —
chosen validity period for multi-duration plans; omit for single-duration plans; `0` = unlimited).
Invalid duration → 422 `"مدت اعتبار انتخاب‌شده برای این طرح معتبر نیست."`

- Paid → `{"payment_url": "...", "transaction_id": "tr_7g8h9i0j", "amount": 1190000, "gateway": "zarinpal", "order_id": 12, "duration_months": 3}`
- Free plan → `{"is_free": true, "order_id": 13, "plan": "طرح آزمایشی", "duration_months": 1, "admin_status": "pending", "status": "pending", "message": "درخواست اشتراک ثبت شد؛ پس از تأیید مدیر فعال می‌شود."}`

#### `POST /subscriptions/payments/{transactionId}/verify`

```json
{
  "paid": true,
  "order_id": 12,
  "plan": "طرح طلایی",
  "status": "paid",
  "admin_status": "approved",
  "subscription_id": 34,
  "expires_at": "2027-01-01 00:00:00"
}
```

> Licenses for the plan's included packages are issued **after admin approval** (not at this
> step). Poll `GET /my-subscriptions` and use `free_packages` to claim them.

#### `GET /my-subscriptions`

```json
{
  "data": {
    "subscriptions": [
      { "id": 34, "plan": "طرح طلایی", "plan_slug": "gold", "status": "active",
        "starts_at": "2026-01-01", "expires_at": "2027-01-01 00:00:00",
        "is_unlimited": false, "days_remaining": 334 }
    ],
    "orders": [
      { "id": 12, "plan": "طرح طلایی", "amount": 3900000, "status": "paid",
        "admin_status": "approved", "rejected_reason": null, "created_at": "2026-01-01 10:30:00" }
    ],
    "free_packages": [
      { "slug": "shop-package", "name": "پکیج فروشگاهی", "free_months": 6, "free_label": "۶ ماه رایگان",
        "subscription_plan": "طرح طلایی", "license_key": "LIC-XXXX-XXXX", "expires_at": "2026-07-01 00:00:00" }
    ]
  }
}
```

### 5.4 Payment Callback & Categories (2)

#### `GET|POST /payments/callback` — gateway callback (automatic; do NOT call manually)

The gateway redirects the user here; the panel verifies the payment, shows a result page with a
10-second countdown, then redirects the user to the store's `callback_url` with these params:

```
{callback_url}?transaction_id=tr_9f8a2b1c&status=success&purchase_id=42
{callback_url}?transaction_id=tr_9f8a2b1c&status=failed&error=پیام خطا
{callback_url}?transaction_id=tr_9f8a2b1c&status=pending
```

`status` ∈ `success | failed | pending`. Gateway params (e.g. `Status`, `Authority`, `cancel`,
`tracking_code`) are also forwarded. **Always confirm with the verify endpoint** — do not trust
the query string alone.

#### `GET /categories?type=package|product`

Active category tree for building the store's category selector (e.g. when the client project
installs a package and must map it to its own store category):

```json
{
  "data": [
    {
      "id": 1, "name": "وردپرس", "slug": "wordpress", "description": null,
      "children": [
        { "id": 3, "name": "افزونه", "slug": "plugins", "description": "...", "children": [] }
      ]
    }
  ]
}
```

### 5.5 Legacy Update System (2) — old projects only

No domain header; token via query param.

| Endpoint | Params | Response |
|---|---|---|
| `GET /check-update` | `token` (update_code), `version` | `{"update_available": true, "version": "3.2.0", "description": "...", "download_url": "https://.../download-update/17?token=...", "file_size": 34928120}` or `{"update_available": false}` |
| `GET /download-update/{updateId}?token=...` | `updateId`, `token` | Binary file (attachment) |

---

## 6. End-to-End Flows

### Flow A — Paid package purchase (canonical)

```
Storefront                    Panel API / Checkout           Gateway
   │  GET /packages             │                              │
   │───────────────────────────▶│  (catalog + gateways + plans)│
   │  POST /packages/{slug}/purchase                                 │
   │  {pricing_plan_id, callback_url, gateway}                       │
   │───────────────────────────▶│                              │
   │◀─── {checkout_url, payment_url, transaction_id: null}     │
   │  redirect user ───────────▶│  CHECKOUT PAGE (24h signed)  │
   │                            │  package name/image + plan   │
   │                            │  + discount code + amount    │
   │                            │  [پرداخت]           [انصراف] │
   │                            │   │                          │
   │                            │   │ pay: create gateway trx  │
   │                            │   └──────────────────────────▶ │
   │                            │◀── user pays, gateway hits /payments/callback
   │                            │  verify → result page (10s countdown)
   │◀── user redirected to callback_url?transaction_id=...&status=success
   │  POST /payments/{transaction_id}/verify                          │
   │───────────────────────────▶│                              │
   │◀─── {paid: true, license_key, download_token, signature}    │
   │  save license_key securely; show download button             │
   │  POST /packages/{slug}/download-url → one-time link          │
```

Implementation rules:

1. Follow `payment_url` (the checkout page). The `transaction_id` in the purchase response is
   `null`; your project receives the real `transaction_id` on `callback_url` redirect params
   after payment. No need to persist a mapping upfront anymore.
2. On `callback_url`, read `status` but **always** call the verify endpoint server-side.
3. If verify returns 400 `paid:false` (gateway lag), retry 3–5 times with 3–10s backoff, then
   show "در انتظار تأیید" and let the user retry from their purchases page.
4. Only after `paid: true` → store `license_key` and offer download.

### Flow B — Free / subscription-covered purchase

Same purchase endpoint; response arrives immediately with `license_key` (no gateway). Detect via
`is_free` / `via_subscription` keys and skip the redirect entirely.

### Flow C — Auto-update loop (client project runtime)

```
on app boot (cron or admin panel):
  POST /packages/{slug}/verify-license      {license_key}
    ├─ valid=false → disable features, show "لایسنس نامعتبر"
    └─ valid=true  →
  GET /packages/{slug}/check-update?current_version={installed}
    ├─ has_update=false → done
    └─ has_update=true → show changelog (what_added/what_changed/what_fixed)
         confirm (or auto if is_mandatory=false and auto-update enabled)
  POST /packages/{slug}/download-url → download_url (one-time, 15 min)
  GET download_url (server-side, save to file)
  hash_file('sha256', file) === file_hash (or response header X-Package-Hash)?
    ├─ mismatch → abort, retry once, report
    └─ match → backup, unzip, install, update installed version marker
```

### Flow D — Template purchase

Identical to Flow A with template endpoints. For `exclusive`, warn the buyer that the template
will disappear from the panel store after purchase (`is_sold_exclusively` on detail endpoint).

### Flow E — Subscriptions

Sell plans (`GET /subscription-plans` with `features` + included `packages` + `free_label`),
purchase (Flow A mechanics), then poll `GET /my-subscriptions` to show subscription status,
order history (`admin_status: pending/approved/rejected`) and claim free package licenses from
`free_packages`.

---

## 7. Error Handling Matrix

| HTTP | Trigger | Storefront behavior |
|---|---|---|
| 403 | Missing/invalid token, disabled account, **domain mismatch**, no active license/subscription | Show config-help message (store owner action). Never retry blindly |
| 404 | Package/template/plan/transaction not found | "یافت نشد" + refresh catalog |
| 422 | Validation (missing plan/callback, bad gateway, one-time plan reuse) | Render `error` message near the control |
| 400 | Payment not verified yet / bad transaction state | Retry verify with backoff; show pending state |
| 429 | Rate limit (120/min/IP) | Exponential backoff, cache catalog responses |
| 500 | Panel internal error | Generic error + support hint |

All error bodies: `{"error": "پیام فارسی"}` — safe to display directly.

---

## 8. Security Requirements (MUST implement)

1. **Token secrecy** — `update_code` only in server-side config/storage. Never in HTML, JS
   bundles, git, or URLs.
2. **Server-side calls only** — the browser must never talk to the panel directly (except via
   one-time `download_url` links, which are safe by design).
3. **Hash verification** — compare SHA-256 (`file_hash` / `signature` / `X-Package-Hash`) after
   every download before installation.
4. **One-time links** — always generate a fresh `download-url` per click; never cache or share.
5. **HTTPS callback_url** — required for payment returns.
6. **License storage** — keep `license_key` in app config/DB with restricted access; re-verify
   via `verify-license` periodically (e.g. every 24h) and handle revocation gracefully.
7. **Rate-limit friendliness** — cache `/packages` and `/categories` responses (5–15 min TTL);
   never poll in tight loops.
8. **Idempotent retries** — verify endpoint is idempotent for already-paid transactions; safe
   to re-call.

---

## 9. Storefront Blueprint (suggested pages & UX)

Build these pages; Persian labels suggested:

| Page | Data source | Persian UI suggestions |
|---|---|---|
| Catalog / Home | `GET /packages` + `GET /categories` | «فروشگاه پکیج‌ها»، فیلتر دسته‌بندی، جست‌وجو، badge «خریداری‌شده» |
| Package detail | `GET /packages/{slug}` | نسخه‌ها، طرح‌های قیمت، توضیحات، دکمهٔ «خرید/دانلود» |
| Checkout | `active_pricing_plans` + `gateways` | انتخاب طرح، انتخاب درگاه، «پرداخت» |
| Payment result | `callback_url` params + verify | «پرداخت موفق» / «ناموفق» / «در انتظار تأیید» + retry |
| My purchases & licenses | `GET /packages` (`is_purchased`, `purchased_license`) | جدول لایسنس‌ها با days_remaining، دکمهٔ «دانلود نسخهٔ آخر» |
| Updates center | `verify-license` + `check-update` | «بررسی آپدیت»، changelog، دکمهٔ نصب |
| Templates & detail | `GET /templates`, `GET /templates/{slug}` | قیمت عادی/اختصاصی، «خرید اختصاصی» |
| Subscription shop | `GET /subscription-plans` | کارت طرح‌ها، features، پکیج‌های هدیه |
| My subscriptions | `GET /my-subscriptions` | وضعیت، سفارش‌ها، پکیج‌های رایگانِ طرح |

**Purchase state machine:** `draft → payment_pending (transaction_id stored) → paid
(license_key stored) → installed (version marker) → update_available → updating → installed`.

---

## 10. Complete Code Examples

All examples assume:

```
BASE = https://panel.example.com/api/v1
TOKEN = YOUR_UPDATE_CODE          (customer's update_code)
PROJECT_URL = https://your-site.com
```

### 10.1 cURL — full paid purchase session

```bash
# 1) catalog
curl "https://panel.example.com/api/v1/packages?per_page=15" \
  -H "Authorization: Bearer YOUR_UPDATE_CODE" \
  -H "X-Project-Url: https://your-site.com"

# 2) start purchase
curl "https://panel.example.com/api/v1/packages/shop-package/purchase" \
  -H "Authorization: Bearer YOUR_UPDATE_CODE" \
  -H "X-Project-Url: https://your-site.com" \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"pricing_plan_id": 7, "callback_url": "https://your-site.com/payment-result", "gateway": "zarinpal"}'

# 3) after user returns from gateway (transaction_id from step 2 / callback query):
curl "https://panel.example.com/api/v1/payments/tr_9f8a2b1c/verify" \
  -H "Authorization: Bearer YOUR_UPDATE_CODE" \
  -H "X-Project-Url: https://your-site.com" \
  -X POST

# 4) one-time download link
curl "https://panel.example.com/api/v1/packages/shop-package/download-url" \
  -H "Authorization: Bearer YOUR_UPDATE_CODE" \
  -H "X-Project-Url: https://your-site.com" \
  -X POST

# 5) download the file
curl -L -o package.zip "https://panel.example.com/api/v1/packages/download/dl_a1b2c3d4..."

# 6) verify integrity
sha256sum package.zip   # must equal file_hash / X-Package-Hash
```

### 10.2 Laravel HTTP Client — reusable service class

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpShopApi
{
    private string $base;
    private string $token;
    private string $projectUrl;

    public function __construct()
    {
        // همه از config/.env — هرگز در کد سمت کلاینت قرار نگیرند
        $this->base       = rtrim(config('services.opshop.base'), '/');
        $this->token      = config('services.opshop.token');       // update_code مشتری
        $this->projectUrl = config('services.opshop.project_url'); // دامنهٔ ثبت‌شده
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Project-Url' => $this->projectUrl,
        ])->timeout(30);
    }

    /** لیست پکیج‌ها */
    public function packages(int $perPage = 15): array
    {
        return $this->http()
            ->get($this->base.'/packages', ['per_page' => $perPage])
            ->throw()
            ->json();
    }

    /** شروع خرید پولی → آرایهٔ payment_url/transaction_id */
    public function purchase(string $slug, int $planId, string $callbackUrl, ?string $gateway = null): array
    {
        return $this->http()
            ->post($this->base."/packages/{$slug}/purchase", array_filter([
                'pricing_plan_id' => $planId,
                'callback_url'    => $callbackUrl,
                'gateway'         => $gateway,
            ]))
            ->throw()
            ->json();
    }

    /** تأیید پرداخت — قابل فراخوانی مجدد (idempotent برای پرداخت‌های موفق) */
    public function verifyPayment(string $transactionId): array
    {
        return $this->http()
            ->post($this->base."/payments/{$transactionId}/verify")
            ->json(); // paid=false هم ممکن است؛ throw نکن
    }

    /** اعتبارسنجی لایسنس + توکن دانلود تازه */
    public function verifyLicense(string $slug, string $licenseKey): array
    {
        return $this->http()
            ->post($this->base."/packages/{$slug}/verify-license", ['license_key' => $licenseKey])
            ->json();
    }

    /** بررسی نسخهٔ جدید */
    public function checkUpdate(string $slug, string $currentVersion): array
    {
        return $this->http()
            ->get($this->base."/packages/{$slug}/check-update", ['current_version' => $currentVersion])
            ->throw()
            ->json();
    }

    /** لینک دانلود یک‌بارمصرف ۱۵ دقیقه‌ای */
    public function downloadUrl(string $slug, ?string $version = null): array
    {
        return $this->http()
            ->post($this->base."/packages/{$slug}/download-url", $version ? ['version' => $version] : [])
            ->throw()
            ->json();
    }

    /** دانلود سمت سرور + کنترل هش SHA-256 */
    public function download(string $slug, string $toPath): bool
    {
        $response = $this->http()
            ->withOptions(['sink' => $toPath])
            ->get($this->base."/packages/{$slug}/download");

        $expected = $response->header('X-Package-Hash');

        return $expected === '' || hash_file('sha256', $toPath) === $expected;
    }
}
```

Usage in a controller:

```php
$api   = new \App\Services\OpShopApi();
$cart  = $api->purchase('shop-package', 7, route('payment.result'));
// $cart = ['payment_url' => ..., 'transaction_id' => ..., 'amount' => ..., 'gateway' => ...]
Order::create(['transaction_id' => $cart['transaction_id'], ...]);
return redirect()->away($cart['payment_url']);
```

### 10.3 Guzzle — standalone PHP (no Laravel needed)

> If your project is Laravel, Guzzle is already installed (`composer show guzzlehttp/guzzle`).
> For standalone projects: `composer require guzzlehttp/guzzle`.

```php
<?php

use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://panel.example.com',
    'timeout'  => 30,
]);

$auth = [
    'Authorization' => 'Bearer YOUR_UPDATE_CODE',
    'X-Project-Url' => 'https://your-site.com',
    'Accept'        => 'application/json',
];

// 1) catalog
$response = $client->get('/api/v1/packages', [
    'headers' => $auth,
    'query'   => ['per_page' => 15],
]);
$catalog = json_decode((string) $response->getBody(), true);

// 2) purchase
$response = $client->post('/api/v1/packages/shop-package/purchase', [
    'headers' => $auth,
    'json'    => [
        'pricing_plan_id' => 7,
        'callback_url'    => 'https://your-site.com/payment-result',
        'gateway'         => 'zarinpal',
    ],
]);
$purchase = json_decode((string) $response->getBody(), true);
// redirect: header('Location: '.$purchase['payment_url']);

// 3) verify after return
$response = $client->post('/api/v1/payments/tr_9f8a2b1c/verify', ['headers' => $auth]);
$verify   = json_decode((string) $response->getBody(), true);
if ($verify['paid'] ?? false) {
    file_put_contents(__DIR__.'/license.key', $verify['license_key']);
}

// 4) one-time download link + server-side download
$response = $client->post('/api/v1/packages/shop-package/download-url', ['headers' => $auth]);
$url      = json_decode((string) $response->getBody(), true)['download_url'];

$response = $client->get(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY), [
    'sink' => 'package.zip',
]);
$expected = $response->getHeaderLine('X-Package-Hash');
if ($expected !== '' && hash_file('sha256', 'package.zip') !== $expected) {
    unlink('package.zip');
    throw new RuntimeException('File integrity check failed');
}
```

### 10.4 Callback handler sketch (framework-agnostic)

```php
// route: /payment-result  (this is the callback_url you sent during purchase)
// Query: ?transaction_id=tr_...&status=success|failed|pending&purchase_id=42[&error=...]

$transactionId = $_GET['transaction_id'] ?? '';
$status        = $_GET['status'] ?? 'pending';

$order = findOrderByTransaction($transactionId);          // your local order table
if (!$order) { http_response_code(404); exit; }

$attempts = 0;
do {
    $result = $api->verifyPayment($transactionId);        // server-side
    if (($result['paid'] ?? false)) {
        saveLicense($order, $result['license_key'], $result['expires_at']);
        renderSuccessPage($result);
        exit;
    }
    sleep(3 * ($attempts + 1));
} while (++$attempts < 4);

renderPendingPage($status); // "در انتظار تأیید پرداخت" + manual retry button
```

---

## 11. Acceptance Checklist (self-verify before delivery)

- [ ] Catalog lists packages with names, prices (from `active_pricing_plans`), categories tree, search & pagination working
- [ ] Purchasing a paid package: plan + gateway selection → redirect to `payment_url` → return → verify → license saved
- [ ] Free / subscription-covered purchase issues license without gateway
- [ ] Payment result page handles `success`, `failed`, `pending` (with verify-retry)
- [ ] Downloads work via one-time `download_url` (fresh per click) and/or server proxy
- [ ] SHA-256 verified after each download
- [ ] License verification runs periodically; invalid license blocks premium features
- [ ] Update checker shows changelog and respects `is_mandatory` / `min_php_version`
- [ ] Template purchase supports `normal` and `exclusive`
- [ ] Subscription shop sells plans; `my-subscriptions` displays status, orders, free packages
- [ ] All errors (403/404/422/400/429) render friendly Persian messages
- [ ] API token never appears in client-side code/HTML/logs; `.env`-driven config
- [ ] UI is fully RTL Persian, responsive, with loading and empty states
- [ ] Rate-limit friendly: catalog responses cached, no tight polling loops

---

*Contract source: this specification was extracted directly from the panel's controller source
(`ApiPackageController`, `ApiTemplateController`, `ApiSubscriptionController`,
`ApiPackageDownloadController`, `ApiTemplateDownloadController`, `ApiPaymentCallbackController`,
`UpdateController`, `PackageApiAuthService`, `PaymentReturnController`). API version: v1.*

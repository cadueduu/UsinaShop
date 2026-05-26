# Spark Eletrônica — Full Architecture Documentation

> **Purpose:** This document is the authoritative reference for maintainers and AI agents working on this codebase. It covers system structure, data flows, integration contracts, and operational details at a depth sufficient to understand, extend, or debug any part of the platform without reading every source file first.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Technology Stack](#2-technology-stack)
3. [Directory Structure](#3-directory-structure)
4. [Core Configuration](#4-core-configuration)
5. [Request Lifecycle](#5-request-lifecycle)
6. [Frontend Architecture](#6-frontend-architecture)
7. [Backend Modules](#7-backend-modules)
8. [API Reference](#8-api-reference)
9. [Database Schema](#9-database-schema)
10. [Authentication & Authorization](#10-authentication--authorization)
11. [External Integrations](#11-external-integrations)
12. [Key Business Workflows](#12-key-business-workflows)
13. [Admin Panel](#13-admin-panel)
14. [Local Development Mode](#14-local-development-mode)
15. [Deployment & Hosting](#15-deployment--hosting)
16. [Security Model](#16-security-model)
17. [Known Limitations & Technical Debt](#17-known-limitations--technical-debt)

---

## 1. System Overview

**Spark Eletrônica** is a Brazilian consumer electronics e-commerce storefront. It is a **server-rendered multi-page application (MPA)** built with PHP 7.4+, backed by Supabase (PostgreSQL), and augmented with client-side JavaScript for interactivity.

### Architecture Style

```
Browser (HTML + CSS + JS)
       │
       │  HTTP requests (page loads + AJAX)
       ▼
Apache + PHP 7.4+ (monolithic application server)
       │
       ├── Supabase (PostgreSQL via PostgREST REST API) ← primary datastore
       ├── Supabase Auth (JWT-based authentication)
       ├── Supabase Storage (product images, fallback)
       ├── Mercado Pago (payment gateway)
       ├── Correios CWS API (shipping calculation)
       ├── ViaCEP API (postal code address lookup)
       └── Image Repository API (product images, primary)
```

**Key design choices:**
- **No build pipeline.** PHP, CSS, and JS are served as-is. No Webpack, Vite, or transpilers.
- **No ORM.** Database access is raw HTTP calls to Supabase PostgREST.
- **Two JS contexts.** The Supabase JS SDK runs in the browser for auth session management; PHP uses cURL for all server-side DB access.
- **Dual-mode operation.** A `LOCAL_DATA_MODE` flag switches the entire data layer to a local JSON file (`data/local-db.json`), enabling offline development and demos without a live Supabase connection.
- **Single-origin.** The PHP app, API endpoints, and admin panel all live in one directory and share one configuration entrypoint (`includes/config.php`).

---

## 2. Technology Stack

| Layer | Technology | Version | Notes |
|-------|-----------|---------|-------|
| Language | PHP | 7.4+ | No framework (vanilla PHP) |
| Web Server | Apache HTTP Server | Any | Requires `mod_rewrite` |
| Database | PostgreSQL (via Supabase) | N/A | Accessed via PostgREST REST API |
| Auth | Supabase Auth | — | JWT, email+password |
| Storage | Supabase Storage | — | `product-assets` bucket |
| Payments | Mercado Pago SDK | 3.9 (DX PHP) | Composer-managed |
| Shipping | Correios CWS | — | REST API with JWT |
| Address Lookup | ViaCEP | — | Public REST, no auth |
| Product Images | External Image API | — | X-API-Key authenticated |
| Package Manager | Composer | 2.x | `vendor/` directory |
| Frontend JS | Vanilla ES6+ | — | No framework |
| Frontend CSS | Vanilla CSS3 | — | Single unified stylesheet |
| Supabase JS SDK | `@supabase/supabase-js` | 2.x | Loaded from CDN in browser |

---

## 3. Directory Structure

```
P4/
│
├── index.php                   # Homepage (banners, featured products)
├── products.php                # Product catalog with filters and pagination
├── product.php                 # Single product detail page (?id=)
├── checkout.php                # Cart review + address selection
├── checkout-pagamento.php      # Payment method + order creation
├── login.php                   # Auth: login and registration forms
├── conta.php                   # User account: orders, addresses, profile
├── atendimento.php             # Customer support inquiry form
├── institucional.php           # Company/institutional information pages
├── sobre.php                   # About page
│
├── admin/                      # Admin panel (HTTP Basic Auth protected)
│   ├── index.php               # Redirects to clientes.php
│   ├── clientes.php            # Client management and order history
│   ├── produtos.php            # Product visibility toggle (syncsite flag)
│   ├── produto-edit.php        # Individual product editor
│   ├── layout.php              # Admin HTML head + nav wrapper
│   └── layout-end.php         # Admin HTML footer wrapper
│
├── api/                        # JSON API endpoints (used by browser JS)
│   ├── produtos.php            # Paginated product listing with filters
│   ├── categorias.php          # Category tree
│   ├── correios-preco.php      # Shipping price quote
│   ├── correios-data-entrega.php  # Delivery date estimate
│   ├── local-login.php         # Auth endpoint for LOCAL_DATA_MODE
│   └── debug-sb.php            # Supabase debugging (dev only)
│
├── includes/                   # Shared PHP includes (no standalone execution)
│   ├── config.php              # *** Core config, all Supabase helpers (998 lines) ***
│   ├── head.php                # HTML <head>: meta, CSS, Supabase JS SDK, globals
│   ├── header.php              # Desktop navigation: logo, search, cart, account
│   ├── footer.php              # Page footer
│   ├── mobile-bar.php          # Mobile bottom navigation bar
│   ├── cart-sidebar.php        # Sliding cart panel (shows items, weight, frete)
│   ├── search-sidebar.php      # Sliding search panel
│   └── product-card.php        # Reusable product grid card template
│
├── assets/
│   ├── css/
│   │   └── style.css           # Single unified stylesheet (all pages and components)
│   ├── js/
│   │   └── main.js             # Single JS file (~1067 lines): auth, cart, UI
│   └── images/
│       ├── productos/logo.png
│       ├── banners/            # Homepage banner images
│       └── [other brand assets]
│
├── vendor/                     # Composer packages
│   └── mercadopago/dx-php/     # Mercado Pago PHP SDK v3.9
│
├── data/
│   └── local-db.json           # JSON flat-file database (LOCAL_DATA_MODE fallback)
│
├── .env                        # Environment variables (credentials — do not commit)
├── .htaccess                   # Apache URL rewriting, security, cache headers
├── composer.json               # Declares PHP dependencies
├── composer.lock               # Locked dependency tree
├── README.md                   # Project overview and quick-start
├── SETUP.md                    # Full setup instructions
└── RECRIACAO.md                # Rebuild/recreation notes
```

---

## 4. Core Configuration

### `includes/config.php` — The Configuration Entrypoint

Every PHP page begins by requiring `includes/config.php`. This file is the single source of truth for all configuration and provides the primary database access helpers.

**Responsibilities:**
1. Loads `.env` (parses key=value pairs into `$_ENV`)
2. Detects `LOCAL_DATA_MODE` and switches data layer to `data/local-db.json`
3. Defines all Supabase HTTP helper functions
4. Defines product enrichment logic (`enrich_products()`)
5. Defines category helpers (`build_category_tree()`, `cat_name()`)
6. Defines product name/image helpers (`prod_name()`, image resolution pipeline)
7. Defines formatting helpers (`fmt_brl()`)
8. Defines admin basic-auth guard (`admin_require_basic_auth()`)

### Environment Variables (`.env`)

All secrets and environment-specific values are stored in `.env` at the project root.

| Variable | Purpose | Used By |
|----------|---------|---------|
| `SUPABASE_URL` | Supabase project URL | All DB calls |
| `SUPABASE_ANON_KEY` | Supabase anon (public) key | Public reads |
| `SUPABASE_SERVICE_KEY` | Supabase service (admin) key | Admin writes, server-side auth |
| `CORREIOS_CWS_TOKEN` | Correios CWS API token | Shipping |
| `CORREIOS_CONTRATO` | Correios contract number | Shipping |
| `CORREIOS_DR` | Correios regional code | Shipping |
| `CORREIOS_CWS_JWT` | Pre-obtained Correios JWT (optional) | Shipping |
| `MP_ACCESS_TOKEN` | Mercado Pago access token | Payments |
| `MP_WEBHOOK_SECRET` | Mercado Pago webhook signing secret | Payment webhook |
| `IMG_TOKEN` | External image repository API key | Product images (primary) |
| `IMG_API_BASE_URL` | External image repository base URL | Product images (primary) |
| `APP_BASE_URL` | Full URL of this application | Webhook return URLs |
| `CEP_ORIGEM` | Sender's postal code (warehouse) | Shipping origin |
| `ADMIN_USER` | Admin panel basic auth username | Admin panel |
| `ADMIN_PASS` | Admin panel basic auth password | Admin panel |
| `LOCAL_DATA_MODE` | `true` to switch to JSON flat-file mode | Offline dev |

### Supabase Access Functions (`includes/config.php`)

```php
// Single table query with filters
sb(string $table, array $filters = [], ?string $key = null): array

// Multiple table queries in parallel via array of requests
sb_multi(array $requests, ?string $key = null): array

// In LOCAL_DATA_MODE, reads from data/local-db.json instead of Supabase
sb_local(string $table, array $filters = []): array
```

All Supabase calls use cURL with Accept: `application/json` and optionally `Prefer: return=representation`. The anon key is used for public reads; the service key is used for admin writes and server-side operations requiring elevated privileges.

### Product Constants

| Constant | Value | Meaning |
|----------|-------|---------|
| `PROD_FETCH_BATCH` | 72 | Rows fetched from DB per pagination cycle |
| `PROD_PAGE_SIZE` | 24 | Products shown per page (after filtering unavailable) |

---

## 5. Request Lifecycle

### Standard Page Request

```
1. Browser requests /products.php
2. Apache: .htaccess checks URL rewrite rules
3. PHP loads: require_once 'includes/config.php'
   ├── .env is parsed
   ├── Supabase client configured
   └── Helpers defined
4. Page logic runs (PHP)
   ├── Fetches data from Supabase via cURL
   └── Builds PHP variables for template
5. Page renders includes:
   ├── require 'includes/head.php'    → <head>, CSS, Supabase JS SDK
   ├── require 'includes/header.php'  → navigation
   ├── [page body HTML + PHP output]
   ├── require 'includes/footer.php'
   ├── require 'includes/cart-sidebar.php'
   ├── require 'includes/search-sidebar.php'
   ├── require 'includes/mobile-bar.php'
   └── <script src="assets/js/main.js">
6. Browser renders HTML
7. main.js initializes:
   ├── Supabase JS SDK initialized (window.supabaseClient)
   ├── Auth session restored (onAuthStateChange)
   ├── Cart loaded from DB (if logged in) or localStorage
   └── UI event listeners attached
```

### API Request (browser AJAX → PHP)

```
1. Browser JS calls fetch('/api/produtos.php?offset=0&categoria=123')
2. Apache serves /api/produtos.php directly
3. PHP loads config.php, runs query, returns JSON
4. Response headers include:
   Cache-Control: public, max-age=30, stale-while-revalidate=120
5. Browser receives JSON, updates DOM
```

### Apache URL Rewriting (`.htaccess`)

Key rewrite rules:
- `/mp/webhook` → `checkout-pagamento.php?action=mp-webhook` (Mercado Pago webhook receiver)
- Vendor and composer paths blocked from public access
- Gzip compression enabled for HTML, CSS, JS, JSON
- Cache headers set for static assets (images, CSS, JS)

---

## 6. Frontend Architecture

The frontend is a **multi-page application** with no JavaScript framework. Each PHP page renders full HTML; JavaScript adds interactivity without controlling rendering.

### `assets/js/main.js` (~1067 lines)

This is the single JavaScript file for all pages. It initializes once per page load.

**Sections:**

#### Auth Module
- Initializes `window.supabaseClient` using globals injected by `includes/head.php`
- Calls `supabase.auth.getSession()` on load to restore the user session
- Listens to `onAuthStateChange` to update UI (show/hide account links, name, etc.)
- Login: calls `supabase.auth.signInWithPassword()`
- Signup: calls `supabase.auth.signUp()` with user metadata
- Logout: calls `supabase.auth.signOut()`

#### Cart Module
- **In-memory cart state**: `let cartItems = []`
- **Persistence strategy:**
  - Logged-in users: cart synced to `carrinho` table in Supabase (server-side)
  - Guest users: cart held in memory only (no localStorage persistence)
- Key functions:
  - `loadCart()` — fetches from DB or initializes empty
  - `addToCart(product)` — adds item, upserts to DB
  - `updateCartQuantity(codprod, qty)` — adjusts quantity
  - `removeFromCart(codprod)` — removes item
  - `renderCartSidebar()` — rebuilds DOM for cart sidebar
  - `updateCartBadge()` — updates item count bubble on header icon

#### UI Module
- **Banner carousel** (homepage): timed image rotation with dot indicators
- **Sidebar toggles**: cart sidebar, search sidebar open/close
- **Category dropdown**: hover-activated multi-level dropdown in header
- **Mobile nav**: active state management for bottom bar icons
- **CEP lookup** (checkout): calls ViaCEP API on blur, fills address fields
- **Shipping quote**: calls `/api/correios-preco.php` and `/api/correios-data-entrega.php`
- **Product gallery**: thumbnail switching on product detail page
- **Form validation**: password strength, required field checks

### `assets/css/style.css` — Design System

A single flat CSS file providing all styles. Structure:

| Section | Description |
|---------|-------------|
| CSS Variables | Color palette (yellow `#f5c518`, black, grays), spacing scale, shadow depths |
| Reset | Box-sizing, margin/padding normalization |
| Typography | Font stack, heading scales |
| Layout | Grid and flexbox utilities |
| Header | Desktop navigation, dropdowns |
| Footer | Footer grid layout |
| Product Card | Grid card: image, title, price, badge |
| Sidebars | Cart and search slide-in panels |
| Mobile Nav | Bottom tab bar |
| Pages | Page-specific styles (checkout, conta, product, admin) |
| Responsive | `@media` breakpoints |

### `includes/head.php` — JS Globals Injection

This file injects PHP configuration values as `window.*` globals so the browser JS can access them without a separate API call:

```html
<script>
  window.SUPABASE_URL = '<?= SUPABASE_URL ?>';
  window.SUPABASE_ANON_KEY = '<?= SUPABASE_ANON_KEY ?>';
  // Additional globals as needed per page
</script>
```

---

## 7. Backend Modules

### `includes/config.php` — Data Layer Functions

#### `enrich_products(array $products, bool $listing_mode = false): array`

Enriches a raw product list (from the `produto` table) with pricing, stock, and images.

```
Input:  array of produto rows (codprod, descrprod, ...)
Output: array with added fields: vlr_venda, estoque_disponivel, imagem_url, ...

Process:
1. Extract all codprod values
2. Batch-fetch preco, estoque, produto_imagem for all IDs
3. Merge into each product row
4. If listing_mode=true: use first image only (performance)
5. If listing_mode=false: include all images (product detail)
6. Resolve image URL: external API first, Supabase Storage as fallback
```

#### `fetch_products(array $filters = []): array`

High-level product fetch used by homepage and admin. Calls `sb()` then `enrich_products()`.

#### `filter_available(array $products): array`

Removes products where `vlr_venda <= 0` or `estoque_disponivel <= 0`. Used in the product API to skip products that cannot be sold.

#### `build_category_tree(): array`

Constructs a hierarchical category array from the flat `categoria` table using the `codgrupopai` self-referential foreign key.

```
Input:  flat rows with codgrupoprod, descr_grupo, codgrupopai
Output: nested array with parent → children structure
```

#### Image Resolution Pipeline

```
1. Check if external image API is configured (IMG_TOKEN set)
2. If yes: call IMG_API_BASE_URL/{codprod}?key=IMG_TOKEN
3. If request fails or returns no image: fall back to Supabase Storage
4. Supabase Storage: public URL from product-assets bucket
5. If still no image: return placeholder image URL
```

### Public Pages

#### `index.php` — Homepage
- Calls `fetch_products(['featured' => true])` and a bestseller query
- Renders banner carousel HTML (image paths hardcoded in banners/ directory)
- Includes `product-card.php` in a loop for each product section
- No JS-side data fetching; all data server-rendered

#### `products.php` — Catalog
- Renders the shell (header, filters sidebar, empty product grid)
- Product data loaded entirely via JS calling `GET /api/produtos.php`
- Category filter links are PHP-rendered server-side
- Infinite scroll or "load more" button triggers JS pagination

#### `product.php` — Product Detail
- Receives `?id={codprod}` query parameter
- Fetches single product via `sb()`, enriches with all images, specs, category breadcrumb
- Renders image gallery (main + thumbnails), specs table, add-to-cart form
- Related products section (same category)
- Add-to-cart calls `addToCart()` in `main.js`

#### `checkout.php` — Checkout Step 1
- **Requires authentication** (PHP redirects to `/login.php` if no session)
- Fetches user's saved addresses from `endereco` table
- Renders address list + "add new address" form
- CEP input triggers ViaCEP API call (browser-side) to auto-fill fields
- Shipping quote is calculated per address selection via Correios API calls
- Saves selected address + cart context to session, redirects to `checkout-pagamento.php`

#### `checkout-pagamento.php` — Checkout Step 2
- Handles both page render (GET) and multiple POST actions (query string dispatch):

| `?action=` | Method | Description |
|------------|--------|-------------|
| `(none)` | GET | Renders payment options page |
| `mp-methods` | POST | Returns available Mercado Pago payment methods as JSON |
| `product-images` | POST | Returns product image URLs for cart display |
| `mp-webhook` | POST | Mercado Pago webhook: validates signature, updates `pedido.status` |

- **Order creation flow:**
  1. PHP creates `pedido` row (status = `aguardando_pagamento`)
  2. PHP creates `pedido_item` rows
  3. PHP calls Mercado Pago SDK to create a Preference
  4. Returns Mercado Pago Preference ID + checkout link to browser
  5. Browser redirects to Mercado Pago hosted checkout

#### `conta.php` — User Account
- **Requires authentication**
- Tabs: Pedidos (orders), Endereços (addresses), Dados (profile)
- Orders fetched from `pedido` + `pedido_item` tables filtered by `cliente_id`
- Handles `?action=mp-confirm` POST: polls Mercado Pago for payment status, updates DB

#### `login.php` — Authentication
- Tab-based UI: "Entrar" (login) and "Criar Conta" (register)
- Both forms are client-side only (JS calls Supabase Auth SDK)
- On success: JS redirects to referring page or `/`
- Registration collects: nome, sobrenome, email, CPF, telefone, senha
- After Supabase Auth signup: JS upserts a row into `cliente` table with custom fields

---

## 8. API Reference

All API endpoints live in `/api/` and return JSON. No authentication required for public endpoints.

### `GET /api/produtos.php` — Product Listing

**Purpose:** Paginated, filterable product list for the catalog page.

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `offset` | int | 0 | Row offset for pagination |
| `categoria` | int | — | Filter by `codgrupoprod` |
| `q` | string | — | Full-text search (ilike on name) |

**Response:**

```json
{
  "ok": true,
  "products": [ /* array of enriched product objects */ ],
  "has_more": true,
  "next_offset": 72
}
```

**Pagination Logic:**

```
PROD_FETCH_BATCH = 72   (rows fetched from Supabase per call)
PROD_PAGE_SIZE   = 24   (filtered available products returned per call)

1. Fetch PROD_FETCH_BATCH rows starting at `offset`
2. Run filter_available() → remove products without price/stock
3. Return first PROD_PAGE_SIZE of filtered results
4. If fewer than PROD_PAGE_SIZE available, advance offset and repeat (max 3 cycles)
5. has_more = true if there were rows beyond what was returned
6. next_offset = offset + PROD_FETCH_BATCH
```

**Cache headers:** `Cache-Control: public, max-age=30, stale-while-revalidate=120`

---

### `GET /api/categorias.php` — Category Tree

**Purpose:** Returns the full category hierarchy for navigation menus.

**Response:**

```json
[
  {
    "codgrupoprod": 1,
    "descr_grupo": "Eletrônicos",
    "codgrupopai": null,
    "children": [
      { "codgrupoprod": 2, "descr_grupo": "Smartphones", "codgrupopai": 1, "children": [] }
    ]
  }
]
```

Filters out categories with `hidden = true`.

---

### `GET /api/correios-preco.php` — Shipping Price

**Purpose:** Calculates shipping cost from warehouse to customer.

**Query Parameters:**

| Parameter | Description |
|-----------|-------------|
| `cep_destino` | Destination postal code (8 digits) |
| `peso` | Total weight in kg |
| `altura` | Package height (cm) |
| `largura` | Package width (cm) |
| `comprimento` | Package length (cm) |

**Response:** Array of Correios service options with price and delivery days.

**Auth:** Correios CWS JWT (obtained automatically using `CORREIOS_CWS_TOKEN` if `CORREIOS_CWS_JWT` not pre-set).

---

### `GET /api/correios-data-entrega.php` — Delivery Date

**Purpose:** Estimates the delivery date for a given postal code and service.

**Query Parameters:** Same as `correios-preco.php` plus `servico` (Correios service code).

---

### `POST /checkout-pagamento.php?action=mp-webhook` — Mercado Pago Webhook

**Purpose:** Receives payment status notifications from Mercado Pago.

**Headers:** `x-signature` (HMAC-SHA256, validated against `MP_WEBHOOK_SECRET`)

**Behavior:**
1. Validates webhook signature
2. Queries Mercado Pago API for payment status
3. Finds matching `pedido` row via `mp_payment_id`
4. Updates `pedido.status`:
   - `approved` → `pago`
   - `pending` → `aguardando_pagamento`
   - `rejected` → `cancelado`
5. Inserts row into `log_integracao_pedido`

---

## 9. Database Schema

The database is hosted on Supabase (PostgreSQL). Tables are accessed via the Supabase PostgREST API.

### Entity Relationship Summary

```
auth.users (Supabase managed)
    │ 1:1
    ▼
cliente ──────────────────────────────────────────── endereço (N addresses)
    │                                                      │
    │ 1:N                                                  │ 1:N
    ▼                                                      ▼
pedido ◄──────────────────────────────────── uses endereço_id
    │ 1:N
    ├── pedido_item ──────────► produto
    ├── pedido_embalagem ──────► embalagem
    └── log_integracao_pedido

produto
    ├── preco (price table rows)
    ├── estoque (stock row)
    ├── produto_imagem (N images)
    ├── especificacao (N spec key-values)
    └── codgrupoprod → categoria (hierarchical)

carrinho (active cart items, per cliente)
    └── codprod → produto
```

### Table Definitions

#### `cliente` — User Profile

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid PK | Mirrors `auth.users.id` |
| `codparc` | int | ERP partner code (external system) |
| `cpf_cnpj` | varchar | Brazilian tax ID |
| `email` | varchar | Email address |
| `nome` | varchar | First name |
| `sobrenome` | varchar | Last name |
| `telefone` | varchar | Phone number |
| `is_admin` | bool | Admin privilege flag |
| `created_at` | timestamp | Record creation time |

#### `endereco` — Delivery Addresses

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid PK | — |
| `cliente_id` | uuid FK → cliente | Owner |
| `tipo` | varchar | `residencial`, `comercial`, etc. |
| `cep` | varchar(8) | Postal code |
| `logradouro` | varchar | Street name |
| `numero` | varchar | Number |
| `complemento` | varchar | Apartment, suite, etc. |
| `bairro` | varchar | Neighborhood |
| `cidade` | varchar | City |
| `uf` | char(2) | State code (BR) |
| `is_padrao` | bool | Default address flag |

#### `categoria` — Product Categories (Hierarchical)

| Column | Type | Description |
|--------|------|-------------|
| `codgrupoprod` | int PK | Category ID |
| `descr_grupo` | varchar | Display name |
| `codgrupopai` | int FK → categoria | Parent category (null = root) |
| `hidden` | bool | Hide from storefront |

#### `produto` — Product Catalog

| Column | Type | Description |
|--------|------|-------------|
| `codprod` | int PK | Product ID |
| `descrprod` | varchar | Base product name |
| `comnome` | varchar | Commercial display name |
| `codgrupoprod` | int FK → categoria | Category |
| `peso` | decimal | Weight (kg) |
| `altura` | decimal | Height (cm) |
| `largura` | decimal | Width (cm) |
| `comprimento` | decimal | Length (cm) |
| `syncsite` | char(1) | `'Y'` = visible on storefront |
| `codprodemb` | int FK → embalagem | Packaging reference |

#### `embalagem` — Packaging Dimensions

| Column | Type | Description |
|--------|------|-------------|
| `codprod` | int PK | Packaging product ID |
| `peso` | decimal | Packaging weight (kg) |
| `altura` | decimal | Packaging height (cm) |
| `largura` | decimal | Packaging width (cm) |
| `comprimento` | decimal | Packaging length (cm) |

#### `preco` — Pricing

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | — |
| `codprod` | int FK → produto | Product |
| `vlr_venda` | decimal | Sale price (BRL) |
| `codtab` | int | Price table code (selects active price tier) |

#### `estoque` — Stock

| Column | Type | Description |
|--------|------|-------------|
| `codprod` | int PK | Product |
| `estoque_real` | decimal | Physical stock count |
| `proporcao` | decimal | Unit conversion ratio |
| `estoque_disponivel` | decimal | Available for sale (real × proporcao) |

#### `produto_imagem` — Product Images

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | — |
| `codprod` | int FK → produto | Product |
| `url` | varchar | Image URL (relative or absolute) |
| `ordem` | int | Display sort order |

#### `especificacao` — Product Specifications

| Column | Type | Description |
|--------|------|-------------|
| `id_espec` | int PK | — |
| `codprod` | int FK → produto | Product |
| `label` | varchar | Spec name (e.g., "Voltagem") |
| `valor` | varchar | Spec value (e.g., "220V") |

#### `carrinho` — Shopping Cart

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid PK | — |
| `cliente_id` | uuid FK → cliente | Owner |
| `codprod` | int FK → produto | Product |
| `quantidade` | int | Quantity |
| `peso_total` | decimal | quantidade × produto.peso |

#### `pedido` — Order Header

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid PK | — |
| `cliente_id` | uuid FK → cliente | Buyer |
| `endereco_id` | uuid FK → endereco | Delivery address |
| `status` | varchar | `aguardando_pagamento`, `pago`, `cancelado`, `enviado`, `entregue` |
| `vlr_total` | decimal | Order total (BRL) |
| `vlr_frete` | decimal | Shipping cost (BRL) |
| `nunota` | int | ERP order number (external system) |
| `mp_preference_id` | varchar | Mercado Pago Preference ID |
| `mp_payment_id` | varchar | Mercado Pago Payment ID |
| `created_at` | timestamp | Order creation time |

#### `pedido_item` — Order Line Items

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid PK | — |
| `pedido_id` | uuid FK → pedido | Parent order |
| `codprod` | int FK → produto | Product |
| `quantidade` | int | Quantity ordered |
| `vlr_unitario` | decimal | Unit price at time of order |

#### `pedido_embalagem` — Order Packing Details

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid PK | — |
| `pedido_id` | uuid FK → pedido | Parent order |
| `embalagem_codprod` | int FK → embalagem | Packaging used |
| `quantidade_caixas` | int | Number of boxes |
| `peso_total` | decimal | Total packed weight |
| `cenario` | varchar | `unitario`, `padrao`, or `catalogo` |

#### `log_integracao_pedido` — Order Integration Logs

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | — |
| `pedido_id` | uuid FK → pedido | Order |
| `tentativa` | int | Attempt number |
| `status` | varchar | `ok`, `erro` |
| `payload_enviado` | jsonb | Request payload sent to ERP |
| `resposta_recebida` | jsonb | ERP response |

#### `log_sincronizacao` — Sync Operation Logs

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | — |
| `entidade` | varchar | Entity being synced (e.g., `produto`) |
| `status` | varchar | `sucesso`, `erro` |
| `registros_processados` | int | Number of records processed |
| `mensagem_erro` | text | Error details (if any) |

---

## 10. Authentication & Authorization

### Authentication System

**Provider:** Supabase Auth (JWT-based, email + password)

**Token flow:**
```
1. User submits login form (email + password)
2. JS calls: supabase.auth.signInWithPassword({ email, password })
3. Supabase Auth returns: { session: { access_token, refresh_token, user } }
4. Supabase JS SDK stores session in localStorage (automatic)
5. All subsequent Supabase JS calls include access_token as Bearer
6. PHP server-side: validates Bearer token via Supabase REST API
```

**Registration extras:**
```
1. supabase.auth.signUp({ email, password, options: { data: { nome, sobrenome, telefone, cpf } } })
2. On success: upsert into `cliente` table with matching UUID
```

### Authorization Levels

| Level | Guard | Pages |
|-------|-------|-------|
| Public | None | `/`, `/products.php`, `/product.php`, `/atendimento.php`, `/institucional.php`, `/sobre.php`, all `/api/` endpoints |
| Authenticated | PHP session check + Supabase JWT | `/checkout.php`, `/checkout-pagamento.php`, `/conta.php` |
| Admin | HTTP Basic Auth | `/admin/*` |

**PHP auth guard pattern (checkout pages):**
```php
// At top of page, before any HTML output
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
```

**Admin guard (`admin_require_basic_auth()` in config.php):**
```php
// Called at top of every admin/*.php file
function admin_require_basic_auth(): void {
    if (!isset($_SERVER['PHP_AUTH_USER']) ||
        $_SERVER['PHP_AUTH_USER'] !== getenv('ADMIN_USER') ||
        $_SERVER['PHP_AUTH_PW']   !== getenv('ADMIN_PASS')) {
        header('WWW-Authenticate: Basic realm="Admin"');
        header('HTTP/1.0 401 Unauthorized');
        exit;
    }
}
```

### Row-Level Security

Supabase RLS policies enforce that authenticated users can only read/write their own data in tables like `carrinho`, `pedido`, `endereco`. The PHP backend uses the service key to bypass RLS for admin operations and webhook processing.

---

## 11. External Integrations

### Supabase (PostgreSQL + Auth + Storage)

**SDK:** Direct HTTP cURL (PHP server-side) + `@supabase/supabase-js@2` (browser)

**Endpoints used:**
- `{SUPABASE_URL}/rest/v1/{table}` — PostgREST CRUD
- `{SUPABASE_URL}/auth/v1/token` — Auth token operations
- `{SUPABASE_URL}/storage/v1/object/product-assets/` — Image storage

**PHP pattern:**
```php
$response = sb('produto', [
    'codprod=eq.' . $id,
    'select=codprod,descrprod,comnome'
]);
```

---

### Mercado Pago (Payments)

**SDK:** `mercadopago/dx-php` v3.9 (Composer)

**Integration type:** Hosted checkout (Redirect)

**Flow:**
```
1. PHP creates a Preference (product list + buyer + URLs)
2. Mercado Pago returns preference_id + init_point URL
3. Browser redirects to init_point (hosted Mercado Pago checkout)
4. User completes payment on Mercado Pago
5. Mercado Pago POSTs to /mp/webhook with payment notification
6. PHP webhook handler queries MP API for payment status
7. PHP updates pedido.status + inserts integration log
8. Browser polls /conta.php?action=mp-confirm until confirmed
```

**Environment config:**
- `MP_ACCESS_TOKEN` — used for all SDK calls
- `MP_WEBHOOK_SECRET` — used to validate incoming webhook HMAC signatures

---

### Correios CWS (Shipping)

**Integration type:** REST API (JWT-authenticated)

**Flow:**
```
1. If CORREIOS_CWS_JWT is set: use it directly
2. If not: POST to Correios auth endpoint with CORREIOS_CWS_TOKEN to obtain JWT
3. GET /cws/{version}/preco with cargo dimensions + origin/destination CEP
4. Parse response: array of service options with precoFinal + prazoEntrega
```

**Origin postal code:** `CEP_ORIGEM` env var (warehouse location)

---

### ViaCEP (Address Lookup)

**Integration type:** Public REST API, no auth required

**Usage:** Browser JS calls `https://viacep.com.br/ws/{cep}/json/` on CEP input blur in checkout address form. Auto-fills logradouro, bairro, cidade, uf fields.

---

### External Image Repository

**Integration type:** REST API with `X-API-Key` header

**Flow:**
```
1. Server-side: GET {IMG_API_BASE_URL}/{codprod} with X-API-Key: {IMG_TOKEN}
2. Response: { url: "..." } with direct image URL (typically low-res)
3. If no image: fall back to Supabase Storage URL
4. If no Supabase image either: use local placeholder
```

---

## 12. Key Business Workflows

### Product Discovery Flow

```
User visits /products.php
    │
    ├── PHP renders page shell (header, filter sidebar, empty grid)
    │
    └── JS calls GET /api/produtos.php?offset=0[&categoria=X][&q=Y]
            │
            ├── PHP fetches 72 produto rows from Supabase
            ├── PHP batch-fetches preco + estoque + imagem for all
            ├── PHP runs filter_available() (removes price=0 or stock=0)
            ├── PHP returns first 24 filtered products as JSON
            └── JS renders product cards into DOM grid
                    │
                    └── User scrolls or clicks "Load More"
                            └── JS calls /api/produtos.php?offset=72
                                (cycle repeats)
```

### Full Purchase Flow

```
1. BROWSE
   User adds product → JS addToCart() → upsert to carrinho table

2. CHECKOUT STEP 1 (/checkout.php)
   PHP fetches saved addresses (endereco table)
   User selects or adds address
   JS calls Correios API for shipping options
   User selects shipping method
   PHP stores selection in session

3. CHECKOUT STEP 2 (/checkout-pagamento.php)
   PHP creates pedido row (status = aguardando_pagamento)
   PHP creates pedido_item rows
   PHP calls Mercado Pago SDK → creates Preference
   Browser receives init_point URL
   Browser redirects to hosted MP checkout

4. PAYMENT (Mercado Pago hosted)
   User fills payment details on Mercado Pago
   MP processes payment
   MP POSTs webhook to /mp/webhook

5. WEBHOOK PROCESSING (/checkout-pagamento.php?action=mp-webhook)
   PHP validates HMAC signature
   PHP queries MP API for final payment status
   PHP updates pedido.status (pago / cancelado)
   PHP inserts log_integracao_pedido row
   PHP clears carrinho rows for this cliente

6. CONFIRMATION (/conta.php)
   Browser redirects back to site (MP return URL)
   Browser polls /conta.php?action=mp-confirm
   PHP returns updated pedido.status
   JS shows success or failure UI
   User sees order in Minha Conta → Pedidos
```

### Admin Product Management Flow

```
Admin visits /admin/produtos.php (HTTP Basic Auth prompt)
    │
    ├── Sees grid of all products (50 per page)
    ├── Each row shows: codprod, name, syncsite toggle
    │
    └── Toggle syncsite ON/OFF
            │
            └── PHP updates produto.syncsite = 'Y' or 'N'
                    └── Product appears or disappears from storefront
                        (filtered in /api/produtos.php query)
```

---

## 13. Admin Panel

Location: `/admin/`

**Access control:** HTTP Basic Authentication using `ADMIN_USER` / `ADMIN_PASS` env vars. Every admin PHP file calls `admin_require_basic_auth()` before any output.

### Pages

| Page | Purpose |
|------|---------|
| `admin/index.php` | Redirects to `clientes.php` |
| `admin/clientes.php` | Lists all `cliente` rows; search by name/email; shows order count per client; links to order history |
| `admin/produtos.php` | Product grid with pagination; toggle `syncsite` flag to control storefront visibility |
| `admin/produto-edit.php` | Edit individual product details (name, price, category, images) |

### Admin Layout

Admin pages use their own layout files:
- `admin/layout.php` — outputs HTML head, admin CSS, and admin navigation bar
- `admin/layout-end.php` — closes HTML

The admin panel does **not** use the public site's `includes/head.php` or `includes/header.php`.

---

## 14. Local Development Mode

**Activation:** Set `LOCAL_DATA_MODE=true` in `.env`

When active, `sb()` is replaced by `sb_local()`, which reads from `data/local-db.json` instead of making HTTP calls to Supabase.

**Use cases:**
- Offline development without Supabase credentials
- Demos or presentations without internet access
- Isolated testing with controlled data

**Auth in local mode:** A simplified `/api/local-login.php` endpoint handles authentication, bypassing Supabase Auth.

**Limitations in local mode:**
- Cart sync not persisted (no DB)
- Payment flow not functional
- Correios API still requires network (can be mocked manually)

### Development Server

```bash
# PHP built-in server (no Apache required)
php -S localhost:8080

# Composer dependencies (run once)
composer install
```

---

## 15. Deployment & Hosting

### Production Environment

- **Web Server:** Apache HTTP Server with `mod_rewrite` enabled
- **PHP:** 7.4+ with cURL extension enabled
- **Hosting:** Shared or VPS Apache hosting (standard PHP hosting)
- **Database:** Supabase (cloud-managed PostgreSQL — no self-hosted DB to maintain)

### Deployment Steps

```
1. Upload all files to web root (except .env)
2. Create .env with production credentials (never commit .env to git)
3. Run: composer install --no-dev --optimize-autoloader
4. Ensure Apache mod_rewrite is enabled
5. Verify .htaccess is active (AllowOverride All in Apache config)
6. Test: curl https://yourdomain.com/api/categorias.php
```

### `.htaccess` Highlights

- Blocks direct access to `vendor/` and `composer.*`
- Rewrites `/mp/webhook` → `checkout-pagamento.php?action=mp-webhook`
- Sets aggressive cache headers for `assets/css/`, `assets/js/`, `assets/images/`
- Enables gzip compression for HTML, CSS, JS, JSON responses
- Forces HTTPS if configured

### No CI/CD Pipeline

There is no automated CI/CD. Deployments are manual file uploads. A future improvement would be to add a GitHub Actions workflow that:
1. Runs `composer install`
2. Deploys via FTP/SFTP or SSH rsync

---

## 16. Security Model

### What is Protected

| Threat | Mitigation |
|--------|-----------|
| Direct DB access | All DB access through Supabase RLS + service key only on server |
| Admin panel exposure | HTTP Basic Auth on all `/admin/` routes |
| Composer/vendor exposure | `.htaccess` blocks direct access to `vendor/` |
| Webhook spoofing | Mercado Pago HMAC-SHA256 signature validation |
| CSRF | Supabase JWT in session (short-lived, rotating) |
| SQL injection | Not applicable — PostgREST parameterizes all queries |
| XSS | PHP `htmlspecialchars()` on output (verify coverage) |

### Known Security Concerns

1. **`.env` in web root:** The `.env` file is in the document root. While `.htaccess` should block direct access, a misconfigured Apache (`AllowOverride None`) would expose it. The file contains all credentials.

2. **Admin credentials in `.env`:** `ADMIN_USER` and `ADMIN_PASS` are plain-text in `.env`. HTTP Basic Auth transmits credentials in base64 (not encrypted) unless HTTPS is enforced.

3. **Service key used in PHP:** `SUPABASE_SERVICE_KEY` bypasses all RLS policies. Any PHP code execution vulnerability would expose full DB access.

4. **No rate limiting:** The product and category APIs have no rate limiting. They rely on Supabase's own limits.

---

## 17. Known Limitations & Technical Debt

| Area | Issue | Impact |
|------|-------|--------|
| Testing | No automated test suite | Regressions undetected until manual QA |
| CI/CD | No pipeline | Deployments are manual and error-prone |
| Migrations | No schema versioning | DB schema changes are undocumented and hard to roll back |
| Error handling | PHP errors may expose stack traces in production | Security risk + poor UX |
| Cart persistence | Guest cart is lost on page refresh | Lost conversions for non-logged-in users |
| Image pipeline | Two-tier image system (external API + Supabase fallback) adds latency | Slower product page loads |
| Local mode | `local-db.json` is manually maintained | Can drift from real schema |
| Pagination | `api/produtos.php` advances through empty batches (up to 3 cycles) | Unpredictable response times for sparse categories |
| Admin auth | HTTP Basic Auth (no session, no 2FA) | Weak admin security |
| PHP version | Targets PHP 7.4 | PHP 7.4 is end-of-life; should migrate to 8.x |

---

*Document generated: 2026-05-15. Reflects codebase at `C:\util\P4`.*

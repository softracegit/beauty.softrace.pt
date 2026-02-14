# Template refactor – changed files

Template source: **SmartAdmin-pro** (project root). Assets were copied to `public/template/`. All app views still extend `partials.layouts.main`; only the layout and partials were replaced.

## New / copied

- **public/template/** – Full copy of `SmartAdmin-pro/assets/` (CSS, JS, img, vendor, etc.). All asset paths in Blade use `asset('template/...')`.

## Layouts

- **resources/views/partials/layouts/main.blade.php** – Replaced with new layout based on SmartAdmin (header, sidebar, main, footer, back-to-top). No more `#layout-wrapper`, `.app-wrapper`, or `@include('partials.horizontal')` / `@include('partials.switcher')`.
- **resources/views/partials/layouts/main-old.blade.php** – Backup of the previous main layout (can be removed after verification).

## Partials (presentation only)

- **resources/views/partials/header.blade.php** – Replaced with SmartAdmin header; logo, quick access, search, theme toggle, user dropdown with Laravel `auth()->user()` and `route('logout')`.
- **resources/views/partials/sidebar.blade.php** – Replaced with SmartAdmin sidebar (icon bar + one panel) and app menu: Dashboard, Clientes, Agentes, Leads, Imóveis, Oportunidades, Negócios, Agenda; all use `route()` and `request()->routeIs()`.
- **resources/views/partials/footer.blade.php** – Replaced with SmartAdmin-style footer and `config('app.name')`.
- **resources/views/partials/head-css.blade.php** – Replaced with SmartAdmin CSS: favicons, Google Fonts, vendor CSS, `template/css/main.css`; all paths use `asset('template/...')`.
- **resources/views/partials/vendor-scripts.blade.php** – Replaced with SmartAdmin JS: Bootstrap, theme, main, apps-sidebar-toggle; all paths use `asset('template/...')`.
- **resources/views/partials/page-title.blade.php** – Same breadcrumb logic as before; output changed to SmartAdmin’s `.page-header` + `.page-title` + `<ol class="breadcrumb">`.

## Backups (can be removed after new layout is verified)

- **resources/views/partials/layouts/main-old.blade.php**
- **resources/views/partials/head-css-old.blade.php**
- **resources/views/partials/vendor-scripts-old.blade.php**
- **resources/views/partials/footer-old.blade.php**
- **resources/views/partials/page-title-old.blade.php**

## Unchanged (no edits)

- All **routes**, **controllers**, and **PHP logic**.
- All **view files** that `@extends('partials.layouts.main')` (agenda, agentes, clientes, deals, leads, opportunities, properties, etc.) – they automatically use the new layout.
- **Auth views** that extend `partials.layouts.main-auth` – unchanged.
- **horizontal.blade.php**, **switcher.blade.php**, **scroll-to-top.blade.php** – not included in the new layout; kept on disk for reference or rollback.

## Notes

- If a page relied on the old wrapper (e.g. `.app-wrapper`, `.container-fluid`), its content is now inside `.main .main-content`; you may need small CSS tweaks in that page’s `@section('css')` or in the template CSS.
- Agenda and other pages that inject custom CSS/JS via `@section('css')` and `@section('js')` are unchanged and still work with the new layout.
- To roll back: point `main.blade.php` back to the old structure or use `main-old.blade.php`, and restore the old partials from the `-old` backups.

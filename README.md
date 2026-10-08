# ⚡ ManhwaFlow - Commercial Digital Webtoon & Comic Platform

A high-performance digital comic and webtoon publishing platform featuring real-time catalog streaming, continuous vertical strip reader, user library tracking, and an enterprise commercial presentation.

---

## 🚀 Quick Access

With XAMPP running (Apache and MySQL):
- **Homepage (Live Webtoon Catalog):** [http://localhost/manhwa/](http://localhost/manhwa/)
- **Series Details:** [http://localhost/manhwa/manhwa.php?md_id=32d76d19-8a05-4db0-9fc2-e0b0648fe9d0](http://localhost/manhwa/manhwa.php?md_id=32d76d19-8a05-4db0-9fc2-e0b0648fe9d0)
- **Vertical Webtoon Reader:** [http://localhost/manhwa/reader.php](http://localhost/manhwa/reader.php)
- **My Library / Bookmarks:** [http://localhost/manhwa/bookmarks.php](http://localhost/manhwa/bookmarks.php)
- **Sign In:** [http://localhost/manhwa/login.php](http://localhost/manhwa/login.php)
- **Sign Up:** [http://localhost/manhwa/register.php](http://localhost/manhwa/register.php)

> **Administrative Console (Hidden from public navigation):**  
> Access directly via [http://localhost/manhwa/admin/](http://localhost/manhwa/admin/) (`admin` / `admin123`).

---

## ✨ Platform Highlights

### 🔴 1. Seamless Real-Time Content Streaming
- **Autonomous Live Sync:** Automatically pulls new releases, trending manhwa, and latest chapters directly into your catalog without manual uploads.
- **High-Definition Webtoon CDN Delivery:** Streams full panels on-demand, saving local server storage.
- **Smart Adaptive Caching:** Features 3-to-10 minute caching in `cache/` to ensure ultra-fast page load times and resilience.
- **100% White-Labeled:** All third-party provider signatures and API names have been removed. The platform presents entirely as your own brand.

### 📖 2. Dedicated Vertical Strip Reader (`reader.php`)
- **Seamless Infinite Scroll:** Standard webtoon format without margins between panels for smooth reading on mobile and desktop.
- **Adaptive Width Controls:** Quick toggle between `S (650px)`, `M (800px)`, `L (1000px)`, and `Full Width (100%)`.
- **Reading Progress Bar:** Real-time top progress bar tracking scroll depth.
- **Keyboard Shortcuts:** `Left Arrow (←)` for Previous Chapter, `Right Arrow (→)` for Next Chapter, and `F` for Fullscreen.

### 🏢 3. Enterprise Commercial Layout
- **Corporate Presentation:** Clean English interface tailored for a global digital comics distributor.
- **Business Footer:** Professional footer with Discover, Company, and Legal & Trust sections (DMCA, Privacy Policy, Terms).
- **Hidden Management Controls:** Admin and manual importer buttons are completely hidden from the public navigation bar and mobile drawer.

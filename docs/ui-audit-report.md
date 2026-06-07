# UI/UX Audit Report

> Tanggal: 2026-06-07
> Tool: UI/UX Pro Max skill (Data-Dense Dashboard design system)
> Scope: Laravel 12 + Blade — admin, waiter, cashier portals
> Sifat: Audit + refactor bertahap. Brand tokens existing dipertahankan (#667eea).

---

## 1. Design System Baseline (dari skill)

**Pattern:** Data-Dense + Drill-Down (cocok retail ops dashboard)
**Style:** Data-Dense Dashboard — KPI cards, data tables, grid layout, space-efficient
**Performance:** Excellent | **Accessibility target:** WCAG AA

**Brand decision:** App sudah punya design token di `admin/layout.blade.php` `:root`
(--color-primary #667eea dst). **Pertahankan brand existing**, terapkan PRINSIP
skill (cursor, focus, transition, contrast) — bukan ganti warna.

---

## 2. Temuan Pelanggaran (skill checklist)

### CRITICAL (Accessibility + Interaction)
| Isu | Skala | Lokasi |
|-----|-------|--------|
| Missing `cursor: pointer` di elemen clickable | 299 onclick / 58 file | global |
| Focus state tak konsisten (keyboard nav) | mayoritas view | global |
| `prefers-reduced-motion` tak dihormati | semua animasi | global |
| Emoji sebagai icon (bukan SVG) | tersebar | banyak view |

### HIGH (Style + Layout)
| Isu | Skala | Lokasi |
|-----|-------|--------|
| Inline `<style>` (no token) | 57 view | tersebar |
| Inline `<script>` | 81 view | tersebar |
| Hover transition tak ada / instan (0ms) | banyak | global |
| Hardcoded hex (bukan var token) | banyak view | tersebar |

### MEDIUM (Typography + Feedback)
| Isu | Lokasi |
|-----|--------|
| Tabular figures tak dipakai di kolom angka/harga | tabel data, payroll |
| Empty state tak konsisten | beberapa tabel |
| Alert gaya beda per view | sebagian (sebagian sudah x-alert) |

---

## 3. Strategi Refactor (high-leverage dulu)

**Prinsip:** fix global di 1 tempat > edit 58 file. CSS di layout `:root`/base
menjangkau semua halaman yang extend `admin.layout`.

### Batch UI (urut ROI)
| Batch | Aksi | Leverage |
|-------|------|----------|
| UB1 | Global CSS: cursor-pointer, focus-visible, transition, reduced-motion | 1 edit → semua admin view |
| UB2 | Tabular figures untuk angka (.num / tabel) | 1 CSS class, pakai di tabel |
| UB3 | Komponen reusable lanjutan (x-stat-card, x-data-table) | kurangi duplikasi |
| UB4 | Migrasi inline alert sisa → x-alert | konsistensi |
| UB5 | (Opsional) extract inline style/script per view besar | tedious, bertahap |

### Yang DITUNDA (butuh effort besar / hati-hati)
- Extract 81 inline `<script>` → JS module (Fase 4, tedious)
- Ganti emoji→SVG icon menyeluruh (butuh icon set decision)
- waiter/cashier portal (layout beda, audit terpisah)

---

## 4. Catatan

- App data-dense ops tool — **bukan** marketing site. Density tinggi OK (sesuai
  pattern skill). Fokus = clarity + interaction feedback, bukan dekorasi.
- Mobile: waiter portal dipakai di HP — touch target ≥44px wajib (cek terpisah).
- Brand #667eea dipertahankan; skill rekomendasi dark dashboard TIDAK diadopsi
  penuh (ganti brand = scope besar + risiko, di luar audit ini).

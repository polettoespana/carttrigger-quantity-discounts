# CartTrigger – Quantity Discounts

<p>
  <img src="https://img.shields.io/badge/version-2.3.1-0a0a23?style=flat-square" alt="Version 2.3.1">
  <img src="https://img.shields.io/badge/WordPress-6.0%2B-3858e9?style=flat-square&logo=wordpress&logoColor=white" alt="WordPress 6.0+">
  <img src="https://img.shields.io/badge/WooCommerce-required-96588a?style=flat-square&logo=woocommerce&logoColor=white" alt="WooCommerce required">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/license-GPLv2-38a169?style=flat-square" alt="GPLv2">
</p>

<small>A lightweight WooCommerce plugin that encourages volume purchasing by showing a customisable cart notice when customers reach a trigger quantity, and automatically applying a discount when they hit the target — no coupons, no JavaScript, no external dependencies.</small>

---

## How it works

<small>Each rule defines two thresholds:</small>

```
Cart quantity ──► TRIGGER  →  notice shown to the customer
Cart quantity ──► TARGET   →  discount applied automatically
```

<small>When the cart reaches the <strong>trigger</strong>, a personalised message is displayed (cart, product page, or both). When it reaches the <strong>target</strong>, the discount is silently added via WooCommerce's native fee system — no coupon required.</small>

---

## Features

| # | Feature | Details |
|---|---------|---------|
| 01 | **Configurable rules** | <small>Trigger qty, target qty, discount type (% or fixed), and scope (full cart or matched items only)</small> |
| 02 | **Category & SKU filters** | <small>Limit any rule to one or more categories (subcategories auto-included) or to specific products by SKU</small> |
| 03 | **Best-discount logic** | <small>When multiple rules share the same target and scope, only the highest discount is applied — no unintended stacking</small> |
| 04 | **Coupon conflict modes** | <small>Stack (default), Exclusive, or Best-discount-wins — full control over coupon interaction</small> |
| 05 | **Dual notice positions** | <small>Independent templates and CSS classes for cart/checkout and product page</small> |
| 06 | **WPML & Polylang ready** | <small>Notice texts registered as translatable strings</small> |
| 07 | **HPOS compatible** | <small>Full WooCommerce High-Performance Order Storage support declared</small> |
| 08 | **Zero bloat** | <small>No frontend JavaScript, no external libraries</small> |

---

## Notice variables

<small>Use these placeholders inside any notice template:</small>

| Variable | Description |
|----------|-------------|
| `{current}` | <small>Current quantity in the cart</small> |
| `{missing}` | <small>Units still needed to reach the target</small> |
| `{target}` | <small>Target quantity</small> |
| `{discount}` | <small>Formatted discount value (e.g. `10%` or `€5.00`)</small> |

**Example template:**
```
Add {missing} more to get {discount} off your order!
```

---

## Coupon conflict modes

| Mode | Behaviour |
|------|-----------|
| **Stack** *(default)* | <small>Quantity discount always applies, alongside any active coupon</small> |
| **Exclusive** | <small>Quantity discount (and its notice) are skipped if any coupon is active</small> |
| **Best discount wins** | <small>Compares totals — applies quantity discount only if it exceeds the coupon discount</small> |

---

## Requirements

<small>

- WordPress **6.0** or later
- WooCommerce *(required)*
- PHP **7.4** or later

Tested with WordPress **6.9.1** and WooCommerce **10.5.2**.

</small>

---

## Installation

<small>

1. Clone this repository or download the ZIP and upload to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins** in your WordPress admin.
3. Navigate to **WooCommerce → Quantity Discounts** to configure your rules.

</small>

> <small>The plugin is pending review on the <a href="https://wordpress.org/plugins/">WordPress.org plugin directory</a>.</small>

---

## Changelog

### 2.3.1
<small>

- Conflict mode now also suppresses cart and product page notices when a coupon is active (Exclusive and Best modes), preventing a discount promise that would not be applied.

</small>

### 2.3.0
<small>

- Added **conflict mode** setting: Stack, Exclusive, or Best discount wins.

</small>

### 2.2.0
<small>

- SKU-based rule filtering (takes priority over category).
- Product page notices with dedicated template and CSS class.
- Discount scope per rule: entire cart or matched items only.
- HPOS compatibility declaration.
- Settings link in the plugin row.
- Renamed to *CartTrigger – Quantity Discounts*.

</small>

### 2.1.0
<small>

- WPML and Polylang string translation support.
- Conflict warning for rules sharing the same target with different discount values.
- Per-rule custom CSS class for frontend notices.

</small>

### 2.0.0
<small>

- Initial public release: trigger/target rules, percentage and fixed discounts, category filtering.

</small>

---

<small><a href="https://www.gnu.org/licenses/gpl-2.0.html">GPLv2 or later</a> — developed by <a href="https://poletto.es">Poletto 1976 S.L.U.</a></small>

# CartTrigger – Quantity Discounts

<p>
  <img src="https://img.shields.io/badge/version-2.3.1-0a0a23?style=flat-square" alt="Version 2.3.1">
  <img src="https://img.shields.io/badge/WordPress-6.0%2B-3858e9?style=flat-square&logo=wordpress&logoColor=white" alt="WordPress 6.0+">
  <img src="https://img.shields.io/badge/WooCommerce-required-96588a?style=flat-square&logo=woocommerce&logoColor=white" alt="WooCommerce required">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/license-GPLv2-38a169?style=flat-square" alt="GPLv2">
</p>

A lightweight WooCommerce plugin that encourages volume purchasing by showing a customisable cart notice when customers reach a trigger quantity, and automatically applying a discount when they hit the target — no coupons, no JavaScript, no external dependencies.

---

## How it works

Each rule defines two thresholds:

```
Cart quantity ──► TRIGGER  →  notice shown to the customer
Cart quantity ──► TARGET   →  discount applied automatically
```

When the cart reaches the **trigger**, a personalised message is displayed (cart, product page, or both). When it reaches the **target**, the discount is silently added via WooCommerce's native fee system — no coupon required.

---

## Features

| # | Feature | Details |
|---|---------|---------|
| 01 | **Configurable rules** | Trigger qty, target qty, discount type (% or fixed), and scope (full cart or matched items only) |
| 02 | **Category & SKU filters** | Limit any rule to one or more categories (subcategories auto-included) or to specific products by SKU |
| 03 | **Best-discount logic** | When multiple rules share the same target and scope, only the highest discount is applied — no unintended stacking |
| 04 | **Coupon conflict modes** | Stack (default), Exclusive, or Best-discount-wins — full control over coupon interaction |
| 05 | **Dual notice positions** | Independent templates and CSS classes for cart/checkout and product page |
| 06 | **WPML & Polylang ready** | Notice texts registered as translatable strings |
| 07 | **HPOS compatible** | Full WooCommerce High-Performance Order Storage support declared |
| 08 | **Zero bloat** | No frontend JavaScript, no external libraries |

---

## Notice variables

Use these placeholders inside any notice template:

| Variable | Description |
|----------|-------------|
| `{current}` | Current quantity in the cart |
| `{missing}` | Units still needed to reach the target |
| `{target}` | Target quantity |
| `{discount}` | Formatted discount value (e.g. `10%` or `€5.00`) |

**Example template:**
```
Add {missing} more to get {discount} off your order!
```

---

## Coupon conflict modes

| Mode | Behaviour |
|------|-----------|
| **Stack** *(default)* | Quantity discount always applies, alongside any active coupon |
| **Exclusive** | Quantity discount (and its notice) are skipped if any coupon is active |
| **Best discount wins** | Compares totals — applies quantity discount only if it exceeds the coupon discount |

---

## Requirements

- WordPress **6.0** or later
- WooCommerce *(required)*
- PHP **7.4** or later

Tested with WordPress **6.9.1** and WooCommerce **10.5.2**.

---

## Installation

1. Clone this repository or download the ZIP and upload to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins** in your WordPress admin.
3. Navigate to **WooCommerce → Quantity Discounts** to configure your rules.

> The plugin is pending review on the [WordPress.org plugin directory](https://wordpress.org/plugins/).

---

## Changelog

### 2.3.1
- Conflict mode now also suppresses cart and product page notices when a coupon is active (Exclusive and Best modes), preventing a discount promise that would not be applied.

### 2.3.0
- Added **conflict mode** setting: Stack, Exclusive, or Best discount wins.

### 2.2.0
- SKU-based rule filtering (takes priority over category).
- Product page notices with dedicated template and CSS class.
- Discount scope per rule: entire cart or matched items only.
- HPOS compatibility declaration.
- Settings link in the plugin row.
- Renamed to *CartTrigger – Quantity Discounts*.

### 2.1.0
- WPML and Polylang string translation support.
- Conflict warning for rules sharing the same target with different discount values.
- Per-rule custom CSS class for frontend notices.

### 2.0.0
- Initial public release: trigger/target rules, percentage and fixed discounts, category filtering.

---

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html) — developed by [Poletto 1976 S.L.U.](https://poletto.es)

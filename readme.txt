=== CartTrigger – Quantity Discounts ===
Contributors: polettoespana
Donate link: https://www.paypal.me/polettoespana
Tags: woocommerce, discount, quantity discount, bulk discount
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce

Show cart notices and apply automatic discounts when customers reach a quantity threshold — configurable per category, or SKU.

== Description ==

**CartTrigger – Quantity Discounts** is a lightweight WooCommerce plugin that encourages customers to buy more by showing a notice when they reach a trigger quantity, and automatically applying a discount when they hit the target quantity.

Each discount rule is fully configurable:

* **Trigger qty** — the cart quantity at which the promotional notice is shown
* **Target qty** — the quantity that unlocks the automatic discount
* **Discount type** — percentage (%) or fixed amount
* **Discount scope** — apply to the entire cart subtotal or only to the matched items' subtotal
* **Category filter** — limit the rule to one or more product categories (subcategories included automatically)
* **SKU filter** — target one or more specific products by SKU (takes priority over category)
* **Notice position** — show on cart/checkout only, product page only, or both
* **Custom CSS class** — style each notice independently with your own CSS
* **Conflict mode** — control how the plugin behaves when other discounts (coupons) are already active in the cart

**Key features:**

* Multiple rules with independent triggers and targets
* Best-discount logic: when multiple rules share the same target and scope, only the highest discount is applied
* Product page notices via a dedicated template with its own text and CSS class
* Coupon conflict modes: stack, exclusive, or best-discount-wins
* Full support for WPML and Polylang (notice text registered as translatable string)
* Compatible with WooCommerce HPOS (Custom Order Tables)
* No bloat — zero external dependencies, no JavaScript on the frontend

**Available notice variables:**

`{current}` — current quantity in cart
`{missing}` — quantity still needed to reach the target
`{target}` — target quantity
`{discount}` — formatted discount value (e.g. *10%* or *€5.00*)

== Installation ==

1. Upload the `poletto-bottle-discount` folder to the `/wp-content/plugins/` directory, or install the plugin directly from the WordPress plugin screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **WooCommerce → Quantity Discounts** to configure your discount rules.

== Frequently Asked Questions ==

= Does the plugin work with product variations? =

Yes. When using SKU-based rules, the plugin matches against the variation's own SKU (if set) or falls back to the parent product's SKU.

= Can I apply the discount only to specific product categories? =

Yes. Each rule has a category selector. Selecting a parent category automatically includes all its subcategories.

= What happens if two rules have the same target quantity? =

The plugin automatically applies the highest discount among all matching rules. It will never stack multiple discounts for the same target/scope group.

= Can I show a different message on the product page and in the cart? =

Yes. Under **Notice position → Product page notice** you can set a separate template (or leave it empty to reuse the cart template).

= Is it compatible with WPML and Polylang? =

Yes. The notice templates are registered as translatable strings and can be translated from the WPML String Translation or Polylang String Translations interface.

= What happens if a customer has a coupon in the cart? =

It depends on the **Conflict mode** setting (WooCommerce → Quantity Discounts → Coupon behaviour):

* **Stack** (default) — the quantity discount is always applied alongside any active coupons.
* **Exclusive** — the quantity discount is skipped entirely if any coupon is active.
* **Best discount wins** — the plugin compares its total discount against the coupon discount total and applies the quantity discount only if it is greater; otherwise the coupon takes precedence.

= Is it compatible with WooCommerce High-Performance Order Storage (HPOS)? =

Yes. The plugin declares full HPOS compatibility.

== Screenshots ==

1. Admin settings page — discount rules table with trigger, target, discount type, scope, category/SKU filters, notice text, position and conflict mode.
2. Cart page — notice shown to the customer when the trigger quantity is reached, and discount applied at checkout.
3. Product page — notice displayed below the Add to Cart button.

== Changelog ==

= 2.3.1 =
* Conflict mode now also suppresses cart and product page notices when a coupon is active (Exclusive and Best modes) — prevents showing a discount promise that would not be applied.

= 2.3.0 =
* Added conflict mode setting: Stack, Exclusive, or Best discount wins — controls how the plugin interacts with active coupons.

= 2.2.0 =
* Added SKU-based rule filtering (takes priority over category).
* Added product page notices with dedicated template and CSS class.
* Added discount scope per rule: entire cart or category subtotal only.
* Added HPOS (Custom Order Tables) compatibility declaration.
* Added Settings link in the plugin list row.
* Improved notice styling: custom CSS class now applied server-side via WooCommerce notice data.
* Renamed plugin to CartTrigger – Quantity Discounts.

= 2.1.0 =
* Added support for WPML and Polylang string translation.
* Added conflict warning for rules with the same target but different discount values.
* Added per-rule custom CSS class for frontend notices.

= 2.0.0 =
* Initial public release.
* Configurable trigger/target quantity rules.
* Percentage and fixed discount types.
* Category filtering with automatic subcategory inclusion.

== Upgrade Notice ==

= 2.3.1 =
Notices are now also suppressed in Exclusive and Best modes when a coupon is active. No breaking changes.

= 2.3.0 =
New setting: conflict mode for coupon interaction (Stack / Exclusive / Best). No breaking changes — existing rules default to Stack (previous behaviour).

= 2.2.0 =
New features: SKU filtering, product page notices, discount scope per rule. No breaking changes — existing rules continue to work unchanged.

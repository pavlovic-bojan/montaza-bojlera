## Boiler Installation Addon – Business Requirements Document (BRD)

### 1. Business Overview

- **Product**: Boiler Installation Addon (WordPress + WooCommerce plugin)
- **Purpose**: Allow WooCommerce store owners to sell boiler installation and delivery as an optional add-on to products.
- **Primary outcome**: Increase average order value and provide a smoother experience for customers who need installation together with the product.

### 2. Goals & Success Criteria

- **G1 – Increase revenue per order**: A measurable share of orders include the installation service.
- **G2 – Better customer experience**: Customers clearly understand what the service includes, limitations, and delivery times.
- **G3 – Low support overhead**: Store admins can configure price and delivery time without custom code.

### 3. Stakeholders

- **Store Owner / Admin**: Configures service price and delivery time; wants a simple, reliable setup.
- **Customer (End User)**: Buys products and optionally adds installation service.
- **Developer / Implementer**: Installs and updates the plugin; may customize integration.

### 4. Target Users & Use Cases

- **U1 – Customer adds installation on product page**  
  When viewing a compatible product, the customer can tick a checkbox to add installation and delivery to that product.

- **U2 – Customer adds installation on checkout**  
  If a product is in the cart without installation, the customer can add installation directly from the checkout page.

- **U3 – Admin configures service**  
  The admin can set:
  - Installation price
  - Delivery time (min/max days)

- **U4 – Admin sees clear order info**  
  Orders clearly show which items have installation added, and at what price.

### 5. High-Level Functional Requirements

- **F1 – Admin settings page**
  - Configure installation service price.
  - Configure minimum and maximum delivery time.

- **F2 – Product page integration**
  - Checkbox to add installation + delivery service to a specific product.
  - Clear text about delivery time and important notes (e.g. area limitation, what is/is not included).

- **F3 – Cart and checkout behavior**
  - Installation price is added to the product line item in the cart and checkout.
  - On checkout, customers can add installation for items that do not yet have it.

- **F4 – Order meta**
  - Order line items store whether installation was added and at what price.

- **F5 – Shortcode**
  - Shortcode to display a marketing/info block about the installation service (price, delivery time, notes).

### 6. Non-Functional Requirements (Initial Draft)

- **N1 – Compatibility**: Works with supported versions of WordPress, WooCommerce, and at least the Flatsome theme.
- **N2 – Performance**: Minimal impact on page load; no heavy queries or external calls.
- **N3 – Security**: Follows WordPress best practices (nonces, capabilities, escaping, sanitization).
- **N4 – UX**: Texts are clear, unambiguous, and fully in English.

### 7. Constraints & Assumptions

- Service is currently **limited to a specific area (Belgrade)**; this is communicated in all user-facing texts.
- Only **one installation price** per store instance (no per-product custom price) in the initial version.

### 8. Out of Scope (Initial)

- Per-product custom installation pricing.
- Scheduling exact installation time slots.
- Multi-language support (beyond English) in this first version.

### 9. Open Questions / To Clarify Together

- Should the service be allowed for all products, or only for a subset (e.g. specific categories or tags)?
- Should admins be able to disable the service on certain products?
- Do we need basic reporting on how often the service is used?

> We will refine this BRD together. Once the BRD is stable and agreed, we will derive a detailed PRD from it and then implement the remaining functionality step by step.


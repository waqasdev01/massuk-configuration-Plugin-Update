# Massuk Configuration

A WordPress plugin that runs the MASSUK registration forms end-to-end: Stripe Checkout, seat capacity, promo codes, transactional emails, MailerLite sync, and a database record of every complete form submission — all configurable from a tabbed wp-admin screen at **Settings → Massuk Configuration**.

## Install

1. Copy this folder to `wp-content/plugins/massuk-configuration`
2. Activate **Massuk Configuration** in WordPress
3. Open **Settings → Massuk Configuration**
4. On the **Stripe** tab: paste your Stripe **test** and **live** secret keys, set the **Mode**, and generate a **Plugin API Key**
5. Add `[muk_registration_form]` to your registration page

## Admin tabs

| Tab | What it's for |
|---|---|
| **Stripe** | Test/Live mode toggle (only the active mode's fields are shown), secret keys, webhook signing secrets, plugin API key, success/cancel URLs |
| **Discounts** | Promo codes — code, percent/fixed discount, max uses, uses so far |
| **Registrations** | Every complete form submission, filterable by type/status/search, with a "View" button showing the full raw submission |
| **Settings** | Pricing schedule, per-type default/fallback price, seat capacities, product names, registration-page intro text, Symposium promo box on/off |
| **Email** | Sender address, MailerLite token/group, and editable email subject/body templates for paid/failed/cancelled |
| **Edit** | Raw HTML / CSS / JS editor for `test.html` — saves straight back to the file |

## Payment workflow

The plugin creates a persistent payment record (`wp_muk_payments`) and a complete form-submission record (`wp_muk_submissions`) before redirecting the customer to Stripe. Stripe then calls the signed webhook, which updates both records, confirms capacity, sends a transactional email, and can synchronise a paid customer to MailerLite.

### Registrations table (`wp_muk_submissions`)

Every field submitted on either form is captured here — not just the payment metadata — with real columns for name, email, phone, date of birth, guardian details, dietary requirements, medical/SEN/access details, sparring-kit confirmation, and promo code, plus a full JSON copy of the raw submission. View and filter it from the **Registrations** tab.

## Pricing

The **Settings** tab has a pricing schedule with **one tab per month** (August, September, October by default — click "Add month" to add more). Each month has its own "price applies until" date, price in £ (e.g. `35.00`), and label. Both forms automatically use whichever month's tab covers today's date — no manual switching needed.

There's also a separate **default price (override)** field for Symposium and for Training Camp, entered in £. If you set one, it **always wins** — it overrides the monthly tabs completely, for both forms, regardless of date. Leave it blank ("None") to have the price follow the current month's tab automatically instead.

## Stripe webhook setup

1. On the **Stripe** tab, save the Test or Live webhook signing secret.
2. In the matching Stripe dashboard mode, create an endpoint at:
   `https://your-site.example/wp-json/muk/v1/stripe-webhook`
3. Subscribe it to `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`, `payment_intent.succeeded`, `payment_intent.payment_failed`, and `payment_intent.canceled`.

The endpoint checks Stripe's timestamped `Stripe-Signature` header before it accepts a webhook. Do not send an API key to this endpoint.

## MailerLite

Add a MailerLite API token and (optionally) a group ID on the **Email** tab. A paid customer is upserted into MailerLite and added to that group. By default, synchronisation requires `marketing_consent: true` in the checkout metadata; this prevents treating a ticket purchase as marketing consent. Disable that checkbox only if the client has established an appropriate lawful basis.

The integration uses MailerLite's subscriber upsert API and standard `name`, `last_name`, and `phone` fields. Use the `muk_mailerlite_fields` WordPress filter for MailerLite custom fields.

## Email templates

On the **Email** tab, edit the subject and body for the three payment outcomes (paid/failed/cancelled). Use `{name}`, `{amount}`, and `{reference}` as placeholders — they're replaced automatically when the email is sent.

## Editing the form directly (Edit tab)

The **Edit** tab shows three boxes — HTML, CSS, JS — pulled straight out of `test.html`. Saving rewrites `test.html` on disk, so the shortcode and any Elementor HTML-widget copies pick up the change immediately. Requires the plugin folder to be writable by the web server.

## Frontend shortcode

Use `[muk_registration_form]` on any page or post to render the registration
form from `test.html`. The shortcode reads that file at render time and
outputs its `<style>` and `<body>` contents, so editing `test.html` (directly
or via the **Edit** tab) is enough to update what visitors see — nothing
else needs to change. The shortcode also fills in the plugin API key
automatically, so `test.html` never needs hand-editing for that.

Do not paste the raw contents of `test.html` into a normal paragraph/text
block or a "shortcode" text field that escapes HTML — that will print the
markup as visible text instead of rendering it. Use one of:

- A WordPress **Custom HTML** block (or a **Shortcode** block containing
  `[muk_registration_form]`)
- An Elementor **Shortcode** widget containing `[muk_registration_form]`,
  or an Elementor **HTML** widget with the raw `test.html` contents pasted
  in directly

Because the CSS in `test.html` is page-wide rather than scoped to the form,
only add the shortcode once per page.

## Endpoint

### Dynamic frontend configuration

`GET /wp-json/muk/v1/config` returns the active price, availability, and Stripe product names from the plugin settings. The supplied `test.html` uses this endpoint, so price changes made in **Settings → Massuk Configuration** appear on the registration page without editing its JavaScript. The checkout endpoint also rejects an amount that does not match the currently configured price.

`POST /wp-json/muk/v1/checkout`

### Headers

| Header | Value |
|--------|--------|
| `Content-Type` | `application/json` |
| `X-MUK-API-Key` | Your plugin API key from settings |

### Body

| Field | Required | Description |
|-------|----------|-------------|
| `amount` | yes | Integer in smallest currency unit (e.g. `2500` = $25.00) |
| `currency` | yes | 3-letter code, e.g. `usd` |
| `product_name` | yes | Line item name shown on Checkout |
| `quantity` | no | Defaults to `1` |
| `success_url` | no | Overrides the default success URL |
| `cancel_url` | no | Overrides the default cancel URL |
| `customer_email` | no | Prefills email on Checkout |
| `metadata` | no | Object of string key/value pairs |
| `customer_data` | no | Complete form fields stored in the local payment record; it is not sent to Stripe |

For MailerLite marketing consent, include `"marketing_consent": "true"` in `metadata` only when the customer explicitly opted in.

### Success response

```json
{
  "id": "cs_test_...",
  "url": "https://checkout.stripe.com/c/pay/cs_test_..."
}
```

Redirect the customer to `url`.

## Example

```js
fetch('/wp-json/muk/v1/checkout', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-MUK-API-Key': 'your-plugin-api-key'
  },
  body: JSON.stringify({
    amount: 2500,
    currency: 'usd',
    product_name: 'Custom Service'
  })
})
  .then((r) => r.json())
  .then((data) => {
    if (data.url) {
      window.location.href = data.url;
    } else {
      console.error(data);
    }
  });
```

## Security notes

- Stripe secret keys never leave the server
- Callers must send the plugin API key header
- Keep the plugin API key private (treat it like a password)
- Use Test mode until you are ready for real charges

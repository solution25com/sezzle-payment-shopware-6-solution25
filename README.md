[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](https://github.com/solution25com/sezzle-payment-shopware-6-solution25/blob/main/LICENSE)

# Sezzle Payment for Shopware 6

## Introduction

The **Sezzle Payment Plugin** integrates the Sezzle **Buy Now, Pay Later** platform into your Shopware 6 store. Sezzle allows customers to split their purchase into four interest-free installments over six weeks - with the merchant receiving the full order amount upfront, less Sezzle's fee, and assuming no credit or fraud risk.

This plugin handles the full payment lifecycle: session creation, redirect to Sezzle checkout, payment finalization, refunds, and customer tokenization for repeat purchases. It also includes promotional widgets, price breakdown displays, express checkout support, and a webhook system for real-time order event notifications.

---

## Key Features

### Buy Now, Pay Later Checkout
- Redirects customers to the **Sezzle checkout** to split their payment into four interest-free installments.

### Authorize & Capture
- Choose between **Authorize only** or **Direct Capture** transaction types per Sales Channel.

### Customer Tokenization
- Securely tokenizes returning customers for faster repeat checkouts without re-entering payment details.

### Express Checkout
- Enables an accelerated **Express Checkout** flow for tokenized customers directly from the cart.

### Automatic Refund Handling
- Listens to Shopware's order refund events and automatically triggers a **refund via the Sezzle API**.

### Flexible Order Flow
- Choose between **Order then Payment** (order created first, failed payment sets order to failed) or **Payment then Order** (payment attempted first, no order created on failure).

### Popup Form Style
- Configure how the Sezzle checkout is presented: **Popup**, **Iframe**, or **Redirect**.

### Price Breakdown Widgets
- Display Sezzle installment breakdowns on **product pages** and the **cart page** to increase conversion.

### Promotional Widget & Homepage Banner
- Show a **promotional widget** and **homepage banner** to highlight Sezzle as a payment option across your storefront.

### Webhook Configuration
- Register and manage Sezzle webhooks directly from the Admin to receive real-time updates for order events (authorized, captured, refunded, released, cancelled, customer tokenized).

### Multi-Environment Support
- Switch between **Sandbox** and **Live** Sezzle environments without code changes.

### Comprehensive Logging
- Enable detailed **API logging** for troubleshooting API communication and payment events.

### Admin Customer Management
- View and manage **tokenized Sezzle customers** via a dedicated section in the Shopware Admin.

---

## Compatibility
- ✅ Shopware 6.6.x / 6.7.x
- ✅ PHP 8.1+

---

## Get Started

### Installation & Activation

#### GitHub

1. Clone the plugin into your Shopware plugins directory:

```bash
git clone https://github.com/solution25com/sezzle-payment-shopware-6-solution25.git
```

2. **Install the Plugin in Shopware 6**

    - Log in to your Shopware 6 Administration panel.
    - Navigate to **Extensions > My Extensions**.
    - Locate the plugin and click **Install**.

3. **Activate the Plugin**

    - After installation, click **Activate** to enable the plugin.
    - Run the following commands from your Shopware root:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate Sezzle
bin/console cache:clear
```

4. **Build Storefront Assets**

```bash
bin/console bundle:dump
bin/build-storefront.sh
bin/console cache:clear
```

5. **Verify Installation**

    - After activation, you will see **Sezzle Payment** in the list of installed plugins.
    - The plugin name, version, and installation date should appear.

---

## Plugin Configuration

After installing the plugin, configure your **Sezzle** credentials and options through the Shopware Administration panel.

### Accessing the Configuration

1. Go to **Settings > Extensions > Sezzle Payment**
2. Select the **Sales Channel** you want to configure
3. Set the following fields:

### Sezzle Settings

| Field | Description |
|---|---|
| **Mode** | Select `Sandbox` for testing or `Live` for production |
| **API Key Sandbox** | Your Sezzle public API key for the Sandbox environment |
| **API Password Sandbox** | Your Sezzle private API password for the Sandbox environment |
| **API Key Live** | Your Sezzle public API key for the Live environment |
| **API Password Live** | Your Sezzle private API password for the Live environment |
| **Merchant ID** | Your Sezzle Merchant ID (required) |

### Order Flow

| Field | Description |
|---|---|
| **Flow** | `Order then Payment` — order is created first; failed payment sets the order to failed. `Payment then Order` — payment is attempted first; no order is created on failure |
| **Popup Form Style** | How the Sezzle checkout is presented: `Popup`, `Iframe`, or `Redirect` |

### Authorization & Logging

| Field | Description |
|---|---|
| **Transaction Type** | `Authorize` — authorize only, capture later. `Capture` — authorize and capture immediately |
| **Enable Express Checkout** | Allow tokenized customers to check out faster directly from the cart |
| **Enable Logging** | Enable detailed API logging for debugging (default: enabled) |

### Widgets & Banners

| Field | Description |
|---|---|
| **Enable Price Breakdown on Product Pages** | Show Sezzle installment amounts on product detail pages |
| **Enable Price Breakdown on Cart Page** | Show Sezzle installment amounts on the cart page |
| **Enable Promotional Widget** | Display a Sezzle promotional widget across the storefront |
| **Enable Homepage Banner** | Display a Sezzle banner on the homepage |

### Webhook Configuration

| Field | Description |
|---|---|
| **Webhook URL** | Auto-generated URL for Sezzle to send event notifications to your store |
| **Webhook Events** | Select which events to receive: `order.authorized`, `order.captured`, `order.refunded`, `order.released`, `order.cancelled`, `customer.tokenized` |
| **Webhook UUID** | Auto-configured when the webhook is registered; do not edit manually |

Use the **Register Webhook**, **View All Webhooks**, and **Test Webhook** buttons to manage your webhook setup directly from the Admin.

---

## How It Works

### 1. Checkout with Sezzle

At checkout, the customer selects **Sezzle** as the payment method. The plugin creates a Sezzle session with the order details and redirects the customer to the Sezzle checkout page, where they confirm the installment plan.

### 2. Payment Finalization

After the customer completes the Sezzle checkout, they are redirected back to your store. The plugin retrieves the session details from Sezzle, marks the transaction as paid in Shopware, and stores all relevant Sezzle order data as order custom fields.

### 3. Customer Tokenization

If the customer consents, Sezzle tokenizes their payment details. On future purchases, tokenized customers can use the **Express Checkout** flow without re-entering their information.

### 4. Refunds

When an order transaction is set to **Refunded** or **Refunded Partially** in Shopware, the plugin automatically triggers a refund request to Sezzle via the API, keeping both systems in sync.

### 5. Webhooks

Sezzle sends real-time event notifications to your store's webhook endpoint. These payloads are stored on the order custom fields for full visibility and auditability.

---

## Uninstallation

```bash
bin/console plugin:deactivate Sezzle
bin/console plugin:uninstall Sezzle
bin/console cache:clear
```

---

## License

MIT — see [LICENSE](LICENSE) for details.

---

## Support

For questions or issues, please open a [GitHub Issue](https://github.com/solution25com/sezzle-payment-shopware-6-solution25/issues) or contact [Solution25](https://solution25.com).
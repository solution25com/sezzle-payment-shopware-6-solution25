const { PluginBaseClass } = window;

export default class SezzleExpressCheckoutPlugin extends PluginBaseClass {
    static options = {
        sdkUrl: 'https://checkout-sdk.sezzle.com/express_checkout.min.js',
        buttonContainerId: 'sezzle-smart-button-container',
        apiVersion: 'v2',
    };

    init() {
        const wrapper = document.getElementById('sezzle-plugin-wrapper');
        if (!wrapper) return;

        const isExpressEnabled = (wrapper.dataset.sezzleExpressCheckout || '0') === '1';
        if (!isExpressEnabled) return;

        this.publicKey = wrapper.dataset.sezzlePublicKey || '';
        this.apiMode = wrapper.dataset.sezzleMode || 'sandbox';
        this.amount = Number(wrapper.dataset.sezzleAmount || 0);
        this.currency = wrapper.dataset.sezzleCurrency || 'USD';

        if (!this.publicKey) {
            console.warn('[SezzleExpressCheckout] Missing public key');
            return;
        }

        this._loadSdk()
            .then(() => this._initExpressCheckout())
            .catch((e) => console.error('[SezzleExpressCheckout] SDK load failed', e));
    }

    _loadSdk() {
        return new Promise((resolve, reject) => {
            if (window.Checkout) {
                resolve();
                return;
            }

            const src = this.options.sdkUrl;
            if (document.querySelector(`script[src="${src}"]`)) {
                const timer = setInterval(() => {
                    if (window.Checkout) {
                        clearInterval(timer);
                        resolve();
                    }
                }, 50);

                setTimeout(() => {
                    clearInterval(timer);
                    reject(new Error('Checkout SDK did not become available'));
                }, 5000);

                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.type = 'text/javascript';
            script.crossOrigin = 'anonymous';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error(`Failed loading ${src}`));
            document.head.appendChild(script);
        });
    }

    async _initExpressCheckout() {
        const container = document.getElementById(this.options.buttonContainerId);
        if (!container) {
            console.error('[SezzleExpressCheckout] Button container not found');
            return;
        }

        const checkoutSdk = new window.Checkout({
            publicKey: this.publicKey,
            apiMode: this.apiMode,
            apiVersion: this.options.apiVersion,
            mode: 'popup',
        });

        await checkoutSdk.renderSezzleButton(this.options.buttonContainerId);

        const form = document.getElementById('confirmOrderForm');
        const submitBtn = form?.querySelector('button[type="submit"]');

        if (submitBtn) {
            submitBtn.style.display = 'none';
        }

        const amountInCents = Math.round(this.amount * 100);

        checkoutSdk.init({
            onClick: (event) => {
                event.preventDefault();

                checkoutSdk.startCheckout({
                    checkout_payload: {
                        express_checkout_type: 'multi-step',
                        is_express_checkout: true,
                        order: {
                            intent: 'AUTH',
                            reference_id: 'ref-' + Date.now(),
                            description: 'Order from Shopware',
                            requires_shipping_info: true,
                            items: [
                                {
                                    name: 'Cart Total',
                                    sku: 'shopware-cart',
                                    quantity: 1,
                                    price: {
                                        amount_in_cents: amountInCents,
                                        currency: this.currency,
                                    },
                                },
                            ],
                            order_amount: {
                                amount_in_cents: amountInCents,
                                currency: this.currency,
                            },
                        },
                    },
                });
            },
            onCalculateAddressRelatedCosts: async (shippingAddress, orderUuid) => {
                const shippingOptions = this._collectShopwareShippingOptions(amountInCents);

                try {
                    const res = await fetch('/sezzle/express/calculate-address-costs', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            orderUuid,
                            shippingAddress,
                            shippingOptions,
                        }),
                    });

                    return { ok: res.ok };
                } catch (e) {
                    console.error('[SezzleExpressCheckout]', e);
                    return { ok: false, error: { code: 'merchant_error' } };
                }
            },

            onComplete: async (event) => {
                const payload = event?.data ?? event ?? {};
                const orderUuid = payload.order_uuid;
                const sessionToken = payload.session_token ?? payload.session_uuid;

                if (!orderUuid || !sessionToken) {
                    console.error('[SezzleExpressCheckout] Missing Sezzle popup result data', payload);
                    return;
                }

                const body = new URLSearchParams();
                body.append('sezzleOrderUuid', orderUuid);
                body.append('sezzleSessionToken', sessionToken);

                try {
                    const res = await fetch('/sezzle/express/finalize', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    });
                    const result = await res.json().catch(() => ({}));

                    if (result?.success && result.redirectUrl) {
                        window.location.href = result.redirectUrl;
                        return;
                    }

                    console.error('[SezzleExpressCheckout] Finalize failed:', result?.error || 'Unknown error');
                } catch (e) {
                    console.error('[SezzleExpressCheckout] Finalize request failed:', e);
                }
            },
            onCancel: () => console.warn('[SezzleExpressCheckout] cancelled'),
            onFailure: (err) => console.error('[SezzleExpressCheckout] failed', err),
        });
    }

    _collectShopwareShippingOptions(amountInCents) {
        const radios = document.querySelectorAll('input[type="radio"][name="shippingMethodId"]');
        const options = [];

        radios.forEach((radio) => {
            const container = radio.closest('.shipping-method, .custom-control, .form-check, label') || radio.parentElement;
            if (!container) return;

            const text = (container.textContent || '').trim();
            const rawName = container.querySelector('strong, .shipping-method-name')?.textContent?.trim() || text.split('\n')[0];
            const name = rawName.replace(/\s*-\s*\$[0-9.,]+\s*$/, '').trim();

            const priceMatch = text.match(/\$\s*([0-9]+(?:[.,][0-9]{1,2})?)/);
            const shippingAmountInCents = priceMatch ? Math.round(parseFloat(priceMatch[1].replace(',', '.')) * 100) : 0;

            options.push({
                name,
                shipping_amount_in_cents: shippingAmountInCents,
                tax_amount_in_cents: 0,
                final_order_amount_in_cents: amountInCents + shippingAmountInCents,
                _isSelected: radio.checked,
            });
        });

        options.sort((a, b) => Number(b._isSelected) - Number(a._isSelected));
        return options.map(({ _isSelected, ...opt }) => opt);
    }
}

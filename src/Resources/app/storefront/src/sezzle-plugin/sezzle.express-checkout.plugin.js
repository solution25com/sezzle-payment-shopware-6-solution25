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
            console.error('[SezzleExpressCheckout] Missing public key');
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
                try {
                    const res = await fetch('/sezzle/express/calculate-address-costs', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            orderUuid,
                            shippingAddress,
                        }),
                    });

                    return { ok: res.ok };
                } catch (e) {
                    console.error('[SezzleExpressCheckout]', e);
                    return { ok: false };
                }
            },

            onComplete: (event) => {
                console.log('[SezzleExpressCheckout] complete', event);
            },
            onCancel: () => console.log('[SezzleExpressCheckout] cancelled'),
            onFailure: (err) => console.error('[SezzleExpressCheckout] failed', err),
        });
    }
}

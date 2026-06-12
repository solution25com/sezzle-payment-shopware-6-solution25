const { PluginBaseClass } = window;

export default class SezzlePlugin extends PluginBaseClass {
    static options = {
        wrapperId: 'sezzle-plugin-wrapper',
        sezzleButtonContainerId: 'sezzle-smart-button-container',
        apiVersion: 'v2',
    };

    init() {
        this._registerElements();
        if (!this.wrapper || !this.publicKey) return;

        this._loadCheckoutSDK();
    }

    _registerElements() {
        this.wrapper = document.getElementById(this.options.wrapperId);
        if (!this.wrapper) {
            console.warn('[SezzlePlugin] wrapper not found');
            return;
        }

        this.merchantId = this.wrapper.dataset.sezzleMerchantId;
        this.mode = this.wrapper.dataset.sezzleMode || 'sandbox'; // sandbox | production
        this.popupFormStyle = this.wrapper.dataset.sezzlePopupFormStyle || 'popup';
        this.orderPayment = this.wrapper.dataset.sezzlePaymentFlow;
        this.publicKey = this.wrapper.dataset.sezzlePublicKey;

        this.confirmOrderForm = document.getElementById('confirmOrderForm');
        this.buyButton = this.confirmOrderForm?.querySelector('button[type="submit"]');

        console.log('[SezzlePlugin] merchantId:', this.merchantId);
        console.log('[SezzlePlugin] orderPayment:', this.orderPayment);
    }

    _loadCheckoutSDK() {
        if (window.Checkout) {
            this._initCheckout();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://checkout-sdk.sezzle.com/checkout.min.js';
        script.async = true;
        script.type = 'text/javascript';
        script.crossOrigin = 'anonymous';
        script.onload = () => this._initCheckout();
        script.onerror = () => console.error('[SezzlePlugin] SDK load failed:', script.src);

        document.head.appendChild(script);
    } // ✅ IMPORTANT: close _loadCheckoutSDK here

    _initCheckout() {
        this._ensureButtonContainer();

        const checkoutSdk = new window.Checkout({
            mode: this.popupFormStyle || 'popup',
            publicKey: this.publicKey,
            apiMode: this.mode,
            apiVersion: this.options.apiVersion,
        });

        checkoutSdk.renderSezzleButton(this.options.sezzleButtonContainerId);

        const payload = {
            checkout_payload: {
                order: {
                    intent: 'AUTH',
                    reference_id: 'ORDER-' + Date.now(),
                    description: 'Order from Shopware',
                    order_amount: {
                        amount_in_cents: 10000, // TODO: replace with real amount
                        currency: 'USD',        // TODO: replace with real currency
                    },
                },
            },
        };

        const startCheckout = () => checkoutSdk.startCheckout(payload);

        if (this.orderPayment === 'payment_order' && this.confirmOrderForm) {
            this.confirmOrderForm.addEventListener('submit', (e) => {
                e.preventDefault();
                startCheckout();
            });
        }

        checkoutSdk.init({
            onClick: (event) => {
                event.preventDefault();
                startCheckout();
            },
            onComplete: (event) => this._handleOrderPaymentSuccess(event.data),
            onCancel: () => console.log('[SezzlePlugin] Checkout cancelled'),
            onFailure: (err) => console.error('[SezzlePlugin] Checkout failed', err),
        });
    }

    _ensureButtonContainer() {
        let container = document.getElementById(this.options.sezzleButtonContainerId);
        if (!container) {
            container = document.createElement('div');
            container.id = this.options.sezzleButtonContainerId;
            container.style.display = 'none';
            document.body.appendChild(container);
        }
    }

    _handleOrderPaymentSuccess(data) {
        if (!data?.order_uuid) {
            console.error('[SezzlePlugin] Missing order_uuid');
            return;
        }

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'sezzleOrderUuid';
        input.value = data.order_uuid;

        if (!this.confirmOrderForm) {
            console.error('[SezzlePlugin] confirmOrderForm not found');
            return;
        }

        this.confirmOrderForm.appendChild(input);
        this.confirmOrderForm.submit();
    }
}

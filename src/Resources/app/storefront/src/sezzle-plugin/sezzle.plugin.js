const { PluginBaseClass } = window;

export default class SezzlePlugin extends PluginBaseClass {
    static options = {
        wrapperId: 'sezzle-plugin-wrapper',
        sezzleButtonContainerId: 'sezzle-smart-button-container',
        apiVersion: 'v2',
    };

    init() {
        this._registerElements();
        if (!this.wrapper || !this.publicKey) {
            return;
        }

        if (this.popupFormStyle !== 'popup' || !this.confirmOrderForm) {
            return;
        }

        this._loadCheckoutSDK();
    }

    _registerElements() {
        this.wrapper = document.getElementById(this.options.wrapperId);
        if (!this.wrapper) {
            console.warn('[SezzlePlugin] wrapper not found');
            return;
        }

        this.mode = this.wrapper.dataset.sezzleMode || 'sandbox';
        this.popupFormStyle = this.wrapper.dataset.sezzlePopupFormStyle || 'popup';
        this.publicKey = this.wrapper.dataset.sezzlePublicKey;
        this.intent = this.wrapper.dataset.sezzleIntent || 'AUTH';
        this.referenceId = this.wrapper.dataset.sezzleReferenceId || `cart-${Date.now()}`;
        this.description = this.wrapper.dataset.sezzleDescription || 'Order from Shopware';
        this.currency = this.wrapper.dataset.sezzleCurrency || 'USD';
        this.amountInCents = Number.parseInt(this.wrapper.dataset.sezzleAmountInCents || '0', 10);

        this.buttonContainer = document.getElementById(this.options.sezzleButtonContainerId);
        this.confirmOrderForm = document.getElementById('confirmOrderForm');
        this.buyButton = this.confirmOrderForm?.querySelector('button[type="submit"]');
        this.isCheckoutInProgress = false;
        this.isSubmittingAfterPopup = false;
        this.sezzleButton = null;
        this.nativeFormSubmit = null;
        this.shouldStartCheckoutWhenReady = false;
    }

    _loadCheckoutSDK() {
        if (window.Checkout) {
            void this._initCheckout();
            return;
        }

        if (window.sezzleCheckoutSdkPromise) {
            window.sezzleCheckoutSdkPromise
                .then(() => this._initCheckout())
                .catch(() => this._handleSdkLoadFailure());
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://checkout-sdk.sezzle.com/checkout.min.js';
        script.async = true;
        script.type = 'text/javascript';
        script.crossOrigin = 'anonymous';

        window.sezzleCheckoutSdkPromise = new Promise((resolve, reject) => {
            script.onload = resolve;
            script.onerror = reject;
        });

        window.sezzleCheckoutSdkPromise
            .then(() => this._initCheckout())
            .catch(() => this._handleSdkLoadFailure());

        document.head.appendChild(script);
    }

    async _initCheckout() {
        if (this.checkoutSdk) {
            if (this.shouldStartCheckoutWhenReady) {
                this.shouldStartCheckoutWhenReady = false;
                this._startCheckout();
            }

            return;
        }

        this.checkoutSdk = new window.Checkout({
            mode: 'popup',
            publicKey: this.publicKey,
            apiMode: this.mode === 'live' ? 'live' : 'sandbox',
            apiVersion: this.options.apiVersion,
        });

        if (this.buttonContainer) {
            await this.checkoutSdk.renderSezzleButton(this.options.sezzleButtonContainerId);
            this.sezzleButton = this.buttonContainer.querySelector('button, [role="button"]');
        }

        this.checkoutSdk.init({
            onClick: (event) => {
                event?.preventDefault?.();
                this._startCheckout();
            },
            onComplete: (event) => this._handleOrderPaymentSuccess(event),
            onCancel: () => this._resetCheckoutState(),
            onFailure: (error) => {
                console.error('[SezzlePlugin] Checkout failed', error);
                this._resetCheckoutState();
            },
        });

        this.confirmOrderForm.addEventListener('submit', (event) => this._handleFormSubmit(event));
        this._patchProgrammaticSubmit();

        if (this.shouldStartCheckoutWhenReady) {
            this.shouldStartCheckoutWhenReady = false;
            this._startCheckout();
        }
    }

    _handleFormSubmit(event) {
        if (!this._shouldOpenPopup()) {
            return;
        }

        event.preventDefault();
        this._startCheckoutFromSubmit();
    }

    _patchProgrammaticSubmit() {
        if (this.nativeFormSubmit) {
            return;
        }

        this.nativeFormSubmit = HTMLFormElement.prototype.submit.bind(this.confirmOrderForm);
        this.confirmOrderForm.submit = () => this._handleProgrammaticSubmit();
    }

    _handleProgrammaticSubmit() {
        if (!this._shouldOpenPopup()) {
            this._submitFormNative();
            return;
        }

        this._startCheckoutFromSubmit();
    }

    _startCheckoutFromSubmit() {
        this._setBuyButtonDisabled(true);

        if (!this.checkoutSdk) {
            this.shouldStartCheckoutWhenReady = true;
            void this._loadCheckoutSDK();
            return;
        }

        if (this.sezzleButton) {
            this.sezzleButton.click();
            return;
        }

        this._startCheckout();
    }

    _shouldOpenPopup() {
        return !this.isSubmittingAfterPopup && this._isSezzleSelected();
    }

    _isSezzleSelected() {
        const selectedPaymentInput = document.querySelector(
            'input[type="radio"][name="paymentMethodId"]:checked'
        );

        if (!selectedPaymentInput) {
            return true;
        }

        return selectedPaymentInput.value === this.wrapper.dataset.paymentMethodId;
    }

    _startCheckout() {
        if (this.isCheckoutInProgress || !this.checkoutSdk) {
            return;
        }

        this.isCheckoutInProgress = true;

        try {
            this.checkoutSdk.startCheckout({
                checkout_payload: {
                    order: {
                        intent: this.intent,
                        reference_id: this.referenceId,
                        description: this.description,
                        order_amount: {
                            amount_in_cents: this.amountInCents,
                            currency: this.currency,
                        },
                    },
                },
            });
        } catch (error) {
            console.error('[SezzlePlugin] Checkout failed', error);
            this._resetCheckoutState();
        }
    }

    _handleSdkLoadFailure() {
        console.error('[SezzlePlugin] Failed to load Sezzle SDK');
        this._resetCheckoutState();
    }

    _resetCheckoutState() {
        this.isCheckoutInProgress = false;
        this._removeFormLoadingIndicator();
        this._setBuyButtonDisabled(false);
    }

    _removeFormLoadingIndicator() {
        this.confirmOrderForm?.dispatchEvent(new Event('removeLoader'));
    }

    _setBuyButtonDisabled(isDisabled) {
        if (this.buyButton) {
            this.buyButton.disabled = isDisabled;
        }
    }

    _handleOrderPaymentSuccess(eventOrData) {
        if (!this.confirmOrderForm) {
            this._resetCheckoutState();
            return;
        }

        const payload = this._extractCompletionPayload(eventOrData) || {};
        const orderUuid = this._firstValue(payload.order_uuid, payload.orderUuid, payload.order?.uuid);
        const sessionToken = this._firstValue(payload.session_token, payload.sessionToken, payload.token, payload.session?.token);
        const sessionUuid = this._firstValue(payload.session_uuid, payload.sessionUuid, payload.uuid, payload.session?.uuid);
        const checkoutUuid = this._firstValue(payload.checkout_uuid, payload.checkoutUuid, payload.checkout?.uuid);

        if (!orderUuid || (!sessionToken && !sessionUuid)) {
            console.error('[SezzlePlugin] Missing Sezzle popup result data');
            this._resetCheckoutState();
            return;
        }

        this._setHiddenFields({
            sezzleOrderUuid: orderUuid,
            sezzleSessionToken: sessionToken ?? sessionUuid,
            sezzleSessionUuid: sessionUuid ?? sessionToken,
            sezzleCheckoutUuid: checkoutUuid,
        });
        this.isSubmittingAfterPopup = true;
        this._setBuyButtonDisabled(false);
        this._submitFormNative();
    }

    _submitFormNative() {
        if (this.nativeFormSubmit) {
            this.nativeFormSubmit();
            return;
        }

        HTMLFormElement.prototype.submit.call(this.confirmOrderForm);
    }

    _extractCompletionPayload(eventOrData) {
        return eventOrData?.data ?? eventOrData?.payload ?? eventOrData ?? null;
    }

    _firstValue(...values) {
        return values.find((value) => value !== undefined && value !== null && value !== '');
    }

    _setHiddenFields(fields) {
        Object.entries(fields).forEach(([name, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                this._setHiddenField(name, value);
            }
        });
    }

    _setHiddenField(name, value) {
        let field = this.confirmOrderForm.querySelector(`input[name="${name}"]`);

        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            this.confirmOrderForm.appendChild(field);
        }

        field.value = value;
    }
}

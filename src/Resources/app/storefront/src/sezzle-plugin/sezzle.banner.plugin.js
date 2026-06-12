// sezzle.banner.plugin.js
const { PluginBaseClass } = window;

export default class SezzleBannerPlugin extends PluginBaseClass {
    static options = {
        theme: 'indigo',
        sdkUrl: 'https://checkout-sdk.sezzle.com/sezzle-home-banner.min.js',
        modalUrl: 'https://media.sezzle.com/shopify-app/assets/sezzle-modal-4.0.4.html',
    };

    init() {
        console.log('[SezzleBannerPlugin] init START', this.el);

        this.merchantId = this.el?.dataset?.sezzleMerchantId || '';
        this.theme = this.el?.dataset?.sezzleTheme || this.options.theme;

        console.log('[SezzleBannerPlugin] data', {
            merchantId: this.merchantId,
            mode: this.mode,
            theme: this.theme,
        });

        if (!this.merchantId) {
            console.warn('[SezzleBannerPlugin] missing merchantId, banner will not render');
            return;
        }
        this.renderBanner();
    }

    renderBanner() {
        this.el.innerHTML = this.getBannerTemplate();

        const link = this.el.querySelector('.sezzle-banner-link');
        if (link) {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.currentTarget.id = 'sezzle-modal-return';
                this.renderModal();
            });
        }
    }

    getBannerTemplate() {
        return `
            <div class="sezzle-banner-container ${this.theme}">
                <div class="sezzle-banner-content">
                    <div class="sezzle-banner-logo">
                        ${this.getLogoSvg()}
                    </div>
                    <span class="sezzle-banner-text">
                        Shop Now, Pay Later with Sezzle.
                        <a href="#" class="sezzle-banner-link" aria-haspopup="dialog">Learn more</a>
                    </span>
                </div>
            </div>
        `;
    }

    disableBodyScroll(disable) {
        const bodyElement = document.body;

        if (disable) {
            this.scrollDistance =
                window.pageYOffset ||
                document.documentElement.scrollTop ||
                document.body.scrollTop ||
                0;

            bodyElement.classList.add('sezzle-modal-open');
            bodyElement.style.top = `${this.scrollDistance * -1}px`;
        } else {
            bodyElement.classList.remove('sezzle-modal-open');
            bodyElement.style.top = '0';
            window.scrollTo(0, this.scrollDistance || 0);

            const modal = document.querySelector('.sezzle-modal');
            if (modal) modal.scrollTop = 0;

            this.scrollDistance = 0;
        }
    }

    handleModalClose(modalNode) {
        this.disableBodyScroll(false);

        modalNode.style.display = 'none';

        const modalCore = modalNode.getElementsByClassName('sezzle-modal')[0];
        if (modalCore) {
            modalCore.className = 'sezzle-modal sezzle-checkout-modal-hidden';
        }

        const newFocus =
            document.querySelector('#sezzle-modal-return') ||
            document.querySelector('.sezzle-banner-container');

        if (newFocus) {
            newFocus.focus();
            newFocus.removeAttribute('id');
        }
    }

    addModalCloseListeners(modalNode) {
        document.querySelectorAll('.close-sezzle-modal, .close-btn').forEach((el) => {
            el.addEventListener('click', () => this.handleModalClose(modalNode));
        });

        const core = document.querySelector('#sezzle-modal-core-content');
        core?.addEventListener('click', (e) => e.stopPropagation());
    }

    async getModalContent(modalNode) {
        const existingCore = document.getElementById('sezzle-modal-core-content');
        if (existingCore?.innerHTML) return;

        const response = await this.httpRequestWrapper('GET', this.options.modalUrl);
        if (!response) return;

        modalNode.innerHTML = response;

        const inlineScript = modalNode.querySelector('script');
        if (inlineScript?.innerHTML) {
            const headScript = document.createElement('script');
            headScript.innerHTML = inlineScript.innerHTML;
            document.head.appendChild(headScript);
        }

        if (window.ModalUI?.load) {
            window.ModalUI.load();
        }
    }

    createModal() {
        const existing = document.getElementsByClassName('sezzle-checkout-modal-lightbox');
        if (existing.length) return existing[0];

        const modalNode = document.createElement('section');
        modalNode.className = 'sezzle-checkout-modal-lightbox close-sezzle-modal';
        modalNode.style.display = 'none';
        modalNode.role = 'dialog';
        modalNode.style.maxHeight = '100%';

        document.body.appendChild(modalNode);
        return modalNode;
    }

    renderModal() {
        this.disableBodyScroll(true);

        const modalNode = this.createModal();
        this.getModalContent(modalNode).catch((e) => console.error(e));
        this.addModalCloseListeners(modalNode);

        modalNode.style.display = 'block';
        modalNode.focus();

        const modals = modalNode.getElementsByClassName('sezzle-modal');
        if (modals.length) {
            modals[0].className = 'sezzle-modal';
        }
    }

    async httpRequestWrapper(method, url, body = null) {
        try {
            const options = { method, headers: {} };

            if (body !== null) {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(body);
            }

            const response = await fetch(url, options);
            if (!response.ok) throw new Error('Sezzle request failed');

            return await response.text();
        } catch (e) {
            console.error(e.message);
            return null;
        }
    }

    getLogoSvg() {
        return `
<svg width="18" height="21" viewBox="0 0 18 21" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M1 1" />
</svg>
        `;
    }
}

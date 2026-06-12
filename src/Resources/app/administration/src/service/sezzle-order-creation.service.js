const { ApiService } = Shopware.Classes;

class SezzleOrderCreationService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sezzle') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'sezzleOrderCreationService';
    }

    /**
     * Create order and charge customer
     */
    createOrderAndCharge(customerId, orderData) {
        const apiRoute = `/_action/${this.getApiBasePath()}/customers/${customerId}/create-order`;

        return this.httpClient
            .post(apiRoute, orderData, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Search for products
     */
    searchProducts(term, limit = 25, salesChannelId = null) {
        const apiRoute = `/_action/${this.getApiBasePath()}/products/search`;
        const params = {
            term,
            limit,
        };

        if (salesChannelId) {
            params.salesChannelId = salesChannelId;
        }

        return this.httpClient
            .get(apiRoute, {
                params,
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default SezzleOrderCreationService;


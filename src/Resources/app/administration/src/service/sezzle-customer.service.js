const ApiService = Shopware.Classes.ApiService;

class SezzleCustomerService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sezzle') {
        super(httpClient, loginService, apiEndpoint);
    }

    listCustomers(limit = 25, offset = 0) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                '/sezzle/customers',
                {
                    params: { limit, offset },
                    headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getCustomerDetails(customerId) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `/sezzle/customers/${customerId}`,
                {
                    headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    syncCustomers(salesChannelId) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                '/_action/sezzle/customers/sync',
                { salesChannelId },
                { headers }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    chargeCustomer(customerUuid, orderData) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `/_action/sezzle/customers/${customerUuid}/charge`,
                orderData,
                { headers }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default SezzleCustomerService;


const ApiService = Shopware.Classes.ApiService;

export default class SezzleCustomerService {
    constructor(httpClient) {
        this.httpClient = httpClient;
    }

    async syncCustomers(salesChannelId) {
        const response = await this.httpClient.post(
            '_action/sezzle/customers/sync',
            { salesChannelId }
        );
        return response.data;
    }

}

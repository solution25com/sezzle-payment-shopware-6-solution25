const ApiService = Shopware.Classes.ApiService;

class SezzleWebhookService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sezzle') {
        super(httpClient, loginService, apiEndpoint);
    }

    testWebhook(webhookUrl, salesChannelId) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                '/_action/sezzle/webhook/test',
                {
                    webhookUrl,
                    salesChannelId,
                },
                { headers }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    registerWebhook(domain, salesChannelId) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                '/_action/sezzle/webhook/auto-configure',
                {
                    domain,
                    salesChannelId,
                },
                { headers }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    listWebhooks(salesChannelId) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                '/_action/sezzle/webhook/list',
                {
                    salesChannelId,
                },
                { headers }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    deleteWebhook(salesChannelId) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                '/_action/sezzle/webhook/delete-and-clear',
                {
                    salesChannelId,
                },
                { headers }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    deleteWebhookByUuid(webhookUuid, salesChannelId) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                '/_action/sezzle/webhook/delete-by-uuid',
                {
                    webhookUuid,
                    salesChannelId,
                },
                { headers }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getWebhookStatus(salesChannelId) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                '/_action/sezzle/webhook/status',
                {
                    params: { salesChannelId },
                    headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default SezzleWebhookService;

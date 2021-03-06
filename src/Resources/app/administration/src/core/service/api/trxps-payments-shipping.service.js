const ApiService = Shopware.Classes.ApiService;

class TrxpsPaymentsShippingService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'trxps') {
        super(httpClient, loginService, apiEndpoint);
    }

    ship(data = {itemId: null, versionId: null, quantity: null}) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/ship`,
                JSON.stringify(data),
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    total(data = {orderId: null}) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/ship/total`,
                JSON.stringify(data),
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default TrxpsPaymentsShippingService;
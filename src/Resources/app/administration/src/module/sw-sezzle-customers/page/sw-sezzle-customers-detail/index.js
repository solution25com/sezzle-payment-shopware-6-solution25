import template from './sw-sezzle-customers-detail.html.twig';
import './sw-sezzle-customers-detail.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-sezzle-customers-detail', {
    template,

    inject: ['repositoryFactory', 'sezzleCustomerService', 'sezzleOrderCreationService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            customer: null,
            isLoading: false,
            
            // Order Creation
            showOrderCreation: false,
            orderInProgress: false,
            selectedProducts: [], // Array of {id, quantity, name, price, productNumber}
            availableProducts: [], // All products loaded from repository
            productsLoading: false,
            salesChannels: [],
            selectedSalesChannel: null,
            shopwareCustomers: [],
            selectedShopwareCustomer: null,
            
            // Simple Charge (old functionality)
            chargeInProgress: false,
            chargeForm: {
                amount: '',
                currency: 'USD',
                referenceId: '',
                description: '',
            },
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('sezzle_customer');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productOptions() {
            return this.availableProducts.map(product => ({
                value: product.id,
                label: `${product.name} (${product.productNumber}) - $${product.price.toFixed(2)}`,
            }));
        },

        totalOrderAmount() {
            return this.selectedProducts.reduce((sum, product) => {
                return sum + (product.price * product.quantity);
            }, 0);
        },

        canCreateOrder() {
            return this.customer && 
                   this.customer.isTokenized && 
                   this.selectedProducts.length > 0 &&
                   this.selectedSalesChannel && 
                   this.selectedShopwareCustomer;
        },
    },

    created() {
        this.loadCustomer();
        this.loadSalesChannels();
        this.loadShopwareCustomers();
        this.loadProducts();
    },

    methods: {
        async loadCustomer() {
            this.isLoading = true;

            try {
                const customerId = this.$route.params.id;
                
                // Try to get from repository first (if it's a DB ID)
                try {
                    this.customer = await this.customerRepository.get(customerId, Shopware.Context.api);
                } catch (e) {
                    // If not found in DB, try to get from API (if it's a Sezzle UUID)
                    const response = await this.sezzleCustomerService.getCustomerDetails(customerId);
                    this.customer = response;
                }

                // Pre-select sales channel if customer has one
                if (this.customer && this.customer.salesChannelId) {
                    this.selectedSalesChannel = this.customer.salesChannelId;
                }

                // Pre-select Shopware customer if linked
                if (this.customer && this.customer.shopwareCustomerId) {
                    this.selectedShopwareCustomer = this.customer.shopwareCustomerId;
                }
            } catch (error) {
                this.createNotificationError({
                    title: 'Error',
                    message: error.message || 'An error occurred while loading customer',
                });
                this.$router.push({ name: 'sw.sezzle.customers.index' });
            } finally {
                this.isLoading = false;
            }
        },

        async loadSalesChannels() {
            try {
                const criteria = new Shopware.Data.Criteria();
                criteria.addSorting(Shopware.Data.Criteria.sort('name', 'ASC'));
                const salesChannelRepository = this.repositoryFactory.create('sales_channel');
                const response = await salesChannelRepository.search(criteria, Shopware.Context.api);
                this.salesChannels = response;
            } catch (error) {
                console.error('Error loading sales channels:', error);
            }
        },

        async loadShopwareCustomers() {
            try {
                const criteria = new Shopware.Data.Criteria();
                criteria.setLimit(100);
                criteria.addSorting(Shopware.Data.Criteria.sort('lastName', 'ASC'));
                const customerRepository = this.repositoryFactory.create('customer');
                const response = await customerRepository.search(criteria, Shopware.Context.api);
                this.shopwareCustomers = response;
            } catch (error) {
                console.error('Error loading Shopware customers:', error);
            }
        },

        async loadProducts() {
            this.productsLoading = true;
            try {
                const criteria = new Shopware.Data.Criteria();
                criteria.addFilter(
                    Shopware.Data.Criteria.equals('active', true)
                );
                criteria.setLimit(500); // Load up to 500 products
                criteria.addSorting(Shopware.Data.Criteria.sort('name', 'ASC'));

                const products = await this.productRepository.search(criteria, Shopware.Context.api);
                
                this.availableProducts = [];
                products.forEach(product => {
                    // Get the price
                    let price = 0;
                    if (product.price && product.price.length > 0) {
                        price = product.price[0].gross || product.price[0].net || 0;
                    }

                    this.availableProducts.push({
                        id: product.id,
                        name: product.translated?.name || product.name,
                        productNumber: product.productNumber,
                        price: price,
                    });
                });
            } catch (error) {
                this.createNotificationError({
                    title: 'Error',
                    message: 'Failed to load products: ' + (error.message || 'Unknown error'),
                });
            } finally {
                this.productsLoading = false;
            }
        },

        onProductSelect(productId) {
            // Check if already added
            const existing = this.selectedProducts.find(p => p.id === productId);
            if (existing) {
                this.createNotificationWarning({
                    title: 'Already Added',
                    message: 'This product is already in your order. Adjust the quantity instead.',
                });
                return;
            }

            // Find product details
            const product = this.availableProducts.find(p => p.id === productId);
            if (!product) {
                return;
            }

            // Add to selected products
            this.selectedProducts.push({
                id: product.id,
                name: product.name,
                productNumber: product.productNumber,
                price: product.price,
                quantity: 1,
            });
        },

        async onProductsChange_OLD(productIds) {
            // When products are selected/deselected in the multi-select
            // Load full product details for newly added products
            
            const newProductIds = productIds.filter(id => !this.selectedProducts.find(p => p.id === id));
            const removedProductIds = this.selectedProducts
                .filter(p => !productIds.includes(p.id))
                .map(p => p.id);

            // Remove deselected products
            this.selectedProducts = this.selectedProducts.filter(p => productIds.includes(p.id));

            // Load and add new products
            if (newProductIds.length > 0) {
                try {
                    const criteria = new Shopware.Data.Criteria(newProductIds);
                    criteria.addAssociation('cover');
                    
                    const products = await this.productRepository.search(criteria, Shopware.Context.api);

                    products.forEach(product => {
                        // Get the price - try different price structures
                        let price = 0;
                        if (product.price && product.price.length > 0) {
                            price = product.price[0].gross || product.price[0].net || 0;
                        }

                        this.selectedProducts.push({
                            id: product.id,
                            name: product.translated?.name || product.name,
                            productNumber: product.productNumber,
                            price: price,
                            quantity: 1,
                        });
                    });
                } catch (error) {
                    this.createNotificationError({
                        title: 'Error',
                        message: 'Failed to load product details: ' + (error.message || 'Unknown error'),
                    });
                }
            }
        },

        removeProduct(index) {
            this.selectedProducts.splice(index, 1);
        },

        updateQuantity(index, quantity) {
            const qty = parseInt(quantity);
            if (qty > 0) {
                this.selectedProducts[index].quantity = qty;
            } else {
                this.removeProduct(index);
            }
        },

        async createOrderAndCharge() {
            if (!this.canCreateOrder) {
                this.createNotificationError({
                    title: 'Validation Error',
                    message: 'Please select products, sales channel, and Shopware customer',
                });
                return;
            }

            this.orderInProgress = true;

            try {
                const orderData = {
                    products: this.selectedProducts.map(p => ({
                        productId: p.id,
                        quantity: p.quantity,
                    })),
                    salesChannelId: this.selectedSalesChannel,
                    shopwareCustomerId: this.selectedShopwareCustomer,
                };

                const response = await this.sezzleOrderCreationService.createOrderAndCharge(
                    this.customer.id || this.customer.sezzleCustomerUuid,
                    orderData
                );

                if (response.success) {
                    const sezzleOrderId = response.sezzleOrderUuid ? ` | Sezzle Order: ${response.sezzleOrderUuid}` : '';
                    this.createNotificationSuccess({
                        title: '✅ Order Created & Customer Charged!',
                        message: `Shopware Order: ${response.orderId}${sezzleOrderId}\n\nThe customer has been successfully charged via Sezzle.`,
                    });

                    // Reset form
                    this.selectedProducts = [];
                    this.showOrderCreation = false;

                    // Reload customer to see updated data
                    await this.loadCustomer();
                } else {
                    let errorMessage = response.error || 'Failed to create order';
                    let title = 'Error';
                    
                    if (response.orderCreated && !response.chargeSuccess) {
                        title = '⚠️ Partial Success';
                        errorMessage = `Order was created in Shopware (ID: ${response.orderId}), but the Sezzle charge failed:\n\n${response.error}\n\nPlease charge the customer manually or retry.`;
                    }

                    this.createNotificationError({
                        title: title,
                        message: errorMessage,
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: 'Error',
                    message: error.message || 'Failed to create order and charge customer',
                });
            } finally {
                this.orderInProgress = false;
            }
        },

        // Legacy simple charge functionality
        async chargeCustomer() {
            if (!this.chargeForm.amount || !this.chargeForm.currency) {
                this.createNotificationError({
                    title: 'Validation Error',
                    message: 'Amount and currency are required',
                });
                return;
            }

            this.chargeInProgress = true;

            try {
                const payload = {
                    salesChannelId: this.customer.salesChannelId,
                    amount: {
                        amount_in_cents: Math.round(parseFloat(this.chargeForm.amount) * 100),
                        currency: this.chargeForm.currency,
                    },
                    currency: this.chargeForm.currency, // Also at top level
                    reference_id: this.chargeForm.referenceId || `charge-${Date.now()}`,
                    description: this.chargeForm.description || 'Charge customer',
                };

                const response = await this.sezzleCustomerService.chargeCustomer(
                    this.customer.sezzleCustomerUuid,
                    payload
                );

                if (response.success) {
                    this.createNotificationSuccess({
                        title: 'Charge Successful',
                        message: 'Customer charged successfully',
                    });
                    this.chargeForm = {
                        amount: '',
                        currency: 'USD',
                        referenceId: '',
                        description: '',
                    };
                } else {
                    this.createNotificationError({
                        title: 'Charge Failed',
                        message: response.error || 'Failed to charge customer',
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: 'Charge Failed',
                    message: error.message || 'Failed to charge customer',
                });
            } finally {
                this.chargeInProgress = false;
            }
        },
    },
});

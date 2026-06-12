import template from './sw-sezzle-customers-list.html.twig';
import './sw-sezzle-customers-list.scss';

const { Component, Mixin } = Shopware;
const notificationMixin = Mixin.getByName('notification');

Component.register('sw-sezzle-customers-list', {
    template,

    inject: ['repositoryFactory', 'sezzleCustomerService'],

    mixins: [
        Mixin.getByName('listing'),
        notificationMixin,
    ],

    data() {
        return {
            customers: [],
            isLoading: false,
            showDeleteModal: false,
            selectedCustomer: null,

            selectedSalesChannel: null,

            syncInProgress: false,
            total: 0,
            page: 1,
            limit: 25,
        };
    },

    metaInfo() {
        return {
            title: 'Sezzle Customers',
        };
    },

    computed: {
        customerRepository() {
            return this.repositoryFactory.create('sezzle_customer');
        },

        customerColumns() {
            return [
                { property: 'firstName', label: 'First Name', allowResize: true, primary: true },
                { property: 'lastName',  label: 'Last Name',  allowResize: true },
                { property: 'email',     label: 'Email',     allowResize: true },
                { property: 'phone',     label: 'Phone',     allowResize: true },
                { property: 'isTokenized', label: 'Tokenized', allowResize: true },
                { property: 'createdAt', label: 'Created At', allowResize: true },
            ];
        },
    },

    created() {
        this.getList();
    },

    methods: {
        safeNotify(type, payload) {
            const methodName = `createNotification${type}`;

            if (typeof this[methodName] === 'function') {
                this[methodName](payload);
                return;
            }

            console.warn('Notification method not found:', methodName, payload);
        },

        async getList() {
            this.isLoading = true;

            try {
                const response = await this.sezzleCustomerService.listCustomers(
                    this.limit,
                    (this.page - 1) * this.limit
                );

                this.customers = response.data || [];
                this.total = response.total || 0;
            } catch (error) {
                this.safeNotify('Error', {
                    title: 'Error',
                    message: error.message || 'An error occurred while loading customers',
                });
            } finally {
                this.isLoading = false;
            }
        },

        async syncCustomers() {
            if (!this.selectedSalesChannel) {
                this.safeNotify('Error', {
                    title: 'Error',
                    message: 'Please select a sales channel',
                });
                return;
            }

            this.syncInProgress = true;

            try {
                const response = await this.sezzleCustomerService.syncCustomers(this.selectedSalesChannel);

                if (response.success) {
                    this.safeNotify('Success', {
                        title: 'Sync Successful',
                        message: `Successfully synced ${response.synced} customers`,
                    });

                    await this.getList();
                } else {
                    this.safeNotify('Error', {
                        title: 'Sync Failed',
                        message: response.error || 'Failed to sync customers from Sezzle',
                    });
                }
            } catch (error) {
                this.safeNotify('Error', {
                    title: 'Sync Failed',
                    message: error.response?.data?.error || error.message || 'Failed to sync customers from Sezzle',
                });
            } finally {
                this.syncInProgress = false;
            }
        },

        onSalesChannelChanged(salesChannelId) {
            this.selectedSalesChannel = salesChannelId;
        },

        onEdit(customer) {
            this.$router.push({
                name: 'sw.sezzle.customers.detail',
                params: { id: customer.id || customer.sezzleCustomerUuid },
            });
        },

        formatDate(date) {
            if (!date) return '-';
            return this.$options.filters.date(date);
        },

        formatBoolean(value) {
            return value ? 'Yes' : 'No';
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.getList();
        },

        onSortColumn() {
            this.getList();
        },

        onSearch() {
            this.getList();
        },
    },
});

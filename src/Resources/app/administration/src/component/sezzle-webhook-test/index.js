import template from './sezzle-webhook-test.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sezzle-webhook-test', {
    template,

    inject: ['sezzleWebhookService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isTestingWebhook: false,
            isRegisteringWebhook: false,
            isDeletingWebhook: false,
            isLoadingWebhooks: false,
            webhookConfigured: false,
            webhookUuid: null,
            webhooksList: [],
            showWebhooksList: false,
        };
    },

    computed: {
        webhookUrl() {
            return `${window.location.origin}/sezzle/webhook`;
        },
        
        salesChannelId() {
            // Try multiple ways to get the sales channel ID from parent config
            // Empty string is acceptable for global configuration
            return this.$parent?.$parent?.currentSalesChannelId 
                || this.$parent?.$parent?.actualConfigData?.null?.salesChannelId 
                || '';
        },
    },

    created() {
        this.checkWebhookStatus();
    },

    methods: {
        async checkWebhookStatus() {
            try {
                const response = await this.sezzleWebhookService.getWebhookStatus(this.salesChannelId);
                this.webhookConfigured = response.configured || false;
                this.webhookUuid = response.webhookUuid || null;
            } catch (error) {
                console.error('Failed to check webhook status:', error);
            }
        },

        async loadWebhooksList() {
            this.isLoadingWebhooks = true;
            this.showWebhooksList = true;

            try {
                const response = await this.sezzleWebhookService.listWebhooks(this.salesChannelId);

                if (response.success) {
                    this.webhooksList = response.webhooks || [];
                    
                    if (this.webhooksList.length === 0) {
                        this.createNotificationInfo({
                            title: 'No Webhooks Found',
                            message: 'No webhooks are currently registered with Sezzle',
                        });
                    }
                } else {
                    this.createNotificationError({
                        title: 'Failed to Load Webhooks',
                        message: response.error || 'Could not retrieve webhooks from Sezzle',
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: 'Failed to Load Webhooks',
                    message: error.message || 'Could not retrieve webhooks from Sezzle',
                });
            } finally {
                this.isLoadingWebhooks = false;
            }
        },

        async deleteWebhookByUuid(webhookUuid) {
            if (!confirm(`Are you sure you want to delete this webhook?\nUUID: ${webhookUuid}`)) {
                return;
            }

            try {
                const response = await this.sezzleWebhookService.deleteWebhookByUuid(webhookUuid, this.salesChannelId);

                if (response.success) {
                    this.createNotificationSuccess({
                        title: 'Webhook Deleted',
                        message: 'Webhook has been successfully deleted from Sezzle',
                    });
                    
                    // Reload the list
                    await this.loadWebhooksList();
                    await this.checkWebhookStatus();
                } else {
                    this.createNotificationError({
                        title: 'Webhook Deletion Failed',
                        message: response.error || 'Failed to delete webhook',
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: 'Webhook Deletion Failed',
                    message: error.message || 'Failed to delete webhook',
                });
            }
        },

        async deleteWebhook() {
            if (!confirm('Are you sure you want to delete the webhook? You will need to register it again.')) {
                return;
            }

            this.isDeletingWebhook = true;

            try {
                const response = await this.sezzleWebhookService.deleteWebhook(this.salesChannelId);

                if (response.success) {
                    this.createNotificationSuccess({
                        title: 'Webhook Deleted',
                        message: 'Webhook has been successfully deleted',
                    });
                    this.webhookConfigured = false;
                    this.webhookUuid = null;
                    await this.loadWebhooksList();
                } else {
                    this.createNotificationError({
                        title: 'Webhook Deletion Failed',
                        message: response.error || 'Failed to delete webhook',
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: 'Webhook Deletion Failed',
                    message: error.message || 'Failed to delete webhook',
                });
            } finally {
                this.isDeletingWebhook = false;
            }
        },

        async testWebhook() {
            this.isTestingWebhook = true;

            try {
                const response = await this.sezzleWebhookService.testWebhook(
                    this.webhookUrl,
                    this.salesChannelId
                );

                if (response.success) {
                    this.createNotificationSuccess({
                        title: 'Webhook Test Successful',
                        message: 'Webhook connection is working correctly',
                    });
                } else {
                    this.createNotificationError({
                        title: 'Webhook Test Failed',
                        message: response.error || 'Failed to test webhook connection',
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: 'Webhook Test Failed',
                    message: error.message || 'Failed to test webhook connection',
                });
            } finally {
                this.isTestingWebhook = false;
            }
        },

        async registerWebhook() {
            this.isRegisteringWebhook = true;

            try {
                const response = await this.sezzleWebhookService.registerWebhook(
                    window.location.origin,
                    this.salesChannelId
                );

                if (response.success) {
                    const message = response.isDuplicate 
                        ? 'Webhook was already registered (this is OK)' 
                        : 'Webhook has been successfully registered with Sezzle';
                    this.createNotificationSuccess({
                        title: 'Webhook Registered',
                        message: message,
                    });
                    
                    // Update local state
                    this.webhookConfigured = true;
                    this.webhookUuid = response.webhookUuid || null;
                    
                    // Reload config to show saved UUID
                    if (this.$parent && this.$parent.$parent && typeof this.$parent.$parent.loadCurrentSalesChannelConfig === 'function') {
                        this.$parent.$parent.loadCurrentSalesChannelConfig();
                    }
                } else {
                    this.createNotificationError({
                        title: 'Webhook Registration Failed',
                        message: response.error || 'Failed to register webhook with Sezzle',
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('sezzle.webhook.register.errorTitle'),
                    message: error.message || this.$tc('sezzle.webhook.register.errorMessage'),
                });
            } finally {
                this.isRegisteringWebhook = false;
            }
        },
    },
});


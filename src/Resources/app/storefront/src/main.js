import SezzlePlugin from './sezzle-plugin/sezzle.plugin';
import SezzleExpressCheckoutPlugin from './sezzle-plugin/sezzle.express-checkout.plugin';


const PluginManager = window.PluginManager;

PluginManager.register('SezzlePlugin', SezzlePlugin, '[data-sezzle-plugin]');
PluginManager.register('SezzleExpressCheckoutPlugin', SezzleExpressCheckoutPlugin, '#sezzle-plugin-wrapper');



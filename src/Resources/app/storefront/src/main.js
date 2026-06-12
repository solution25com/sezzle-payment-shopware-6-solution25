import SezzlePlugin from './sezzle-plugin/sezzle.plugin';
import SezzleBannerPlugin from './sezzle-plugin/sezzle.banner.plugin';
import SezzleExpressCheckoutPlugin from './sezzle-plugin/sezzle.express-checkout.plugin';


const PluginManager = window.PluginManager;

PluginManager.register('SezzlePlugin', SezzlePlugin, '[data-sezzle-plugin]');
PluginManager.register('SezzleBannerPlugin', SezzleBannerPlugin, '[data-sezzle-homepage-banner="true"]');
PluginManager.register('SezzleExpressCheckoutPlugin', SezzleExpressCheckoutPlugin, '#sezzle-plugin-wrapper');



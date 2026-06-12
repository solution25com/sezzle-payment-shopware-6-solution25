<?php
declare(strict_types=1);
namespace Sezzle;
use Sezzle\Gateways\SezzlePaymentHandler;
use Sezzle\Gateways\SezzlePaymentMethod;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
class Sezzle extends Plugin
{
    public function install(InstallContext $context): void
    {
        $this->addPaymentMethod($context->getContext());
    }
    public function uninstall(UninstallContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
    }
    public function activate(ActivateContext $context): void
    {
        $this->setPaymentMethodIsActive(true, $context->getContext());
        parent::activate($context);
    }
    public function deactivate(DeactivateContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
        parent::deactivate($context);
    }
    private function addPaymentMethod(Context $context): void
    {
        $paymentMethodExists = $this->getPaymentMethodId($context);
        if ($paymentMethodExists) {
            return;
        }
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);
        $paymentMethod = new SezzlePaymentMethod();
        $sezzlePaymentData = [
            'handlerIdentifier' => $paymentMethod->getHandlerIdentifier(),
            'name' => $paymentMethod->getName(),
            'description' => $paymentMethod->getDescription(),
            'pluginId' => $pluginId,
            'afterOrderEnabled' => true,
            'technicalName' => $paymentMethod->getTechnicalName(),
        ];
        $this->container->get('payment_method.repository')->create([$sezzlePaymentData], $context);
    }
    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        $id = $this->getPaymentMethodId($context);
        if (!$id) {
            return;
        }
        $this->container->get('payment_method.repository')->update([
            ['id' => $id, 'active' => $active],
        ], $context);
    }
    private function getPaymentMethodId(Context $context): ?string
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('handlerIdentifier', SezzlePaymentHandler::class)
        );
        return $this->container
            ->get('payment_method.repository')
            ->searchIds($criteria, $context)
            ->firstId();
    }
    public function update(UpdateContext $updateContext): void
    {
    }
    public function postInstall(InstallContext $installContext): void
    {
    }
    public function postUpdate(UpdateContext $updateContext): void
    {
    }
}

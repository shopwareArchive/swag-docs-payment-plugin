<?php declare(strict_types=1);

namespace PaymentPlugin;

use PaymentPlugin\Service\ExamplePayment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Helper\PluginIdProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;

class PaymentPlugin extends Plugin
{
    /**
     * UUID4 for your payment method.
     * It is not auto-generated for several reasons:
     * - Easy deactivate / active, because we don't have to fetch the ID first
     * - Always the same ID, even when reinstalling
     * - Easily fetch your payment method by ID by using this constant, instead of fetching the ID via technical name
     */
    public const PAYMENT_METHOD_ID = '3651742281b5496499eba1671d0e8d83';

    /**
     * The technical name of the example payment method
     */
    public const PAYMENT_METHOD_NAME = 'ExamplePayment';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
    }

    public function install(InstallContext $context): void
    {
        $this->addPaymentMethod($context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        // Only set the payment method to inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders
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
        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByTechnicalName($this->getName(), $context);

        $examplePaymentData = [
            'id' => self::PAYMENT_METHOD_ID,
            'technicalName' => self::PAYMENT_METHOD_NAME,
            'name' => 'Example payment',
            'additionalDescription' => 'Example payment description',
            // Add your payment handler here
            'class' => ExamplePayment::class,
            'pluginId' => $pluginId,
        ];

        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->upsert([$examplePaymentData], $context);
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethod = [
            'id' => self::PAYMENT_METHOD_ID,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }
}
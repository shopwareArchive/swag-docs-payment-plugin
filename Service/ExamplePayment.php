<?php declare(strict_types=1);

namespace PaymentPlugin\Service;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class ExamplePayment implements AsynchronousPaymentHandlerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepo;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(
        EntityRepositoryInterface $orderTransactionRepo,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @throws AsyncPaymentProcessException
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, Context $context): RedirectResponse
    {
        // Method that sends the return URL to the external gateway and gets a redirect URL back
        try {
            $redirectUrl = $this->sendReturnUrlToExternalGateway($transaction->getReturnUrl());
        } catch (\Exception $e) {
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        // Redirect to external gateway
        return new RedirectResponse($redirectUrl);
    }

    /**
     * @throws CustomerCanceledAsyncPaymentException
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, Context $context): void
    {
        $transactionId = $transaction->getOrderTransaction()->getId();

        // Cancelled payment?
        if ($request->query->getBoolean('cancel')) {
            throw new CustomerCanceledAsyncPaymentException(
                $transactionId,
                'Customer canceled the payment on the PayPal page'
            );
        }

        $paymentState = $request->query->getAlpha('status');

        if ($paymentState === 'completed') {
            // Payment completed, set transaction status to "paid"
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
                Defaults::ORDER_TRANSACTION_STATE_MACHINE,
                Defaults::ORDER_TRANSACTION_STATES_PAID,
                $context
            )->getId();
        } else {
            // Payment not completed, set transaction status to "open"
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
                Defaults::ORDER_TRANSACTION_STATE_MACHINE,
                Defaults::ORDER_TRANSACTION_STATES_OPEN,
                $context
            )->getId();
        }

        $transactionUpdate = [
            'id' => $transactionId,
            'stateId' => $stateId,
        ];

        $this->orderTransactionRepo->update([$transactionUpdate], $context);
    }

    private function sendReturnUrlToExternalGateway(string $returnUrl): string
    {
        $paymentProviderUrl = '';
        $requestData = [
            'returnUrl' => $returnUrl,
        ];

        // Do some API Call to your payment provider

        return $paymentProviderUrl;
    }
}

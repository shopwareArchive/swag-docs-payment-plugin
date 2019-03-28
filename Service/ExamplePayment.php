<?php declare(strict_types=1);

namespace PaymentPlugin\Service;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
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

    public function __construct(EntityRepositoryInterface $orderTransactionRepo, StateMachineRegistry $stateMachineRegistry)
    {
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function pay(PaymentTransactionStruct $transaction, Context $context): ?RedirectResponse
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_PAID, $context)->getId();

        $transactionData = [
            'id' => $transaction->getTransactionId(),
            'stateId' => $stateId,
        ];

        $this->orderTransactionRepo->update([$transactionData], $context);

        return null;
    }

    public function finalize(string $transactionId, Request $request, Context $context): void
    {
        // Cancelled payment?
        if ($request->query->getBoolean('cancel')) {
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_CANCELLED, $context)->getId();

            $transaction = [
                'id' => $transactionId,
                'stateId' => $stateId,
            ];

            $this->orderTransactionRepo->update([$transaction], $context);

            return;
        }

        $paymentState = $request->query->getAlpha('status');

        if ($paymentState === 'completed') {
            // Payment completed, set transaction status to "paid"
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_PAID, $context)->getId();
        } else {
            // Payment not completed, set transaction status to "open"
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_OPEN, $context)->getId();
        }

        $transaction = [
            'id' => $transactionId,
            'stateId' => $stateId,
        ];

        $this->orderTransactionRepo->update([$transaction], $context);
    }
}
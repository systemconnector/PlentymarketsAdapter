<?php

namespace PlentymarketsAdapter\ServiceBus\CommandHandler\Order;

use PlentyConnector\Connector\IdentityService\Exception\NotFoundException;
use PlentyConnector\Connector\IdentityService\IdentityServiceInterface;
use PlentyConnector\Connector\ServiceBus\Command\CommandInterface;
use PlentyConnector\Connector\ServiceBus\Command\HandleCommandInterface;
use PlentyConnector\Connector\ServiceBus\Command\Order\HandleOrderCommand;
use PlentyConnector\Connector\ServiceBus\CommandHandler\CommandHandlerInterface;
use PlentyConnector\Connector\TransferObject\Order\Comment\Comment;
use PlentyConnector\Connector\TransferObject\Order\Order;
use PlentymarketsAdapter\Client\ClientInterface;
use PlentymarketsAdapter\PlentymarketsAdapter;
use PlentymarketsAdapter\RequestGenerator\Order\OrderRequestGeneratorInterface;
use RuntimeException;

/**
 * Class HandleOrderCommandHandler.
 */
class HandleOrderCommandHandler implements CommandHandlerInterface
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var IdentityServiceInterface
     */
    private $identityService;

    /**
     * @var OrderRequestGeneratorInterface
     */
    private $orderRequestGeneretor;

    /**
     * HandleOrderCommandHandler constructor.
     *
     * @param ClientInterface                $client
     * @param IdentityServiceInterface       $identityService
     * @param OrderRequestGeneratorInterface $orderRequestGeneretor
     */
    public function __construct(
        ClientInterface $client,
        IdentityServiceInterface $identityService,
        OrderRequestGeneratorInterface $orderRequestGeneretor
    ) {
        $this->client = $client;
        $this->identityService = $identityService;
        $this->orderRequestGeneretor = $orderRequestGeneretor;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(CommandInterface $command)
    {
        return $command instanceof HandleOrderCommand &&
            $command->getAdapterName() === PlentymarketsAdapter::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(CommandInterface $command)
    {
        /**
         * @var HandleCommandInterface $command
         * @var Order                  $order
         */
        $order = $command->getTransferObject();

        $identity = $this->identityService->findOneBy([
            'objectIdentifier' => $order->getIdentifier(),
            'objectType' => Order::TYPE,
            'adapterName' => PlentymarketsAdapter::NAME,
        ]);

        if ($identity !== null) {
            return true;
        }

        if ($this->isExistingOrder($order->getOrderNumber())) {
            return true;
        }

        $result = $this->handleOrder($order);

        if ($result) {
            $this->handleComments($order);
        }

        return true;
    }

    /**
     * @param Order $order
     *
     * @throws NotFoundException
     *
     * @return bool
     */
    private function handleOrder(Order $order)
    {
        $params = $this->orderRequestGeneretor->generate($order);
        $result = $this->client->request('post', 'orders', $params);

        $this->identityService->create(
            $order->getIdentifier(),
            Order::TYPE,
            (string) $result['id'],
            PlentymarketsAdapter::NAME
        );

        return true;
    }

    /**
     * @param string $orderNumber
     *
     * @return bool
     */
    private function isExistingOrder($orderNumber)
    {
        $result = $this->client->request('GET', 'orders', [
            'externalOrderId' => $orderNumber,
        ]);

        if (!empty($result)) {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     *
     * @throws NotFoundException
     */
    private function handleComments(Order $order)
    {
        $orderIdentity = $this->identityService->findOneBy([
            'objectIdentifier' => $order->getIdentifier(),
            'objectType' => Order::TYPE,
            'adapterName' => PlentymarketsAdapter::NAME,
        ]);

        if (null === $orderIdentity) {
            throw new NotFoundException('could not find order for comment handling - ' . $order->getIdentifier());
        }

        foreach ($order->getComments() as $comment) {
            $commentParams = [
                'referenceType' => 'order',
                'referenceValue' => $orderIdentity->getAdapterIdentifier(),
                'text' => $comment->getComment(),
                'isVisibleForContact' => $comment->getType() === Comment::TYPE_CUSTOMER,
            ];

            if ($comment->getType() === Comment::TYPE_INTERNAL) {
                $commentParams['userId'] = $this->getUserId();
            }

            $this->client->request('post', 'comments', $commentParams);
        }
    }

    /**
     * @throws RuntimeException
     *
     * @return int
     */
    private function getUserId()
    {
        static $user = null;

        if (null === $user) {
            $user = $this->client->request('GET', 'user');

            if (empty($user)) {
                throw new RuntimeException('could not read user data');
            }
        }

        return (int) $user['id'];
    }
}

<?php

namespace PlentymarketsAdapter\QueryBus\Handler\PaymentMethod;

use Exception;
use PlentyConnector\Connector\QueryBus\Handler\QueryHandlerInterface;
use PlentyConnector\Connector\QueryBus\Query\PaymentMethod\FetchAllPaymentMethodsQuery;
use PlentyConnector\Connector\QueryBus\Query\QueryInterface;
use PlentymarketsAdapter\Client\ClientInterface;
use PlentymarketsAdapter\PlentymarketsAdapter;
use Psr\Log\LoggerInterface;
use ShopwareAdapter\ResponseParser\ResponseParserInterface;

/**
 * Class FetchAllPaymentMethodsHandler
 */
class FetchAllPaymentMethodsHandler implements QueryHandlerInterface
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var ResponseParserInterface
     */
    private $responseParser;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * FetchAllPaymentMethodsHandler constructor.
     *
     * @param ClientInterface $client
     * @param ResponseParserInterface $responseParser
     * @param LoggerInterface $logger
     */
    public function __construct(
        ClientInterface $client,
        ResponseParserInterface $responseParser,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->responseParser = $responseParser;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(QueryInterface $event)
    {
        return
            $event instanceof FetchAllPaymentMethodsQuery &&
            $event->getAdapterName() === PlentymarketsAdapter::getName();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(QueryInterface $event)
    {
        $paymentMethods = $this->client->request('GET', 'payments/methods');

        $paymentMethods = array_map(function($paymentMethod) {
            return $this->responseParser->parse($paymentMethod);
        }, $paymentMethods);

        return array_filter($paymentMethods);
    }
}

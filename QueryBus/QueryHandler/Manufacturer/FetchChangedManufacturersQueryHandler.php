<?php

namespace PlentymarketsAdapter\QueryBus\QueryHandler\Manufacturer;

use Exception;
use PlentyConnector\Connector\ConfigService\ConfigServiceInterface;
use PlentyConnector\Connector\QueryBus\Query\Manufacturer\FetchChangedManufacturersQuery;
use PlentyConnector\Connector\QueryBus\Query\QueryInterface;
use PlentyConnector\Connector\QueryBus\QueryHandler\QueryHandlerInterface;
use PlentymarketsAdapter\Client\ClientInterface;
use PlentymarketsAdapter\PlentymarketsAdapter;
use PlentymarketsAdapter\QueryBus\ChangedDateTimeTrait;
use PlentymarketsAdapter\ResponseParser\ResponseParserInterface;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/**
 * Class FetchChangedManufacturersQueryHandler.
 */
class FetchChangedManufacturersQueryHandler implements QueryHandlerInterface
{
    use ChangedDateTimeTrait;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var ResponseParserInterface
     */
    private $responseMapper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * FetchChangedManufacturersQueryHandler constructor.
     *
     * @param ClientInterface $client
     * @param ConfigServiceInterface $config
     * @param ResponseParserInterface $responseMapper
     * @param LoggerInterface $logger
     */
    public function __construct(
        ClientInterface $client,
        ConfigServiceInterface $config,
        ResponseParserInterface $responseMapper,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->responseMapper = $responseMapper;
        $this->logger = $logger;
    }

    /**
     * @param QueryInterface $query
     *
     * @return bool
     */
    public function supports(QueryInterface $query)
    {
        return $query instanceof FetchChangedManufacturersQuery &&
            $query->getAdapterName() === PlentymarketsAdapter::NAME;
    }

    /**
     * @param QueryInterface $query
     *
     * @return TransferObjectInterface[]
     *
     * @throws UnexpectedValueException
     */
    public function handle(QueryInterface $query)
    {
        $criteria = [
            'lastUpdateTimestamp' => $this->getChangedDateTime($this->config),
        ];

        $result = [];
        foreach ($this->client->getIterator('items/manufacturers', $criteria) as $element) {
            try {
                $result[] = $this->responseMapper->parse($element);
            } catch (Exception $exception) {
                $this->logger->error($exception->getMessage());
            }
        }

        if ([] !== $result) {
            $this->setChangedDateTime($this->config);
        }

        return $result;
    }
}

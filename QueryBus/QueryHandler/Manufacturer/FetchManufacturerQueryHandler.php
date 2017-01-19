<?php

namespace PlentymarketsAdapter\QueryBus\QueryHandler\Manufacturer;

use Exception;
use PlentyConnector\Adapter\PlentymarketsAdapter\Client\Exception\InvalidResponseException;
use PlentyConnector\Connector\IdentityService\IdentityServiceInterface;
use PlentyConnector\Connector\QueryBus\Query\FetchQueryInterface;
use PlentyConnector\Connector\QueryBus\Query\Manufacturer\FetchManufacturerQuery;
use PlentyConnector\Connector\QueryBus\Query\QueryInterface;
use PlentyConnector\Connector\QueryBus\QueryHandler\QueryHandlerInterface;
use PlentyConnector\Connector\TransferObject\Manufacturer\Manufacturer;
use PlentyConnector\Connector\TransferObject\TransferObjectInterface;
use PlentymarketsAdapter\Client\ClientInterface;
use PlentymarketsAdapter\Client\Exception\InvalidCredentialsException;
use PlentymarketsAdapter\PlentymarketsAdapter;
use PlentymarketsAdapter\ResponseParser\ResponseParserInterface;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/**
 * Class FetchManufacturerQueryHandler
 */
class FetchManufacturerQueryHandler implements QueryHandlerInterface
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var ResponseParserInterface
     */
    private $manufacturerResponseParser;

    /**
     * @var ResponseParserInterface
     */
    private $mediaResponseParser;

    /**
     * @var IdentityServiceInterface
     */
    private $identityService;

    /**
     * FetchManufacturerQueryHandler constructor.
     *
     * @param ClientInterface $client
     * @param ResponseParserInterface $manufacturerResponseParser
     * @param ResponseParserInterface $mediaResponseParser
     * @param IdentityServiceInterface $identityService
     */
    public function __construct(
        ClientInterface $client,
        ResponseParserInterface $manufacturerResponseParser,
        ResponseParserInterface $mediaResponseParser,
        IdentityServiceInterface $identityService
    ) {
        $this->client = $client;
        $this->manufacturerResponseParser = $manufacturerResponseParser;
        $this->mediaResponseParser = $mediaResponseParser;
        $this->identityService = $identityService;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(QueryInterface $query)
    {
        return $query instanceof FetchManufacturerQuery &&
            $query->getAdapterName() === PlentymarketsAdapter::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(QueryInterface $query)
    {
        $result = [];

        /**
         * @var FetchQueryInterface $query
         */
        $identity = $this->identityService->findOneBy([
            'objectIdentifier' => $query->getIdentifier(),
            'objectType' => Manufacturer::TYPE,
            'adapterName' => PlentymarketsAdapter::NAME,
        ]);

        $element = $this->client->request('GET', 'items/manufacturers/' . $identity->getAdapterIdentifier());

        if (!empty($element['logo'])) {
            $result[] = $media = $this->mediaResponseParser->parse([
                'link' => $element['logo'],
                'name' => $element['name']
            ]);

            $element['logoIdentifier'] = $media->getIdentifier();
        }

        $result[] = $this->manufacturerResponseParser->parse($element);

        return $result;
    }
}

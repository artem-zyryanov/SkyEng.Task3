<?php

namespace src\Decorator;

use DateTime;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use src\Integration\DataProvider;

class CachingDataProvider
{
    private $cache;
    private $logger;
    private $dataProvider;

    /**
     * @param DataProvider $dataProvider
     *
     * @param CacheItemPoolInterface $cache
     *          Реализация должна поддерживать ключи без ограничения по символам и размеру.
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(DataProvider $dataProvider, CacheItemPoolInterface $cache, LoggerInterface $logger = null)
    {
        $this->cache = $cache;
        $this->dataProvider = $dataProvider;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function get(array $request)
    {
        try {
            $cacheKey = $this->getCacheKey($request);
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }

            $result = $this->dataProvider->get($request);

            $cacheItem
                ->set($result)
                ->expiresAt((new DateTime())->modify('+1 day'));
            $this->cache->save($cacheItem);
            return $result;
        } catch (Exception $e) {
            if ($this->logger !== null) {
                $this->logger->critical($e->getMessage(), ['exception' => $e]);
            }
        }

        return [];
    }

    private function getCacheKey(array $request)
    {
        //Переделывать не стал, так как для хорошей реализации нужно знать природу запросов.
        //Если мы пишем абстрактную кэширующую штуку, которая ничего не знает про запросы,
        //то генерацию ключа я бы вынес туда, где это знание есть.
        return json_encode($request);
    }
}

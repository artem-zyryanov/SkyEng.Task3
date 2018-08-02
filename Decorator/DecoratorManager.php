<?php

namespace src\Decorator;

use DateTime;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use src\Integration\DataProvider;

/*
 * Если нет острой необходимости в наследовании, я бы предпочёл делегирование,
 * хотя бы банально для удобства тестирования
 */
class DecoratorManager extends DataProvider //Ни о чем не говорящее название
{
    public $cache; // Нарушение инкапсуляции
    public $logger;

    //Бессмысленный PHPDoc

    /**
     * @param string $host
     * @param string $user
     * @param string $password
     * @param CacheItemPoolInterface $cache
     */
    public function __construct($host, $user, $password, CacheItemPoolInterface $cache)
    {
        parent::__construct($host, $user, $password);
        $this->cache = $cache;
    }

    /*
     * Где то есть PHPDoc, где-то нет. Я не фанат бессмысленных комментариев, но Code Style должен быть един
     *
     * Мне кажется, что при использовании IOC такой метод будет мешать и нужно инициализировать через конструктор,
     * но зависит от проекта
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /*
     * Я C#-ер, и меня пугает передача массивов не известной природы по значению в качестве параметров
     * Кажется, что это может быть реализовано через Deep Copy и создавать ощутимый оверхэд,
     * но может это в PHP как-то решается?
     *
     * Кажется этот метод должен был переопределять метод get родителя, но название метода и параметра отличается
     */
    /**
     * {@inheritdoc} - это вообще законно?:) У кого он будет наследовать?
     * Если будет исправлена ошибка с названием метода, то возникнет проблема не совпадения имен параметров
     */
    public function getResponse(array $input)
    {

        try {
            $cacheKey = $this->getCacheKey($input);
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }

            $result = parent::get($input);

            $cacheItem
                ->set($result)
                ->expiresAt(
                    (new DateTime())->modify('+1 day') //Может время кеширования стоило параметризовать?
                );
            // Нет вызова save. А есть ли на этот код тесты?
            return $result;
        } catch (Exception $e) {
            //Судя по всему, логгер может быть не инициализирован, и здесь будет ошибка
            $this->logger->critical('Error'); //Сообщение бессмыслено, информация об ошибки потеряна

        }

        return [];//А правда ли в случае ошибки обращения к внешнему сервису, нам не нужно ее пробрасывать выше?
    }

    public function getCacheKey(array $input) //Ей тоже не нужно быть публичной
    {
        /*
         * PSR-6 говорит нам:
         *  Implementing libraries MUST support keys consisting of the
         *  characters A-Z, a-z, 0-9, _, and . in any order in UTF-8 encoding and a
         *  length of up to 64 characters.
         *
         * Такая реализация может сформировать ключ,
         * который не будет поддержаиваться реализацией CacheItemPoolInterface
         */
        return json_encode($input);
    }
}

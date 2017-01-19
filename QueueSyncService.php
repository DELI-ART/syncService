<?php

namespace HelperBundle\Listeners;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class QueueSyncService.
 */
class QueueSyncService implements ConsumerInterface
{
    const TYPES_NAMESPACE = 'HelperBundle\Services\Queue';

    protected $container;

    /**
     * @param $container
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * Rabbit Message Listener.
     *
     * @param AMQPMessage $msg
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function execute(AMQPMessage $msg)
    {
        $object = json_decode($msg->getBody(), true);
        if (!$object || !$object['entity']) {
            throw new \Exception('Msg is not object::'.$msg->getBody());
        }
        $syncEntity = $this->factoryCreateType($object['entity']);
        call_user_func([$syncEntity, $object['event']], $object['data'], $object['identifier']);
        $this->writeLog('::ReceiveLog::'.$object['entity'].':'.$object['event'].':'.$object['identifier'].':'.json_encode($object['data']));

        return true;
    }

     /**
      * Publish message to queue.
      *
      * @param $syncEntityName
      * @param $event
      * @param $identifier
      * @param array $data
      */
     public function publish($syncEntityName, $event, $identifier, array $data)
     {
         $message = [
            'entity' => $syncEntityName,
            'system' => 'road',
            'identifier' => $identifier,
            'event' => $event,
            'data' => $data,
        ];
         $this->writeLog('::PublishLog::'.$syncEntityName.':'.$event.':'.$identifier.':'.json_encode($data));
        //Publish to Queue
     }

    /**
     * CreateSyncEntityType.
     *
     * @param $name
     *
     * @return mixed
     */
    public function factoryCreateType($name)
    {
        $typeNameSpace = self::TYPES_NAMESPACE.'\Sync'.$name.'Type';

        return  new $typeNameSpace($this->container);
    }

    /**
     * //WriteLog.
     *
     * @param $text
     */
    public function writeLog($text)
    {
        $logger = $this->container->get('monolog.logger.sync_log');
        $logger->info($text);
    }
}

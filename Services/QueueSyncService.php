<?php

namespace SyncBundle\Services;


class QueueSyncService {

    const TYPES_NAMESPACE = 'SyncBundle\SyncTypes';

    /**
     * @param $container
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    public function executeMessage(array $message)
    {
        $syncEntity = $this->factoryCreateType($message['entity']);
        call_user_func([$syncEntity, $message['event']], $message['data'], $message['identifier']);
    }

    /**
     * @param $syncEntityName
     * @param $event
     * @param $identifier
     * @param array $data
     */
    public function publishMessage($syncEntityName, $event, $identifier, array $data)
    {
        $message = [
            'entity' => $syncEntityName,
            'system' => 'road',
            'identifier' => $identifier,
            'event' => $event,
            'data' => $data,
        ];
        $producer = $this->container->get('old_sound_rabbit_mq.sync_publisher_producer');
        //Publish to Queue
        $producer->publish(json_encode($message));
        //Logging
        $this->writeLog('::PublishLog::'.json_encode($message));
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
     * @param $text
     */
    public function writeLog($text)
    {
        $logger = $this->container->get('monolog.logger.sync_log');
        $logger->info($text);
    }

}
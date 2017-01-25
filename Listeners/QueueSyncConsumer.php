<?php

namespace SyncBundle\Listeners;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class QueueSyncService.
 */
class QueueSyncConsumer  implements ConsumerInterface
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
        $syncService = $this->container->get('queue_sync_service');
        $syncService->executeMessage($object);
        $this->writeLog('::ReceiveLog::'.$msg->getBody());
        return true;
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

<?php

namespace HelperBundle\Services\Queue;

use Doctrine\ORM\Id\UuidGenerator;
use HelperBundle\Services\OrderDispatcherProducerService;
use JMS\Serializer\Serializer;

abstract class QueueTypeAbstract
{
    protected $message;

    protected $serializer;

    protected $producer;

    protected $uidGenerator;

    public function __construct(Serializer $serializer, OrderDispatcherProducerService $producer, $em)
    {
        $this->serializer = $serializer;
        $this->producer = $producer;
        $this->em = $em;
        $this->uidGenerator = new UuidGenerator();
        $this->message = array();
    }

    abstract public function create($data, $argument = null);

    public function getMessage()
    {
        return $this->message;
    }

    public function setField($key, $value)
    {
        if (array_key_exists($key, $this->message)) {
            $this->message[$key] = $value;
        } else {
            throw new \Exception('Undefined Message Key');
        }

        return $this;
    }

    protected function getUid()
    {
        return $this->uidGenerator->generate($this->em, new \Doctrine\ORM\Mapping\Entity());
    }

    protected function getDateTimeEvent()
    {
        return new \DateTime();
    }

    public function publish($routingKey)
    {
        if (!is_array($this->message) || empty($this->message)) {
            throw new \Exception('Message is empty or invalid data type');
        }
        $messageString = $this->serializer->serialize($this->message, 'json');
        $this->producer->publish($messageString, $routingKey);

        return true;
    }
}

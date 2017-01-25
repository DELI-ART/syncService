SyncService with RabbitMQ and Symfony 2 
=====================

1. Create configuration for RabbitMq
-----------------------------------
```
producers:
        sync_publisher:
            class: SyncBundle\Listeners\QueueSyncProducer
            connection: sync_service
            exchange_options:
                 name: \
                 type: fanout
            queue_options:
                 name: sync
            #    type: topic
 consumers:
        sync_receiver:
            connection:       sync_service
            queue_options:
                name: road
            callback:         queue_sync_consumer
            idle_timeout:     123360
```
2. Run consumer
-----------------------------------
php app/console rabbitmq:consumer -w sync_receiver



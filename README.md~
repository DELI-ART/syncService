SyncService 

Create service configuration 

    #SyncHandler
    queue_sync_service:
      class: HelperBundle\Listeners\QueueSyncService
      arguments: ["@service_container"]

    #SyncEntityListener
    entity_sync_listener:
      class: HelperBundle\Listeners\EntitySyncListener
      arguments: ["@queue_sync_service"]
      tags:
          - { name: doctrine.event_listener, event: preUpdate}
          - { name: doctrine.event_listener, event: onFlush}


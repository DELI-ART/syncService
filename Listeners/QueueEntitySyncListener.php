<?php

namespace SyncBundle\Listeners;
/**
 * Class EntitySyncListener
 * @package HelperBundle\Listeners
 */
class QueueEntitySyncListener
{
    protected $queueSync;

    /**
     * @param QueueSyncService $queueSyncService
     */
    public function __construct(\SyncBundle\Services\QueueSyncService $queueSyncService)
    {
        $this->queueSyncService = $queueSyncService;
    }

    /**
     * @param \Doctrine\ORM\Event\OnFlushEventArgs $args
     */
    public function onFlush(\Doctrine\ORM\Event\OnFlushEventArgs $args)
    {
        $syncEntity = null;
        $em = $args->getEntityManager();
        foreach ($em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            switch (true) {
                case $entity instanceof \CompaniesBundle\Entity\AppCompanies:
                     $syncEntity = $this->queueSyncService->factoryCreateType('Company');
                     break;
                case $entity instanceof \Application\Sonata\UserBundle\Entity\User:
                     $syncEntity = $this->queueSyncService->factoryCreateType('User');
                     //CheckLinkUserCompany
                     if ($entity->getAppCompanyUser()) {
                         $data = $syncEntity->getOptionsChangeCreate($entity);
                         $this->queueSyncService->publishMessage($syncEntity::ENTITY_NAME, 'created', null, $data['data']);
                     }
                     break;
                case $entity instanceof \CompaniesBundle\Entity\AppCompanyCompanyLink:
                    $syncEntity = $this->queueSyncService->factoryCreateType('CompanyRelationship');
                     break;
             }

        }
        if ($syncEntity) {
            $data = $syncEntity->getOptionsChangeCreate($entity);
            if (!empty($data['data'])) $this->queueSyncService->publishMessage($syncEntity::ENTITY_NAME, 'created', $data['identifier'], $data['data']);
        }

    }

    /**
     * @param \Doctrine\ORM\Event\preUpdateEventArgs $args
     */
    public function preUpdate(\Doctrine\ORM\Event\preUpdateEventArgs $args)
    {
        $entityName = null;
        $entity = $args->getEntity();
        switch (true) {
            case $entity instanceof  \Application\Sonata\UserBundle\Entity\User:
                $entityName = 'User';
                break;
            case $entity instanceof  \CompaniesBundle\Entity\AppCompanies:
                $entityName = 'Company';
                break;
        }
        if ($entityName) {
            $syncEntity = $this->queueSyncService->factoryCreateType($entityName);
            $data = $syncEntity->getOptionsChangeSet($entity, $args);
            if (!empty($data['data'])) $this->queueSyncService->publishMessage($syncEntity::ENTITY_NAME, 'updated', $data['identifier'], $data['data']);

        }
    }
}

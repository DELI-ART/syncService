<?php

namespace SyncBundle\SyncTypes;


/**
 * Interface SyncInterfaceType.
 */
interface SyncInterfaceType
{
    public function created(array $data, $identifier);

    public function updated(array $data, $identifier);

    public function deleted(array $data, $identifier);

    public function isUnique($uniq);
}

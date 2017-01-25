<?php

namespace SyncBundle\SyncTypes;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class SyncAbstractType.
 */
abstract class SyncAbstractType
{
    protected $container;

    protected $resolver;

    protected $resource;

    /**
     * @param $container
     */
    public function __construct($container)
    {
        $this->container = $container;
        $this->resolver = new OptionsResolver();
    }

    /**
     * @param array $options
     * @param array $data
     *
     * @return array
     */
    protected function configureOptions(array $options, array $data)
    {
        $this->resolver->setRequired($options);

        return $this->resolver->resolve($data);
    }

    /**
     * @param null $syncEntityName
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function getOptionsMapping($syncEntityName = null)
    {
        if (!$syncEntityName) {
            return  $this->container->getParameter('sync_options_mapping');
        }

        if (!array_key_exists($syncEntityName, $this->container->getParameter('sync_options_mapping'))) {
            throw new \Exception('Dont load configuration '.$syncEntityName);
        }

        return  $this->container->getParameter('sync_options_mapping')[$syncEntityName];
    }

    /**
     * @param null  $syncEntityName
     * @param array $option
     */
    protected function addOptionsMapping($syncEntityName = null, array $option)
    {
        $this->container->getParameter('sync_options_mapping')[$syncEntityName][key($option)] = $option[key($option)];
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

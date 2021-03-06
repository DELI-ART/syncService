<?php

namespace SyncBundle\SyncTypes;


use CompaniesBundle\Entity\AppCompanies;

/**
 * Class SyncCompanyType.
 */
class SyncCompanyType extends SyncAbstractType implements SyncInterfaceType
{
    const ENTITY_NAME = 'Company';

    /**
     * @param $uniq
     *
     * @return bool
     */
    public function isUnique($uniq)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $company = $em->createQueryBuilder()
            ->select('c')
            ->from('CompaniesBundle:AppCompanies', 'c')
            ->where('c.inn =:inn')
            ->setParameters(array(
                'inn' => $uniq['inn'],
            ))
            ->getQuery()
            ->getResult();
        if ($company) {
            return false;
        } else {
            return true;
        }

    }

    /**
     * @param array $data
     * @param $identifier
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function created(array $data, $identifier)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        //Get options mapping
        $optionsMapping = $this->getOptionsMapping(self::ENTITY_NAME);
        //Check options resolved
        $options = $this->configureOptions(array_keys($optionsMapping), $data);

        if (!$this->isUnique(array('inn' => $options['inn']))) {
            $this->writeLog('Error::Company:create:is_not_unique');

            return true;
        }
        //Создаем компанию
        $company = new AppCompanies($em);
        $company
            ->setName($options['name'])
            ->setInn($options['inn'])
            ->setAddress($options['address'])
            ->setActive(true)
            ->setVerification(true)
            ->setCountryId($options['country'] !== null ? $em->getRepository('HelperBundle:AppCountryList')->findOneBy(array('syncId' => $options['country'])) : null)
            ->setRegionId($options['region'] !== null ? $em->getRepository('HelperBundle:AppRegionList')->findOneBy(array('syncId' => $options['region'])) : null)
            ->setCityId($options['city'] !== null ? $em->getRepository('HelperBundle:AppCityList')->findOneBy(array('syncId' => $options['city'])) : null);

        //Добавляем роли
        if (!empty($options['type'])) {
            $optionsMappingType = array_flip($this->getOptionsMapping(self::ENTITY_NAME.'Type'));
            foreach ($options['type'] as $role) {
                if (array_key_exists($role, $optionsMappingType))
                    $company->setRoles($optionsMappingType[$role]);
            }
        }
        $em->persist($company);
        $em->flush();

        return true;
    }

    /**
     * @param array $data
     * @param $identifier
     *
     * @return bool
     */
    public function updated(array $data, $identifier)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $company = $em->getRepository('CompaniesBundle:AppCompanies')->findOneBy(array('inn' => $identifier));
        if (!$company) {
            $this->writeLog('Error::Company:update:undefined_company:'.$identifier);

            return true;
        }
        foreach ($data as $name => $value) {
            switch ($name) {
                case 'inn':
                    //Check inn
                    $companyIsset = $em->getRepository('CompaniesBundle:AppCompanies')
                        ->findOneBy(array('inn' => $value));
                    if ($companyIsset) {
                        $this->writeLog('Error::Company:update:is_not_uniq:inn:'.$value);

                        return true;
                    } else {
                        $company->setInn($value);
                    }
                    break;
                case 'name':
                    $company->setName($value);
                    break;
                case 'country':
                    $company->setCountryId($em->getRepository('HelperBundle:AppCountryList')->findOneBy(array('syncId' => $value)));
                    break;
                case 'region':
                    $company->setRegionId($em->getRepository('HelperBundle:AppRegionList')->findOneBy(array('syncId' => $value)));
                    break;
                case 'city':
                    $company->setCityId($em->getRepository('HelperBundle:AppCityList')->findOneBy(array('syncId' => $value)));
                    break;
                case 'address':
                    $company->setAddress($value);
                    break;
                case 'type':
                    $optionsMappingType = $this->getOptionsMapping(self::ENTITY_NAME.'Type');
                    foreach ($value as $role) {
                        if (array_key_exists($role, $optionsMappingType))
                            $company->setRoles($optionsMappingType[$role]);
                    }
                    break;
            }
        }
        $em->persist($company);
        $em->flush();
    }

    /**
     * @param AppCompanies $company
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getOptionsChangeCreate(AppCompanies $company)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        //Get options mapping
        $optionsMapping = array_flip($this->getOptionsMapping(self::ENTITY_NAME));
        $data = [];
        foreach ($optionsMapping as $name => $syncName) {
            if (strpos($name, 'not_used') !== false) {
                $data[$syncName] = null;
                continue;
            }
            $value = call_user_func([$company, 'get'.ucfirst($name)]);
            //ReplaceGeoCodeId
            switch ($name) {
                case 'countryId':
                    $country = $em->getRepository('HelperBundle:AppCountryList')->find($value);
                    if (!$country) {
                        //Default Country if empty
                        $country = $em->getRepository('HelperBundle:AppCountryList')->find(1);
                    }
                    $data[$syncName] = $country->getSyncId();
                    break;
                case 'regionId':
                    $region = $em->getRepository('HelperBundle:AppRegionList')->find($value);
                    if (!$region) {
                        //Default Region if empty
                        $region = $em->getRepository('HelperBundle:AppRegionList')->find(4312);
                    }
                    $data[$syncName] = $region->getSyncId();
                    break;
                case 'cityId':
                    $city = $em->getRepository('HelperBundle:AppCityList')->find($value);
                    //Default City if empty
                    if (!$city) {
                        $city = $em->getRepository('HelperBundle:AppCityList')->find(4400);
                    }
                    $data[$syncName] = $city->getSyncId();
                    break;
                case 'roles':
                    $optionsMappingType = array_flip($this->getOptionsMapping(self::ENTITY_NAME.'Type'));
                    $newValue = [];
                    foreach ($value as $role) {
                        if (array_key_exists($role->getId(), $optionsMappingType))
                        $newValue[] = $optionsMappingType[$role->getId()];
                    }
                    $data[$syncName] = $newValue;
                    break;
                case 'createdAt':
                    $data[$syncName] = date('Y-m-d H:i:s');
                    break;
                default:
                    $data[$syncName] = $value;

            }
        }
        return ['identifier' => $company->getInn(), 'data' => $data];
    }

    /**
     * @param AppCompanies                           $company
     * @param \Doctrine\ORM\Event\preUpdateEventArgs $args
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getOptionsChangeSet(AppCompanies $company, \Doctrine\ORM\Event\preUpdateEventArgs $args)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        //Get options mapping
        $optionsMapping = array_flip($this->getOptionsMapping(self::ENTITY_NAME));
        //Data array
        $data = [];
        foreach ($args->getEntityChangeSet() as $name => $values) {
            if (array_key_exists($name, $optionsMapping)) {
                //ReplaceGeoCodeId
                switch ($name) {
                    case 'countryId':
                        $country = $em->getRepository('HelperBundle:AppCountryList')->find($values[1]);
                        if (!$country) {
                            continue 2;
                        }
                        $values[1] = $country->getSyncId();
                        break;
                    case 'regionId':
                        $region = $em->getRepository('HelperBundle:AppRegionList')->find($values[1]);
                        if (!$region) {
                            continue 2;
                        }
                        $values[1] = $region->getSyncId();
                        break;
                    case 'cityId':
                        $city = $em->getRepository('HelperBundle:AppCityList')->find($values[1]);
                        if (!$city) {
                            continue 2;
                        }
                        $values[1] = $city->getSyncId();
                        break;
                }
                $data[$optionsMapping[$name]] = $values[1];
            }
        }

        return ['identifier' => $args->hasChangedField('inn') ? $args->getOldValue('inn') : $company->getInn(), 'data' => $data];
    }

    /**
     * @param array $data
     * @param $identifier
     *
     * @return bool
     */
    public function deleted(array $data, $identifier)
    {
        return true;
    }
}

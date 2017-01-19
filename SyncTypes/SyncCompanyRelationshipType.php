<?php

namespace HelperBundle\Services\Queue;

use Doctrine\Common\Collections\Criteria;

/**
 * Class SyncCompanyRelationshipType.
 */
class SyncCompanyRelationshipType extends SyncAbstractType implements SyncInterfaceType
{
    const ENTITY_NAME = 'CompanyRelationship';

    /**
     * @param $uniq
     *
     * @return array|bool
     */
    public function isUnique($uniq)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $company_one = $em->getRepository('CompaniesBundle:AppCompanies')->findOneBy(array('inn' => $uniq['inn_one']));
        $company_two = $em->getRepository('CompaniesBundle:AppCompanies')->findOneBy(array('inn' => $uniq['inn_two']));
        if (!$company_one || !$company_two) {
            return false;
        } else {
            return [$company_one, $company_two];
        }
    }

    /**
     * @param array $data
     * @param $identifier
     *
     * @return bool
     */
    public function created(array $data, $identifier)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $companies = $this->isUnique(
            array(
                'inn_one' => $data['company1_inn'],
                'inn_two' => $data['company2_inn'],
            ));
        if (!$companies || ($companies[0] === $companies[1])) {
            $this->writeLog('Error::CompanyRelationship:create:company_is_not_defined');

            return true;
        }
        //Проверяем установлена ли связь
        $criteria = Criteria::create()
                    ->andWhere(Criteria::expr()->eq('id', $companies[1]->getId()));
        $relationship = $companies[0]->getAppCompaniesPartners()->matching($criteria);
        if (false == $relationship->isEmpty()) {
            $this->writeLog('Error::CompanyRelationship:create:_companies_has_already_relationships'.$companies[0]->getId());

            return true;
        }
        //Добавляем связь

        $companies['0']->setAppCompaniesPartners($companies[1]);
        $companies['1']->setAppCompaniesPartners($companies[0]);
        $em->persist($companies['0']);
        $em->persist($companies['1']);
        $em->flush();

        return true;
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

    /**
     * @param array $data
     * @param $identifier
     *
     * @return bool
     */
    public function updated(array $data, $identifier)
    {
        return true;
    }
}

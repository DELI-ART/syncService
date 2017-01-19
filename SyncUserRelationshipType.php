<?php

namespace HelperBundle\Services\Queue;

use Application\Sonata\UserBundle\Entity\User;

/**
 * Class SyncUserRelationshipType.
 */
class SyncUserRelationshipType extends SyncAbstractType implements SyncInterfaceType
{
    const ENTITY_NAME = 'UserRelationship';

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
        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('Application\Sonata\UserBundle\Entity\User', 'u')
            ->where('u.email =:email')
            ->setParameters(array(
                'email' => $uniq['email'],
            ))
            ->getQuery()
            ->getResult();
        if (!$company || !$user) {
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
     */
    public function created(array $data, $identifier)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        if (!$this->isUnique(
            array(
                'inn' => $data['company_inn'],
                'email' => $data['user_email'],
            ))) {
            $this->writeLog('Error::UserRelationship:create:user_or_company_is_not_defined');

            return true;
        }
        $user = $em->getRepository('Application\Sonata\UserBundle\Entity\User')->findOneBy(array('email' => $data['user_email']));
        $company = $em->getRepository('CompaniesBundle:AppCompanies')->findOneBy(array('inn' => $data['company_inn']));
        switch ($data['role']) {
            case 'role_company_admin':
                    if ($company->getRoleCompanyAdminUser() || !$user->hasRole($user::ROLE_INDEPENDENT)) {
                        $this->writeLog('Error::UserRelationship:create:company_or_user_has_other_relationship ');

                        return true;
                    }
                    $user->removeRole($user::ROLE_INDEPENDENT);
                    $user->addRole($user::ROLE_COMPANY_ADMIN);
                break;
        }
        $company->addUser($user);
        $em->persist($company);
        $em->persist($user);
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

    /**
     * @param User $user
     *
     * @return array
     */
    public function getOptionsChangeCreate(User $user)
    {
        if ($user->getAppCompanyUser()) {
            $syncRole = null;
            if ($user->hasRole($user::ROLE_COMPANY_DISPATCHER)) {
                $syncRole = 'role_company_dispatcher';
            } elseif ($user->hasRole($user::ROLE_COMPANY_ADMIN)) {
                $user->hasRole($user::ROLE_COMPANY_ADMIN);
                $syncRole = 'role_company_admin';
            } elseif ($user->hasRole($user::ROLE_DRIVER)) {
                $syncRole = 'role_company_driver';
            }

            return ['identifier' => null, 'data' => [
                'user_email' => $user->getEmail(),
                'company_inn' => $user->getAppCompanyUser()->getInn(),
                'role' => $syncRole,
            ]];
        }
    }
}

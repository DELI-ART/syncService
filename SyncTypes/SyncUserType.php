<?php

namespace HelperBundle\Services\Queue;

use Application\Sonata\UserBundle\Entity\User;

/**
 * Class SyncUserType.
 */
class SyncUserType extends SyncAbstractType implements SyncInterfaceType
{
    const ENTITY_NAME = 'User';

    /**
     * @param $uniq
     *
     * @return bool
     */
    public function isUnique($uniq)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('Application\Sonata\UserBundle\Entity\User', 'u')
            ->where('u.email =:email OR u.phone =:phone')
            ->setParameters(array(
                'email' => $uniq['email'],
                'phone' => $uniq['phone'],
            ))
            ->getQuery()
            ->getResult();
        if ($user) {
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
        //Get options mapping
       $optionsMapping = $this->getOptionsMapping(self::ENTITY_NAME);
       //Check options resolved
       $options = $this->configureOptions(array_keys($optionsMapping), $data);
        if (!$this->isUnique(array('phone' => $options['phone'], 'email' => $options['email']))) {
            $this->writeLog('Error::User:create:is_not_unique');

            return true;
        }
       //Populate entity
       $userManager = $this->container->get('fos_user.user_manager');
        $user = $userManager->createUser();
        $user->setFirstname($options['first_name']);
        $user->setLastname($options['last_name']);
        $user->setPhone($options['phone']);
        $user->setAdditionalPhone($options['additional_phone']);
        $user
           ->setUsername($options['email'])
           ->setEmail($options['email'])
           ->setPlainPassword($options['password']);
        $encoder = $this->container->get('security.encoder_factory')->getEncoder($user);
        $user->setPassword(
            $encoder->encodePassword(
                $user->getPlainPassword(),
                $user->getSalt()
            )
       );
        $user->addRole($user::ROLE_INDEPENDENT);
        $user->setEnabled(true);

       //Create entity
       $userManager->updateUser($user);

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
        $userManager = $this->container->get('fos_user.user_manager');
        $user = $userManager->findUserByEmail($identifier);

        if (!$user) {
            $this->writeLog('Error::User:update:undefined_user:'.$identifier);

            return true;
        }
        foreach ($data as $name => $value) {
            switch ($name) {
                case 'name':
                    $user->setFirstname($value);
                break;
                case 'email':
                    //Проверка email
                    $userIsset = $em->getRepository('Application\Sonata\UserBundle\Entity\User')
                        ->findOneBy(array('email' => $value));
                    if ($userIsset) {
                        $this->writeLog('Error::User:update:is_not_uniq:email:'.$value);

                        return true;
                    }
                    $user->setEmail($value);
                    $user->setUsername($value);
                break;
                case 'phone':
                    $userIsset = $em->getRepository('Application\Sonata\UserBundle\Entity\User')
                        ->findOneBy(array('phone' => $value));
                    if ($userIsset) {
                        $this->writeLog('Error::User:update:is_not_uniq:phone:'.$value);

                        return true;
                    }
                    $user->setPhone($value);
                break;
                case 'additional_phone':
                    $user->setAdditionalPhone($value);
                    break;
                case 'password':
                    //Генерация пароля
                    $encoder = $this->container->get('security.encoder_factory')->getEncoder($user);
                    $user->setPassword(
                        $encoder->encodePassword(
                            $value,
                            $user->getSalt()
                        )
                    );
                break;

            }
        }
        $userManager->updateUser($user);

        return true;
    }

    /**
     * @param User $user
     * @param \Doctrine\ORM\Event\preUpdateEventArgs $args
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getOptionsChangeSet(User $user, \Doctrine\ORM\Event\preUpdateEventArgs $args)
    {
        //Get options mapping
        $this->addOptionsMapping(self::ENTITY_NAME, ['lastname' => 'lastname']);
        $optionsMapping = array_flip($this->getOptionsMapping(self::ENTITY_NAME));
        //Data array
        $data = [];
        foreach ($args->getEntityChangeSet() as $name => $values) {
            if (array_key_exists($name, $optionsMapping)) {
                switch ($name) {
                    case 'password':
                        $values[1] = $user->getSyncHashPassword();
                        break;
                    case 'lastname':
                        $name = 'name';
                        $values = $user->getFullname();
                        break;
                    case 'name':
                        $name = 'name';
                        $values = $user->getFullname();
                        break;

                }
                $data[$optionsMapping[$name]] = $values[1];
            }
        }

        return ['identifier' => $args->hasChangedField('email') ? $args->getOldValue('email') : $user->getEmail(), 'data' => $data];
    }

    /**
     * @param User $user
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getOptionsChangeCreate(User $user)
    {
        //Get options mapping
        $optionsMapping = array_flip($this->getOptionsMapping(self::ENTITY_NAME));
        $data = [];
        //Data array
        foreach ($optionsMapping as $name => $syncName) {
            $value = call_user_func([$user, 'get'.ucfirst($name)]);
            switch ($name) {
                case 'password':
                    $data[$syncName] = $user->getSyncHashPassword();
                    break;
                case 'firstname':
                    $data[$syncName] = $user->getFullname();
                    break;
                default:
                    $data[$syncName] = $value;
            }
        }

        return ['identifier' => $user->getEmail(), 'data' => $data];
    }
    public function deleted(array $data, $identifier)
    {
    }
}

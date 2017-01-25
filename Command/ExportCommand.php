<?php
namespace SyncBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ExportCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:sync:export')
            ->setDescription('Sync export commands')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                'Input EntityName (User, Company)'
            )
         ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $action = $input->getArgument('action');
        $syncService = $this->getContainer()->get('queue_sync_service');
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        switch ($action) {
            case 'User':
                $type = $syncService->factoryCreateType('User');
                //GetUsers
                $users = $em->getRepository('ApplicationSonataUserBundle:User')->findAll();
                $count = 0;
                $output->writeln('<comment>'.count($users).' users found</comment>');
                foreach($users as $user) {
                    if ($user->hasRole($user::ROLE_COMPANY_DISPATCHER) || $user->hasRole($user::ROLE_COMPANY_ADMIN)) {
                        $data  = $type->getOptionsChangeCreate($user);
                        $syncService->publishMessage($type::ENTITY_NAME, 'created', $data['identifier'], $data['data']);
                        $count++;
                    }

                }
                $output->writeln('<info>Export '.$count.' user success!</info>');
            break;
            case 'Company':
                $type = $syncService->factoryCreateType('Company');
                //GetUsers
                $companies = $em->getRepository('CompaniesBundle:AppCompanies')->findAll();
                $count = 0;
                $output->writeln('<comment>'.count($companies).' companies found</comment>');
                foreach($companies as $company) {
                        $data  = $type->getOptionsChangeCreate($company);
                        $syncService->publishMessage($type::ENTITY_NAME, 'created', $data['identifier'], $data['data']);
                        $count++;

                    break;
                }
                $output->writeln('<info>Export '.$count.' company success!</info>');
                break;

        }
        return true;


    }






}

?>
<?php

namespace BrauneDigital\ActivityBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class BuildStreamCommand extends ContainerAwareCommand{

    const ARGUMENT_NAME_BUILD_LIMIT = 'limit';

    protected function configure()
    {
        $this
            ->setName('braunedigital:activity:buildstream')
            ->setDescription('Build the backend activity stream')
            ->addArgument(
                BuildStreamCommand::ARGUMENT_NAME_BUILD_LIMIT,
                InputArgument::OPTIONAL,
                'How many activities do you want to generate?'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = $input->getArgument(BuildStreamCommand::ARGUMENT_NAME_BUILD_LIMIT);
        $service = $this->getApplication()->getKernel()->getContainer()->get('bd_activity.refresh_stream');

        if($limit) {
            $service->setBuildLimit(intval($limit));
        }

        $service->setOutput($output);
        $service->refresh();

    }

}
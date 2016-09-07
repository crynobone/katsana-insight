<?php

namespace Katsana\Insight;

use Carbon\Carbon;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TravelCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->ignoreValidationErrors();

        $this->setName('travel')
            ->setDescription('Get vehicle travel histories')
            ->addArgument('vehicle', InputArgument::REQUIRED, 'Vehicle ID')
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Fetch travels by date');
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $katsana = $this->getClient();
        $vehicle = $input->getArgument('vehicle');
        $date = Carbon::parse($input->getOption('date'));

        $response = $katsana->resource('Vehicles.Travel')
                        ->date($vehicle, $date->year, $date->month, $date->day);

        var_dump($response->toArray());
    }

}

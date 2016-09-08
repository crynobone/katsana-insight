<?php

namespace Katsana\Insight;

use Carbon\Carbon;
use InvalidArgumentException;
use Katsana\Sdk\Client as Katsana;
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
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Fetch travels starting from', 'today')
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Number of days to be fetch', 0)
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output path');
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
        $file = $this->getFilesystem();

        $today = Carbon::today();
        $from = Carbon::parse($input->getOption('start'));
        $to = $from->copy()->addDay($input->getOption('days'));

        if ($to->isFuture()) {
            $to = $today;
        }

        $vehicle = $input->getArgument('vehicle');
        $directory = sprintf('%s/%s', getcwd(), $input->getOption('output'));

        if (! $file->isDirectory($directory)) {
            $file->makeDirectory($directory, 0777, true, true);
        }

        do {
            $output->writeln("Exporting vehicle [{$vehicle}] travels on {$from->toDateString()}");
            $this->exportTravelDataFor($katsana, $vehicle, $from, $directory);
            sleep(5);
            $from->addDay(1);
        } while ($from->lte($to));
    }

    /**
     * Export data for the current date.
     *
     * @param  Katsana $katsana   [description]
     * @param  [type]  $vehicle   [description]
     * @param  Carbon  $date      [description]
     * @param  [type]  $directory [description]
     * @return [type]             [description]
     */
    protected function exportTravelDataFor(Katsana $katsana, $vehicle, Carbon $date, $directory)
    {
        $file = $this->getFilesystem();

        $response = $katsana->resource('Vehicles.Travel')
                        ->date($vehicle, $date->year, $date->month, $date->day);

        $collect = $response->toArray();

        foreach ($collect['trips'] as $key => &$trip) {
            unset($trip['violations'], $trip['idles'], $trip['idle_duration']);
        }

        $file->put(sprintf('%s/%s.json', $directory, $date->format('Y-m-d')), json_encode($collect, JSON_PRETTY_PRINT));
    }
}

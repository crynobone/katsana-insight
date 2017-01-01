<?php

namespace Katsana\Insight;

use Carbon\Carbon;
use InvalidArgumentException;
use Laravie\Promise\Promises;
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
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output path', 'export/vehicle-travels')
            ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Sleep duration before stepping to a new date', 1);
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
        $sleep = $input->getOption('sleep');
        $directory = $this->prepareDirectoryFor($vehicle, $input->getOption('output'));

        $promises = Promises::create();

        $promises->then(function ($from) use ($vehicle, $output) {
            $output->writeln("Exporting vehicle [{$vehicle}] travels on {$from->toDateString()}");

            return $from;
        })->then(function ($from) use ($katsana, $vehicle, $directory, $sleep) {
            $this->exportTravelDataFor($katsana, $vehicle, $from, $directory);
            sleep($sleep);

            return $from;
        });

        do {
            $promises->queue($from->copy());
            $from->addDay(1);
        } while ($from->lte($to));

        $promises->map(function ($from) use ($output) {
            $output->writeln("Done for {$from->toDateString()}");

            return $from;
        });
    }

    /**
     * Export data for the current date.
     *
     * @param  \Katsana\Sdk\Client  $katsana
     * @param  int  $vehicle
     * @param  \Carbon\Carbon  $date
     * @param  string  $directory
     *
     * @return void
     */
    protected function exportTravelDataFor(Katsana $katsana, $vehicle, Carbon $date, $directory)
    {
        $file = $this->getFilesystem();

        $response = $katsana->resource('Vehicles.Travel')
                        ->date($vehicle, $date->year, $date->month, $date->day);

        if ($response->getStatusCode() !== 200) {
            throw new InvalidArgumentException("Unable to fetch data from vehicle [{$vehicle}] for the date.");
        }

        $collect = $response->toArray();

        foreach ($collect['trips'] as $key => &$trip) {
            unset($trip['violations'], $trip['idles'], $trip['idle_duration']);
        }

        $file->put(sprintf('%s/%s.json', $directory, $date->format('Y-m-d')), json_encode($collect, JSON_PRETTY_PRINT));
    }

    /**
     * Prepare directory for a vehicle.
     *
     * @param  int  $vehicle
     * @param  string  $path
     *
     * @return string
     */
    protected function prepareDirectoryFor($vehicle, $path)
    {
        $file = $this->getFilesystem();
        $directory = sprintf('%s/%s/%s', getcwd(), $path, $vehicle);

        if (! $file->isDirectory($directory)) {
            $file->makeDirectory($directory, 0777, true, true);
        }

        return $directory;
    }
}

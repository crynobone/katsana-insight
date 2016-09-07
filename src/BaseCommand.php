<?php

namespace Katsana\Insight;

use Katsana\Sdk\Client;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;

abstract class BaseCommand extends Command
{
    public function getClient()
    {
        $config = new Repository();
        $file = $this->getFilesystem();

        if (! $file->isFile(getcwd().'/config.php')) {
            throw new Exception('Please configurate config.php file');
        }

        $config->set($file->getRequire(getcwd().'/config.php'));

        $client = Client::make($config->get('api.key'), $config->get('api.token'));

        if (! is_null($domain = $config->get('api.domain'))) {
            $client->useCustomApiEndpoint($domain);
        }

        return $client;
    }

    protected function getFilesystem()
    {
        if (! isset($this->filesystem)) {
            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }
}

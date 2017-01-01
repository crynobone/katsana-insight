<?php

namespace Katsana\Insight;

use Katsana\Sdk\Client;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;

abstract class BaseCommand extends Command
{
    /**
     * Get the client.
     *
     * @return \Katsana\Sdk\Client
     */
    public function getClient()
    {
        $config = new Repository();
        $file = $this->getFilesystem();

        if (! $file->isFile(getcwd().'/config.php')) {
            throw new Exception('Please configurate config.php file');
        }

        $config->set($file->getRequire(getcwd().'/config.php'));

        $client = Client::make($config->get('api.client.id'), $config->get('api.client.secret'));

        if (! is_null($accessToken = $config->get('api.client.token'))) {
            $client->setAccessToken($accessToken);
        }

        if (! is_null($domain = $config->get('api.domain'))) {
            $client->useCustomApiEndpoint($domain);
        }

        return $client;
    }

    /**
     * Get filesystem.
     *
     * @return \Illuminate\Filesystem\Filesystem
     */
    protected function getFilesystem()
    {
        if (! isset($this->filesystem)) {
            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }
}

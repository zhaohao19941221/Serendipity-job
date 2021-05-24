<?php
declare(strict_types = 1);

namespace Serendipity\Job;

use DI\Container;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;
use Serendipity\Job\Config\Loader\YamlLoader;
use Serendipity\Job\Config\ProviderConfig;
use Serendipity\Job\Console\SerendipityJobCommand;
use Serendipity\Job\Kernel\Provider\KernelProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Console\Application as SymfonyApplication;

final class  Application extends SymfonyApplication
{
    /**
     * @var \Dotenv\Dotenv
     */
    protected Dotenv $dotenv;

    /**
     * @var \DI\Container
     */
    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct('Serendipity Job Console Tool...');
        $this->addCommands([
            new SerendipityJobCommand()
        ]);
        $this->initialize();
    }

    public function initialize() : void
    {
        $this->initEnvironment();
        $this->initSingleton();
    }

    protected function initEnvironment() : void
    {
        // Non-thread-safe load
        $this->dotenv = Dotenv::createUnsafeImmutable(SERENDIPITY_JOB_PATH);
        $this->dotenv->safeLoad();
    }

    protected function initSingleton() : void
    {
        $fileLocator = $this->container->make(FileLocator::class, ['paths' => [SERENDIPITY_JOB_PATH . '/config/']]);
        $this->container->set(FileLocatorInterface::class, $fileLocator);
        $this->container->make(YamlLoader::class);
    }

    public function getContainer() : ContainerInterface|Container
    {
        return $this->container;
    }

}

#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\RegisterEnvVarProcessorsPass;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Dotenv\Dotenv;
use ScenicRoads\Command\ProcessScenicRoadsCommand;

// Load Environment Variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Build the Service Container
$container = new ContainerBuilder();

// Add the Pass that handles the %env()% syntax
$container->addCompilerPass(new RegisterEnvVarProcessorsPass());

// Load service definitions and parameters from the YAML configuration
$loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../config'));
$loader->load('services.yaml');

// Define the project root directory so services can resolve relative paths
$container->setParameter('kernel.project_dir', realpath(__DIR__ . '/..'));

$container->resolveEnvPlaceholders(true);

// Compile the container
$container->compile(true);

$appName = $container->getParameter('app.name');
$appVersion = $container->getParameter('app.version');

// Initialize CLI Application
$application = new Application($appName, $appVersion);

// Register Commands
$command = $container->get(ProcessScenicRoadsCommand::class);
$application->addCommand($command);

// Launch the application
$application->run();

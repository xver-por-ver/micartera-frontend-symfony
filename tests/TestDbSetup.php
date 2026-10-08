<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;

$application = new Application($kernel);
$application->setAutoExit(false);

$application->run(new StringInput('doctrine:database:create --if-not-exists --quiet --env=test'));
$application->run(new StringInput('doctrine:schema:drop --force --quiet --env=test'));
$application->run(new StringInput('doctrine:schema:create --quiet --env=test'));
// $application->run(new StringInput('doctrine:schema:update --force --quiet --env=test'));
$application->run(new StringInput('doctrine:fixtures:load --quiet --env=test'));

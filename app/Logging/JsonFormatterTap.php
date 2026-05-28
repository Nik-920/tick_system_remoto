<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger as MonologLogger;

class JsonFormatterTap
{
    public function __invoke(IlluminateLogger|MonologLogger $logger): void
    {
        /** @var MonologLogger $monolog */
        $monolog = $logger instanceof IlluminateLogger ? $logger->getLogger() : $logger;

        foreach ($monolog->getHandlers() as $handler) {
            if (! $handler instanceof FormattableHandlerInterface) {
                continue;
            }

            $handler->setFormatter(new JsonFormatter);
        }
    }
}

<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger;

class JsonFormatterTap
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if (! $handler instanceof FormattableHandlerInterface) {
                continue;
            }

            $handler->setFormatter(new JsonFormatter);
        }
    }
}

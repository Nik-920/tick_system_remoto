<?php

namespace Tests\Unit\Logging;

use App\Logging\JsonFormatterTap;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Tests\TestCase;

class JsonFormatterTapTest extends TestCase
{
    public function test_tap_sets_json_formatter_on_formattable_handlers(): void
    {
        $logger = new Logger('test');
        $handler = new StreamHandler('php://memory');

        $logger->pushHandler($handler);

        $tap = new JsonFormatterTap;
        $tap($logger);

        $this->assertInstanceOf(JsonFormatter::class, $handler->getFormatter());
    }
}

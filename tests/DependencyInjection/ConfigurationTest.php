<?php

declare(strict_types=1);

namespace Spyrit\EssentialBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Spyrit\EssentialBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testConfigTreeIsProcessable(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), []);

        self::assertSame([], $config);
    }
}

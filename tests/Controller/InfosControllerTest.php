<?php

declare(strict_types=1);

namespace Spyrit\EssentialBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Spyrit\EssentialBundle\Controller\InfosController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class InfosControllerTest extends TestCase
{
    public function testMissingTokenReturns401(): void
    {
        $response = (new InfosController())(new Request(), 'expected-token');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testEmptyExpectedTokenAlwaysReturns401(): void
    {
        $response = (new InfosController())(new Request(query: ['token' => '']), '');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testWrongTokenReturns401(): void
    {
        $response = (new InfosController())(new Request(query: ['token' => 'wrong']), 'expected-token');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testValidTokenReturnsInfos(): void
    {
        $response = (new InfosController())(new Request(query: ['token' => 'expected-token']), 'expected-token');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJson((string) $response->getContent());
        self::assertStringContainsString('"php":', (string) $response->getContent());
    }
}

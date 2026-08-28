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
        $response = (new InfosController('expected-token', __DIR__))(new Request());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testEmptyExpectedTokenAlwaysReturns401(): void
    {
        $response = (new InfosController('', __DIR__))(new Request(query: ['token' => '']));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testWrongTokenReturns401(): void
    {
        $response = (new InfosController('expected-token', __DIR__))(new Request(query: ['token' => 'wrong']));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testValidTokenReturnsInfos(): void
    {
        $response = (new InfosController('expected-token', __DIR__))(new Request(query: ['token' => 'expected-token']));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJson((string) $response->getContent());
        self::assertStringContainsString('"php":', (string) $response->getContent());
        self::assertStringContainsString('"libraries-php":', (string) $response->getContent());
        self::assertStringContainsString('"libraries-node":', (string) $response->getContent());
    }

    public function testNodeLibrariesAreReadFromNodeModules(): void
    {
        $projectDir = sys_get_temp_dir().'/spyrit-essential-'.bin2hex(random_bytes(8));

        mkdir($projectDir.'/node_modules/@scope/scoped-pkg', recursive: true);
        mkdir($projectDir.'/node_modules/plain-pkg', recursive: true);
        file_put_contents($projectDir.'/node_modules/@scope/scoped-pkg/package.json', json_encode(['name' => '@scope/scoped-pkg', 'version' => '1.2.3']));
        file_put_contents($projectDir.'/node_modules/plain-pkg/package.json', json_encode(['name' => 'plain-pkg', 'version' => '4.5.6']));

        $response = (new InfosController('expected-token', $projectDir))(new Request(query: ['token' => 'expected-token']));

        $content = json_decode((string) $response->getContent(), true);

        self::assertSame(
            ['@scope/scoped-pkg' => '1.2.3', 'plain-pkg' => '4.5.6'],
            $content['libraries-node'],
        );
    }
}

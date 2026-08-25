<?php

declare(strict_types=1);

namespace Spyrit\EssentialBundle\Controller;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/spyrit-essential', name: 'spyrit_essential_infos', methods: [Request::METHOD_GET])]
final class InfosController
{
    public function __invoke(
        Request $request,
        #[Autowire(env: 'SPYRIT_ESSENTIAL_TOKEN')] string $expectedToken,
        ?Connection $connection = null,
    ): JsonResponse {
        $providedToken = $request->query->get('token');

        if ('' === $expectedToken || !\is_string($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'php' => PHP_VERSION,
            'symfony' => Kernel::VERSION,
            'node' => $this->getNodeVersion(),
            'database' => $this->getDatabaseVersion($connection),
            'libraries' => $this->getInstalledLibraries(),
        ]);
    }

    private function getNodeVersion(): ?string
    {
        $binary = (new ExecutableFinder())->find('node');

        if (null === $binary) {
            return null;
        }

        $process = new Process([$binary, '--version']);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput()) ?: null;
    }

    private function getDatabaseVersion(?Connection $connection): ?string
    {
        if (null === $connection) {
            return null;
        }

        try {
            // SELECT VERSION() fonctionne aussi bien sur MySQL/MariaDB que sur PostgreSQL
            // (les identifiants non quotés sont repliés en minuscule côté PostgreSQL).
            $version = $connection->fetchOne('SELECT VERSION()');
        } catch (\Throwable) {
            return null;
        }

        return \is_string($version) && '' !== $version ? $version : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function getInstalledLibraries(): array
    {
        $libraries = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            $libraries[$package] = InstalledVersions::getPrettyVersion($package);
        }

        ksort($libraries);

        return $libraries;
    }
}

<?php

declare(strict_types=1);

namespace Spyrit\EssentialBundle\Controller;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/spyrit-essential', name: 'spyrit_essential_infos', methods: [Request::METHOD_GET])]
final class InfosController
{
    public function __construct(
        #[Autowire(env: 'SPYRIT_ESSENTIAL_TOKEN')] private readonly string $expectedToken,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly ?Connection $connection = null,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $providedToken = $request->query->get('token');

        if ('' === $this->expectedToken || !\is_string($providedToken) || !hash_equals($this->expectedToken, $providedToken)) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'php' => PHP_VERSION,
            'symfony' => Kernel::VERSION,
            'node' => $this->getNodeVersion(),
            'database' => $this->getDatabaseVersion($this->connection),
            'libraries-php' => $this->getInstalledPhpLibraries(),
            'libraries-node' => $this->getInstalledNodeLibraries($this->projectDir),
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
     * @return array<string, string>
     */
    private function getInstalledPhpLibraries(): array
    {
        $libraries = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            // Les packages "virtuels" (provide/replace, ex. symfony/cache-implementation)
            // n'ont pas de version propre : getPrettyVersion() renvoie null, on les exclut.
            $version = InstalledVersions::getPrettyVersion($package);

            if (null !== $version) {
                $libraries[$package] = $version;
            }
        }

        ksort($libraries);

        return $libraries;
    }

    /**
     * @return array<string, string>
     */
    private function getInstalledNodeLibraries(string $projectDir): array
    {
        $nodeModulesDir = $projectDir.'/node_modules';

        if (!is_dir($nodeModulesDir)) {
            return [];
        }

        $libraries = [];

        foreach ($this->findPackageJsonFiles($nodeModulesDir) as $packageJsonPath) {
            $data = json_decode((string) file_get_contents($packageJsonPath), true);

            if (!\is_array($data) || !\is_string($data['name'] ?? null) || !\is_string($data['version'] ?? null)) {
                continue;
            }

            $libraries[$data['name']] = $data['version'];
        }

        ksort($libraries);

        return $libraries;
    }

    /**
     * @return iterable<string>
     */
    private function findPackageJsonFiles(string $nodeModulesDir): iterable
    {
        foreach (scandir($nodeModulesDir) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $entryPath = $nodeModulesDir.'/'.$entry;

            // Paquets scopés (@org/pkg) : un niveau de dossier supplémentaire.
            if (str_starts_with($entry, '@') && is_dir($entryPath)) {
                foreach (scandir($entryPath) ?: [] as $scopedEntry) {
                    if ('.' === $scopedEntry || '..' === $scopedEntry) {
                        continue;
                    }

                    $packageJsonPath = $entryPath.'/'.$scopedEntry.'/package.json';

                    if (is_file($packageJsonPath)) {
                        yield $packageJsonPath;
                    }
                }

                continue;
            }

            $packageJsonPath = $entryPath.'/package.json';

            if (is_file($packageJsonPath)) {
                yield $packageJsonPath;
            }
        }
    }
}

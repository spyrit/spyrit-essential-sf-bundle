<?php

declare(strict_types=1);

namespace Spyrit\EssentialBundle\Controller;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
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
    /**
     * $expectedToken, $projectDir et $connection sont liés via `bind:` dans
     * config/services.yaml plutôt qu'avec l'attribut #[Autowire] (absent avant Symfony 6.1,
     * alors que le bundle supporte ^5.4) ou un type-hint concret pour $connection : certaines
     * apps consommatrices (ex. Propel sans doctrine/dbal) n'ont pas la classe Connection, et
     * des listeners tiers (ex. FOSRestBundle) réfléchissent sur TOUS les types de paramètres
     * de TOUS les contrôleurs, même nullables — un type-hint concret y ferait planter le
     * chargement de la classe.
     */
    public function __construct(
        private readonly string $expectedToken,
        private readonly string $projectDir,
        private readonly ?object $connection = null,
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
            'web-server' => $this->getWebServer($request),
            'database' => $this->getDatabaseVersion($this->connection),
            'libraries-php' => $this->getInstalledPhpLibraries(),
            'libraries-node' => $this->getInstalledNodeLibraries($this->projectDir),
        ]);
    }

    private function getNodeVersion(): ?string
    {
        $output = $this->runVersionCommand('node', ['--version']);

        return null !== $output ? (strtok($output, "\n") ?: null) : null;
    }

    /**
     * Détecte le serveur web qui sert la requête (Apache, nginx, FrankenPHP...).
     *
     * Ordre de détection :
     *  1. FrankenPHP : SAPI "frankenphp", version lue via `frankenphp version` ;
     *  2. Apache mod_php : apache_get_version() ;
     *  3. $_SERVER['SERVER_SOFTWARE'], renseigné par nginx (fastcgi_params) ou Apache (mod_proxy_fcgi)
     *     devant PHP-FPM — fonctionne même si le serveur web est dans un autre conteneur.
     * Si la version est masquée (ex. Apache `ServerTokens Prod`), on tente le binaire local du serveur.
     *
     * @return array{name: string, version: string|null}|null
     */
    private function getWebServer(Request $request): ?array
    {
        $serverSoftware = $request->server->get('SERVER_SOFTWARE');
        $serverSoftware = \is_string($serverSoftware) ? trim($serverSoftware) : '';

        if ('frankenphp' === \PHP_SAPI || str_starts_with(strtolower($serverSoftware), 'frankenphp')) {
            return ['name' => 'FrankenPHP', 'version' => $this->findBinaryVersion(['frankenphp'], ['version'], '#FrankenPHP\s+v?(\d\S*)#i')];
        }

        if ('apache2handler' === \PHP_SAPI && \function_exists('apache_get_version')) {
            $serverSoftware = (string) apache_get_version();
        }

        if ('' === $serverSoftware) {
            return null;
        }

        // Serveur de développement intégré de PHP : "PHP 8.3.0 Development Server".
        if (str_contains($serverSoftware, 'Development Server')) {
            return ['name' => 'PHP built-in server', 'version' => \PHP_VERSION];
        }

        // Format usuel "nom/version (commentaire)", ex. "nginx/1.27.3" ou "Apache/2.4.62 (Debian)".
        if (!preg_match('#^([^/\s]+)(?:/(\S+))?#', $serverSoftware, $matches)) {
            return null;
        }

        $name = $matches[1];
        $version = $matches[2] ?? null;

        if (null === $version) {
            $version = match (strtolower($name)) {
                'apache' => $this->findBinaryVersion(['apache2ctl', 'apachectl', 'httpd', 'apache2'], ['-v'], '#Apache/(\d\S*)#'),
                'nginx' => $this->findBinaryVersion(['nginx'], ['-v'], '#nginx/(\d\S*)#'),
                default => null,
            };
        }

        return ['name' => $name, 'version' => $version];
    }

    /**
     * @param list<string> $binaries
     * @param list<string> $arguments
     */
    private function findBinaryVersion(array $binaries, array $arguments, string $pattern): ?string
    {
        foreach ($binaries as $binary) {
            $output = $this->runVersionCommand($binary, $arguments);

            if (null !== $output && preg_match($pattern, $output, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Exécute `<binaire> <arguments>` (sans shell, arguments fixes) et renvoie stdout + stderr
     * (certains binaires comme `nginx -v` écrivent sur stderr), ou null si le binaire est introuvable
     * ou en échec.
     *
     * @param list<string> $arguments
     */
    private function runVersionCommand(string $binaryName, array $arguments): ?string
    {
        $binary = (new ExecutableFinder())->find($binaryName);

        if (null === $binary) {
            return null;
        }

        try {
            $process = new Process([$binary, ...$arguments]);
            $process->setTimeout(5);
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        if (!$process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput()."\n".$process->getErrorOutput()) ?: null;
    }

    private function getDatabaseVersion(?object $connection): ?string
    {
        if (!$connection instanceof Connection) {
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

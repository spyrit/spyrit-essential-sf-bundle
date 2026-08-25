# SpyritEssentialBundle

Bundle Symfony transverse SPYRIT, mutualisant des utilitaires communs aux applications internes.

Compatibilité : PHP >= 8.1, Symfony 5.4 (LTS) à la dernière LTS.

## Dev

```bash
docker compose build
./docker/tools/composer install
./docker/tools/phpunit
./docker/tools/phpstan
./docker/tools/phpcsfixer
```

## Utilisation en local dans une autre application (path repository)

Dans le `composer.json` de l'application consommatrice :

```json
"repositories": [
    { "type": "path", "url": "../spyrit_essential_sf_bundle", "options": { "symlink": true } }
]
```

puis `composer require spyrit/spyrit-essential-bundle:@dev`.

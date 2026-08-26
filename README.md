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

puis `composer require spyrit/spyrit-essential-sf-bundle:@dev`.

## Route d'infos runtime (`/spyrit-essential`)

Expose en JSON :
- `php` : version live du runtime PHP.
- `symfony` : version de Symfony.
- `node` : version live du binaire `node` s'il est accessible depuis le process qui sert la requête, sinon `null`.
- `database` : version live de la base connectée (MySQL/MariaDB/PostgreSQL, via Doctrine DBAL si présent), sinon `null`.
- `libraries-php` : tous les packages Composer installés (via `Composer\InstalledVersions`).
- `libraries-node` : tous les packages Node installés, lus depuis `node_modules/<pkg>/package.json` (pas d'exec npm/yarn/pnpm).

Protégée par un token statique, comparé en temps constant (`hash_equals`) : la route renvoie **401** si le token attendu
est vide/absent, si aucun token n'est fourni, ou si les deux ne correspondent pas exactement.

**Installation dans l'application consommatrice :**

1. Ajouter l'import de route dans `config/routes.yaml` :
   ```yaml
   spyrit_essential:
       resource: '@SpyritEssentialBundle/config/routes.php'
   ```
2. Définir le token dans `.env` (valeur vide, documentaire) puis dans `.env.local` (valeur réelle, jamais committée) :
   ```
   SPYRIT_ESSENTIAL_TOKEN=
   ```
3. Si l'application a un firewall Symfony qui protège tout par défaut, autoriser explicitement la route en accès public
   (l'autorisation réelle est gérée par le token, pas par le firewall) :
   ```yaml
   access_control:
       - { path: ^/spyrit-essential, roles: PUBLIC_ACCESS }
   ```

**Appel :**
```
GET /spyrit-essential?token=xxx
```

⚠️ **Avertissement sécurité** : cette route expose des numéros de version exacts (PHP, Symfony, libs, BDD), utile pour
du diagnostic mais aussi pour un attaquant qui chercherait des CVE connues sur ces versions précises si le token venait
à fuiter. À ne pas exposer publiquement sans restriction complémentaire (IP allowlist, WAF) en production.

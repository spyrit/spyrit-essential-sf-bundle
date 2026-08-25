<?php

declare(strict_types=1);

namespace Spyrit\EssentialBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SpyritEssentialBundle extends Bundle
{
    public function getPath(): string
    {
        // Bundle::getPath() renvoie par défaut le dossier src/ ; on le remonte à la racine
        // du package pour que les imports "@SpyritEssentialBundle/config/..." résolvent vers config/.
        return \dirname(__DIR__);
    }
}

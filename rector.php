<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\SymfonySetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80,
        SymfonySetList::SYMFONY_60,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::SYMFONY_STRICT,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);
};

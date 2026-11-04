<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\FuncCall\FunctionFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\Php70\Rector\FuncCall\RandomFunctionRector;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;

// Rector upgrades the code to PHP 8.3 idioms. Run from tools/quality:
//   docker compose run --rm quality vendor/bin/rector --dry-run
return RectorConfig::configure()
    ->withPaths([
        '/app/cake',
        '/app/app',
    ])
    ->withSkip([
        // CakePHP's and Croogo's own SimpleTest suites, generator templates
        // and third-party code are left as they were shipped.
        '/app/cake/tests',
        '/app/cake/console/templates',
        '/app/app/tests',
        '/app/app/vendors',
        '/app/app/tmp',

        // Rules from the PHP sets that would change behavior in this code:
        // Security::cipher() seeds srand() for a repeatable key stream, and
        // random_int() can't be seeded.
        RandomFunctionRector::class,
        // match compares with ===, switch with ==.
        ChangeSwitchToMatchRector::class,
        // CakePHP inspects and passes around callable arrays and names.
        FunctionFirstClassCallableRector::class,
        ArrayToFirstClassCallableRector::class,
        // Many methods read extra arguments through func_get_args().
        RemoveExtraParametersRector::class,
        // Subclasses override these methods without a never return type.
        ReturnNeverTypeRector::class,
        // Keeps CakePHP's documented property declarations.
        ClassPropertyAssignToConstructorPromotionRector::class,
    ])
    ->withAutoloadPaths(['/app/cake', '/app/app'])
    ->withPhpSets(php83: true);

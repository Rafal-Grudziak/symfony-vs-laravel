<?php

declare(strict_types=1);

namespace App\Benchmark\NoOrm;

final class EntityId
{
    public static function set(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}

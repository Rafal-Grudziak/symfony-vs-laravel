<?php

namespace App\Benchmark\NoOrm;

use Illuminate\Database\Eloquent\Model;

final class ModelPreventLazy
{
    public static function enable(): void
    {
        Model::preventLazyLoading();
    }
}

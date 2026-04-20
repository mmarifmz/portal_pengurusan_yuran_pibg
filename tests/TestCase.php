<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Laravel\Fortify\Features;
use Livewire\Compiler\CacheManager;
use Livewire\Compiler\Compiler;
use Livewire\Factory\Factory;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = storage_path('framework/testing/views');
        if (! is_dir($compiledPath)) {
            File::makeDirectory($compiledPath, 0775, true);
        }
        config()->set('view.compiled', $compiledPath);

        $livewireCachePath = storage_path('framework/testing/livewire');
        if (! is_dir($livewireCachePath)) {
            File::makeDirectory($livewireCachePath, 0775, true);
        }

        app()->forgetInstance('livewire.compiler');
        app()->forgetInstance('livewire.factory');

        app()->singleton('livewire.compiler', function () use ($livewireCachePath) {
            return new Compiler(new CacheManager($livewireCachePath));
        });

        app()->singleton('livewire.factory', function ($app) {
            return new Factory($app['livewire.finder'], $app['livewire.compiler']);
        });
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}

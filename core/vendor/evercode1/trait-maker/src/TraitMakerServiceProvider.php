<?php

namespace Evercode1\TraitMaker;

use Illuminate\Support\ServiceProvider;

/**
 * This is the service provider.
 *
 * Place the line below in the providers array inside app/config/app.php
 *  Evercode1\TraitMaker\src\TraitMakerServiceProvider::class,
 *
 * @package TraitMaker
 * @author Bill Keck
 *
 **/

class TraitMakerServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;
    /**
     * The console commands.
     *
     * @var bool
     */
    protected $commands = [
        'Evercode1\TraitMaker\MakeTrait',
    ];


    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands($this->commands);
    }
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['traitmaker'];
    }
}
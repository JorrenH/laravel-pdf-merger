<?php

namespace JorrenH\LaravelPDFMerger\Providers;

use Illuminate\Support\ServiceProvider;
use JorrenH\LaravelPDFMerger\PDFMerger;

class PDFMergerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/pdfmerger.php' => config_path('pdfmerger.php'),
        ]);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
      $this->app->singleton('PDFMerger', function ($app) {
          return new PDFMerger($app['files']);
      });
    }
}

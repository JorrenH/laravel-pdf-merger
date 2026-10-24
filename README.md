# Laravel PDF Merger

PDF merger for Laravel inspired by another package, created for personal use. Tested with Laravel 13 on PHP 8.5.

## Advantages
* Works with PDF versions above `1.4`
* Supports `Laravel 9+` on `PHP 8.0+`

## Installation
```bash
 $ composer require jorrenh/laravel-pdf-merger
```

## Configuration

### Auto-discovery (Laravel 9+)
The service provider and facade are registered automatically through package auto-discovery on supported Laravel versions; no changes to `config/app.php` are needed.

If you disabled auto-discovery, register manually in `config/app.php`:
```php
'providers' => [
   ...
   JorrenH\LaravelPDFMerger\Providers\PDFMergerServiceProvider::class
],

'aliases' => [
   ...
   'PDFMerger' => JorrenH\LaravelPDFMerger\Facades\PDFMergerFacade::class
]
```

> When merging PDFs versions above 1.4 or PDF strings, a temporary PDF will be created during the process and stored in the configured `temp` directory, which is created if it does not exist.
> Also, note that this package requires Ghostscript installed on the server and configured in the config in order to function properly with PDF versions 1.5+. [Install Guide](https://www.ghostscript.com/doc/9.20/Install.htm)

> Note: Windows users should configure the gswinXXc.exe binary which is the commandline version of the program.



## Usage

To get the PDF Merger instance you may use the registered facade or the `PDFMergerFacade` directly.
```php
$merger = PDFMerger::init();
```

You can add PDFs for merging, by specifying a file path of PDF with `addPDF` method, or adding PDF file as string with `addString` method. The second argument of both methods is array of selected pages (`'all'` for all pages) and the third argument is PDFs orientation (`P`ortrait or `L`andscape). The second and third argument of both methods are optional and default to `'all'` and `'P'` respectively.
```php
$merger->addPDF('/path/to/pdf', 'all', 'P');
$merger->addString(file_get_contents('path/to/pdf'), ['1', '2'], 'L')
```

You can set a merged PDF name by using `setFileName` method.
```php
$merger->setFileName('merger.pdf');
```

Once you're done adding pages to the PDF, merge them with `merge` or `duplexMerge` method and use one of the output options for the merged PDF. The difference between two methods is, that `duplexMerge` adds blank page after each merged PDF, if it has an odd number of pages to enable duplex printing.

Available output options are:
  * `inline()`
  * `download()`
  * `string()`
  * `save('path/to/merged.pdf')`

```php
$merger->merge();
$merger->inline(); /* output option */
```

Example usage
```php
$merger = \PDFMerger::init();
$merger->addPDF(base_path('/vendor/jorrenh/laravel-pdf-merger/examples/one.pdf'), [2], 'P');
$merger->addString(file_get_contents(base_path('/vendor/jorrenh/laravel-pdf-merger/examples/two.pdf')), 'all', 'L');
$merger->merge();
$merger->save(base_path('/public/pdfs/merged.pdf'));
```

## Configuration
The default configuration of this package is listed below. If you want to make changes to any of the default values, you can publish the default config to your laravel installation.
```bash
$ php artisan vendor:publish --provider="JorrenH\LaravelPDFMerger\Providers\PDFMergerServiceProvider"
```

```php
// Default configuration
'temp' => storage_path('app/temp/'),
'compatibility' => [
    'enabled' => true,
    'binary' => env('GS_BINARY', '/usr/local/bin/gs'),
]
```

## Authors
* [GrofGraf](https://github.com/GrofGraf) (adaptation to Webklex' LaravelPDFMerger)
* [JorrenH](https://github.com/JorrenH) (added configuration)


## Credits
* **Webklex** [LaravelPDFMerger](https://github.com/Webklex/laravel-pdfmerger)

## License
The MIT License (MIT)

Copyright (c) 2017 GrofGraf

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

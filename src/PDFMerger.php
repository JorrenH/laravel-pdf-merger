<?php

namespace JorrenH\LaravelPDFMerger;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class PDFMerger
{
    /**
     * Access the filesystem on an oop base
     *
     * @var Filesystem
     */
    protected $filesystem = Filesystem::class;
    /**
     * Hold all the files which will be merged
     *
     * @var Collection
     */
    protected $files = Collection::class;
    /**
     * Holds every tmp file so they can be removed during the deconstruction
     *
     * @var Collection
     */
    protected $tmpFiles = Collection::class;
    /**
     * The actual PDF Service
     *
     * @var FPDI
     */
    protected $fpdi = Fpdi::class;
    /**
     * The final file name
     *
     * @var string
     */
    protected $fileName = 'undefined.pdf';

    /**
     * Construct and initialize a new instance
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->tmpFiles = collect();
    }

    /**
     * The class destructor method
     */
    public function __destruct()
    {
        $filesystem = $this->filesystem;
        $this->tmpFiles->each(function ($filePath) use ($filesystem) {
            $filesystem->delete($filePath);
        });
    }

    /**
     * Initialize a new internal instance of FPDI in order to prevent any problems with shared resources
     * Please visit https://www.setasign.com/products/fpdi/manual/#p-159 for more information on this issue
     *
     * @return self
     */
    public function init()
    {
        $this->fpdi = new Fpdi();
        $this->files = collect();

        return $this;
    }

    /**
     * Stream the merged PDF content
     *
     * @return string
     */
    public function inline()
    {
        return $this->fpdi->Output($this->fileName, 'I');
    }

    /**
     * Download the merged PDF content
     *
     * @return string
     */
    public function download()
    {
        return $this->fpdi->Output($this->fileName, 'D');
    }

    /**
     * Save the merged PDF content to the filesystem
     *
     * @return string
     */
    public function save($filePath = null)
    {
        return $this->filesystem->put($filePath ? $filePath : $this->fileName, $this->string());
    }

    /**
     * Get the merged PDF content as binary string
     *
     * @return string
     */
    public function string()
    {
        return $this->fpdi->Output($this->fileName, 'S');
    }

    /**
     * Set the generated PDF fileName
     * @param string $fileName
     *
     * @return string
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
        return $this;
    }

    /**
     * Add a binary string for inclusion in the merged PDF file. Pages should be formatted: 1,3,6, 12-16.
     * @param string $string
     * @param mixed $pages
     * @param mixed $orientation
     *
     * @return self
     */
    public function addString($string, $pages = 'all', $orientation = null)
    {
        $filePath = $this->getTemporaryDirectory(Str::random(16) . '.pdf');
        $this->filesystem->put($filePath, $string);
        $this->tmpFiles->push($filePath);
        return $this->addPDF($filePath, $pages, $orientation);
    }

    /**
     * Add a PDF for inclusion in the merge with a valid file path. Pages should be formatted: 1,3,6, 12-16.
     * @param string $filePath
     * @param string $pages
     * @param string $orientation
     *
     * @return self
     *
     * @throws \Exception if the given pages aren't correct
     */
    public function addPDF($filePath, $pages = 'all', $orientation = null)
    {
        if (file_exists($filePath)) {
            $filePath = $this->convertPDFVersion($filePath);
            if (!is_array($pages) && strtolower($pages) != 'all') {
                throw new \Exception($filePath . "'s pages could not be validated");
            }
            $this->files->push([
                'name' => $filePath,
                'pages' => $pages,
                'orientation' => $orientation
            ]);
        } else {
            throw new \Exception("Could not locate PDF on '$filePath'");
        }
        return $this;
    }

    /**
     * Merges the provided PDFs using duplex format onto the allocated FPDI instance.
     * @param string $orientation
     *
     * @return $this
     *
     * @throws \Exception if there are no PDFs to merge
     */
    public function duplexMerge($orientation = 'P')
    {
        return $this->merge($orientation, true);
    }

    /**
     * Merges the provided PDFs onto the allocated FPDI instance.
     * @param string $orientation
     * @param false $duplex
     *
     * @return $this
     *
     * @throws \Exception if there are no PDFs to merge
     */
    public function merge($orientation = 'P', $duplex = false)
    {
        if ($this->files->count() == 0) {
            throw new \Exception("No PDFs to merge.");
        }
        $fpdi = $this->fpdi;
        $files = $this->files;
        foreach ($files as $index => $file) {
            $file['orientation'] = is_null($file['orientation']) ? $orientation : $file['orientation'];
            $count = $fpdi->setSourceFile($file['name']);
            if ($file['pages'] == 'all') {
                $pages = $count;
                for ($i = 1; $i <= $count; $i++) {
                    $template = $fpdi->importPage($i);
                    $size = $fpdi->getTemplateSize($template);
                    $fpdi->AddPage($file['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($template);
                }
            } else {
                $pages = count($file['pages']);
                foreach ($file['pages'] as $page) {
                    if (!$template = $fpdi->importPage($page)) {
                        throw new \Exception("Could not load page '$page' in PDF '" . $file['name'] . "'. Check that the page exists.");
                    }
                    $size = $fpdi->getTemplateSize($template);
                    $fpdi->AddPage($file['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($template);
                }
            }
            if ($duplex && $pages % 2 && $index < (count($files) - 1)) {
                $fpdi->AddPage($file['orientation'], [$size['width'], $size['height']]);
            }
        }
        return $this;
    }

    /**
     * Converts PDF if version is above 1.4
     * @param string $filePath
     *
     * @return string
     */
    protected function convertPDFVersion($filePath)
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Could not open PDF '$filePath'");
        }
        $head = fread($handle, 1024) ?: '';
        fseek($handle, -2048, SEEK_END);
        $tail = fread($handle, 2048) ?: '';
        fclose($handle);

        preg_match('/%PDF-(\d\.\d)/', $head, $matches);
        $pdfversion = $matches[1] ?? '0';
        $hasXrefStream = (bool) preg_match('#/Type\s*/XRef\b#', $tail);

        if ((version_compare($pdfversion, '1.4', '>') || $hasXrefStream) && config('pdfmerger.compatibility.enabled', true)) {
            $newFilePath = $this->getTemporaryDirectory(Str::random(16) . '.pdf');
            $gs = config('pdfmerger.compatibility.binary', env('GS_BINARY', '/usr/bin/gs'));
            //rewrite the PDF to version 1.4 with a regular xref table, as required by the FPDI free parser
            $command = "$gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dCompatibilityLevel=1.4"
                . " -sOutputFile=" . escapeshellarg($newFilePath) . ' ' . escapeshellarg($filePath);
            shell_exec($command);
            //Ghostscript >=10.03 emits XRef streams by default when the compatibility level allows it, so verify the output
            if (!is_file($newFilePath) || strpos((string) file_get_contents($newFilePath), '%PDF-') !== 0) {
                throw new \RuntimeException("Ghostscript conversion failed for '$filePath'. Check the gs binary path (currently: $gs).");
            }
            $this->tmpFiles->push($newFilePath);
            $filePath = $newFilePath;
        }
        //return correct file path
        return $filePath;
    }

    /**
     * Get the location of the configured temporary directory. Creates the directory if it does not yet exist.
     *
     * @return string
     */
    protected function getTemporaryDirectory($file = null)
    {
        $directory = config('pdfmerger.temp', storage_path('app/temp'));
        if (!$this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory);
        }
        return rtrim($directory, '\/') . '/' . ltrim($file, '\/');
    }
}

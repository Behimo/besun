<?php

namespace App\Application\Integrations;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class WooCommercePluginPackageService
{
    public const PLUGIN_SLUG = 'rahbar-crm-connector';

    public const VERSION = '1.0.1';

    public function pluginPath(): string
    {
        return base_path('wordpress-plugin/'.self::PLUGIN_SLUG);
    }

    public function downloadResponse(): StreamedResponse
    {
        $sourcePath = $this->pluginPath();

        if (! is_dir($sourcePath)) {
            abort(404, 'فایل پلاگین یافت نشد.');
        }

        $filename = self::PLUGIN_SLUG.'-'.self::VERSION.'.zip';

        return response()->streamDownload(function () use ($sourcePath) {
            $tempFile = tempnam(sys_get_temp_dir(), 'rahbar_plugin_');

            if ($tempFile === false) {
                abort(500, 'ایجاد فایل zip ممکن نشد.');
            }

            $zipPath = $tempFile.'.zip';
            @unlink($tempFile);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                abort(500, 'ایجاد فایل zip ممکن نشد.');
            }

            $this->addDirectoryToZip($zip, $sourcePath, self::PLUGIN_SLUG);
            $zip->close();

            readfile($zipPath);
            @unlink($zipPath);
        }, $filename, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function addDirectoryToZip(ZipArchive $zip, string $directory, string $zipRoot): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relativePath = $zipRoot.'/'.substr($file->getPathname(), strlen($directory) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);

                continue;
            }

            $zip->addFile($file->getPathname(), $relativePath);
        }
    }
}

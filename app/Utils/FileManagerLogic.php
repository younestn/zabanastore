<?php

namespace App\Utils;

use Illuminate\Support\Facades\Storage;

class FileManagerLogic
{
    private static function get_file_name($path)
    {
        $temp = explode('/',$path);
        return end($temp);
    }

    private static function get_file_ext($name)
    {
        $temp = explode('.',$name);
        return end($temp);
    }

    private static function get_path_for_db($full_path)
    {
        $temp = explode('/',$full_path, 3);
        return end($temp);
    }

    public static function format_file_and_folders($files, $type)
    {
        $data = [];
        foreach($files as $file)
        {
            $name = self::get_file_name($file);
            $ext = self::get_file_ext($name);
            $path = '';
            if($type == 'file')
            {
                $path = $file;
            }
            if($type == 'folder')
            {
                $path = $file;
            }
            if(in_array($ext, ['jpg', 'png', 'jpeg', 'gif', 'bmp', 'tif', 'tiff', 'webp']) || $type=='folder')
            $data[] = [
                'name'=> $name,
                'path'=>  $path,
                'db_path'=>  self::get_path_for_db($file),
                'type'=>$type
            ];
        }
        return $data;
    }

    public static function getFileSize($path): string
{
    if (empty($path) || !is_string($path)) {
        return 'Invalid File';
    }

    try {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $relativePath = ltrim((string) $parsedPath, '/');

        $localCandidates = [];

        if (is_file($path)) {
            $localCandidates[] = $path;
        }

        if (str_starts_with($relativePath, 'storage/')) {
            $storageRelative = ltrim(substr($relativePath, strlen('storage/')), '/');

            $localCandidates[] = public_path($relativePath);
            $localCandidates[] = storage_path('app/public/' . $storageRelative);
        }

        foreach ($localCandidates as $localFile) {
            if ($localFile && is_file($localFile)) {
                $bytes = filesize($localFile);

                if ($bytes !== false) {
                    return self::formatBytes($bytes);
                }
            }
        }
    } catch (\Throwable $exception) {
    }

    try {
        $headers = get_headers($path, 1);
        $decimals = 2;
        $bytes = $headers['Content-Length'] ?? $headers['content-length'] ?? null;

        if (!empty($bytes)) {
            $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            $factor = floor((strlen((string) $bytes) - 1) / 3);
            return sprintf("%.{$decimals}f", $bytes / (1024 ** $factor)) . @$size[$factor];
        }
    } catch (\Throwable $exception) {
    }

    try {
        $ch = curl_init($path);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);

        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);

        if ($contentLength > 0) {
            return self::formatBytes((int) $contentLength);
        }

        return 'Unknown Size';
    } catch (\Throwable $exception) {
        return 'Unknown Size';
    }
}
    public static function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
}

<?php

namespace App\Helpers;

use App\Services\ScriptParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class S3BucketHelper
{
    /**
     * Creates or updates files by script in S3 storage.
     *
     * @param object $script
     * @param object $disk
     * @param string|null $custom_script
     * @param string|null $custom_package_json
     * @return void
     */
    public static function updateOrCreateFilesS3(object $script, object $disk, $custom_script, $custom_package_json)
    {
        try {
            if($script->s3_path !== null) {
                if ($disk->exists($script->s3_path . '.zip')) {
                    $isDelete = $disk->delete($script->s3_path . '.zip');
                    if($isDelete) Log::info('Update or Create files s3: Zip file delete from s3!');
                }

                Storage::put($script->s3_path . '/src/' . $script->path, $custom_script);
                Storage::put($script->s3_path . '/src/package.json', $custom_package_json);
                Storage::put($script->s3_path . '/_metadata.json', $script);
                Storage::put($script->s3_path . '.zip', '');

                if (Storage::exists($script->s3_path . '.zip')) {
                    Log::info('Update or Create files s3: Folder scripts in local created ' . $script->s3_path . '.zip');
                    // Create zip file with folder in local storage
                    $file_content = ZipHelper::createZip($script->s3_path);
                    // Create new zip file for storage s3
                    $disk->put($script->s3_path . '.zip', $file_content);
                }
                Storage::deleteDirectory('scripts');
            }
        } catch (Throwable $throwable) {
            Log::error("Throwable: {$throwable->getMessage()}");
        }
    }

    /**
     * Delete script files in S3 storage.
     *
     * @param string $folder_name
     * @return void
     */
    public static function deleteFilesS3(string $folder_name)
    {
        try {
            if($folder_name !== null) {
                $disk = Storage::disk('s3');
                $disk->delete($folder_name . '.zip');
            }
        } catch (Throwable $throwable) {
            Log::error("Throwable: {$throwable->getMessage()}");
        }
    }

    /**
     * Get script and package.json in storage S3.
     *
     * @param string $folder_name
     * @return array
     */
    public static function getFilesS3(string $folder_name)
    {
        try {
            $unZip = ZipHelper::unZip($folder_name);
            if($unZip) {
                $array = [];
                $files = Storage::files($folder_name . '/src');
                foreach ($files as $file) {
                    if (Str::contains($file,'/package.json')) {
                        $array = Arr::add($array, 'custom_package_json', Storage::get($file));
                    } elseif (Str::contains($file,'.custom.js')) {
                        $array = Arr::add($array, 'custom_script', Storage::get($file));
                    }
                }
                Storage::deleteDirectory('scripts');
                Log::info(print_r($array, true));
                return $array;
            }
        } catch (Throwable $throwable) {
            Log::error("Throwable: {$throwable->getMessage()}");
        }
    }

    /**
     * @param string $script
     * @return false|string|null
     */
    public static function extractParamsFromScript(string $script)
    {
        try {
            $result = ScriptParser::getScriptInfo($script);
            $i = 0;
            foreach($result['params'] as $key => $val) {
                $val->order = $i;
                $result['params']->$key = $val;
                $i++;
            }
            return $result && $result['params'] ? json_encode($result['params']) : null;
        } catch (Throwable $throwable) {
            Log::error("Throwable: {$throwable->getMessage()}");
        }
    }
}

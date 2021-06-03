<?php

namespace App\Console\Commands;

use App\Script;
use App\Helpers\ZipHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SyncS3Scripts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scripts:sync-s3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * @var string
     */
    protected $now;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->now = Carbon::now()->toDateTimeString();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $s3Files = Storage::disk('s3')->files('scripts/');
            foreach ($s3Files as $s3File) {
                $s3Path = str_ireplace('.zip', '', $s3File);
                $script = Script::where('s3_path', '=', $s3Path)->first();
                if(!$script) {
                    $unZip = ZipHelper::unZip($s3Path);
                    if($unZip) {
                        $localFiles = Storage::files($s3Path);
                        foreach ($localFiles as $localFile) {
                            if (Str::contains($localFile,'/_metadata.json')) {
                                $data = json_decode(Storage::get($localFile));
                                Log::info(print_r($data, true));
                                Script::updateOrCreate([
                                    'name'               => $data->name,
                                    'description'        => $data->description,
                                    'parameters'         => $data->parameters,
                                    'path'               => $data->path,
                                    's3_path'            => $data->s3_path,
                                    'type'               => $data->type,
                                ]);
                            }
                        }
                        Log::info(print_r($localFiles, true));
                        Storage::deleteDirectory('scripts');
                    }
                }
            }
        } catch (Throwable $throwable) {
            Log::error($throwable->getMessage());
        }
    }
}

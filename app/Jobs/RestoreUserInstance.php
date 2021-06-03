<?php

namespace App\Jobs;

use App\ScriptInstance;
use App\Events\InstanceLaunched;
use App\Helpers\InstanceHelper;
use App\Services\Aws;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class RestoreUserInstance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var ScriptInstance
     */
    protected $instance;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var string|null
     */
    protected $ip;

    /**
     * Create a new job instance.
     *
     * @param ScriptInstance $instance
     * @param User $user
     * @param string|null $ip
     */
    public function __construct(ScriptInstance $instance, User $user, ?string $ip)
    {
        $this->instance = $instance;
        $this->user     = $user;
        $this->ip       = $ip;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle()
    {
        if ($this->instance->aws_status !== ScriptInstance::STATUS_TERMINATED) {
            return;
        }

        $aboutInstance = $this->instance->about;

        if (empty($aboutInstance)) {
            return;
        }

        $aws = new Aws;
        $aws->ec2Connection($aboutInstance->aws_region);

        Log::debug("Connect to region: {$aboutInstance->aws_region}");

        $awsData = InstanceHelper::createAwsKeyAndGroup($aws, $this->ip);

        if (empty($awsData)) {
            return;
        }

        // Instance Restore
        $restoreInstanceResponse = $aws->restoreInstance(
            $aboutInstance,
            $awsData['keyPairName'],
            $awsData['groupName']
        );

        if (empty($restoreInstanceResponse)) {
            return;
        }

        if ($restoreInstanceResponse->hasKey('Instances')) {

            $instanceId = $restoreInstanceResponse->get('Instances')[0]['InstanceId'] ?? null;

            Log::info('Launched instance ' . $instanceId);

            $aws->waitUntil([$instanceId]);

            Log::info('wait until instance ' . $instanceId);

            $describeInstancesResponse = $aws->describeInstances([$instanceId], $aboutInstance->aws_region);

            Log::info('describe instances ' . $instanceId);

            if ($describeInstancesResponse->hasKey('Reservations')) {

                $instanceArray  = $describeInstancesResponse->get('Reservations')[0]['Instances'][0];
                $launchTime     = $instanceArray['LaunchTime'] ?? '';
                $awsStatus      = $instanceArray['State']['Name'];

                $this->instance->update([
                    'aws_instance_id'   => $instanceArray['InstanceId'] ?? '',
                    'aws_public_ip'     => $instanceArray['PublicIpAddress'] ?? '',
                    'start_time'        => $launchTime->format('Y-m-d H:i:s'),
                ]);

                $this->instance->details()->create([
                    'aws_security_group_id'     => $awsData['groupId'],
                    'aws_security_group_name'   => $awsData['groupName'],
                    'aws_public_dns'            => $instanceArray['PublicDnsName'] ?? '',
                    'aws_pem_file_path'         => $awsData['keyPairPath'],
                    'start_time'                => $launchTime->format('Y-m-d H:i:s'),
                    'aws_instance_type'         => $aboutInstance->aws_instance_type,
                    'aws_storage_gb'            => $aboutInstance->aws_storage_gb,
                    'aws_image_id'              => $aboutInstance->aws_image_id
                ]);

                if ($awsStatus === ScriptInstance::STATUS_RUNNING) {
                    $this->instance->setAwsStatusRunning();
                }

                Log::info('Completed Restore Instance ' . $instanceId);
            }
        }

        broadcast(new InstanceLaunched($this->instance, $this->user));
    }
}

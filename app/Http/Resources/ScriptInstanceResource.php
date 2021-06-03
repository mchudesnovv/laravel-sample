<?php

namespace App\Http\Resources;

use App\ScriptInstance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ScriptInstanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $region     = $this->region ?? null;
        $details    = $this->oneDetail ?? null;
        $uptime     = ($this->total_up_time ?? 0) + ($this->cron_up_time ?? 0);

        $data = [
            'id'                    => $this->id ?? '',
            'region'                => $region->name ?? '',
            'instance_id'           => $this->aws_instance_id ?? '',
            'launched_by'           => $this->tag_user_email ?? '',
            'name'                  => $this->tag_name ?? '',
            'script_name'              => $this->script->name ?? '',
            'parameters'            => $this->script->parameters ?? '',
            'launched_at'           => $details->start_time ?? '',
            'uptime'                => $uptime,
            'total_up_time'         => $this->total_up_time ?? 0,
            'cron_up_time'          => $this->cron_up_time ?? 0,
            'status'                => $this->aws_status ?? ScriptInstance::STATUS_TERMINATED,
            'ip'                    => $this->aws_public_ip ?? '',
            'is_in_queue'           => $this->is_in_queue ?? 0,
            'storage_channel'       => "instances.{$this->aws_instance_id}.storage",
            'notification_channel'  => "instances.{$this->aws_instance_id}.notification",
        ];

        if (Auth::check()) {
            $data = array_merge($data, [
                'pem'           => $details->aws_pem_file_path ?? ''
            ]);
        }

        return $data;
    }
}

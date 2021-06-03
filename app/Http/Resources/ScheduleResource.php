<?php

namespace App\Http\Resources;

use App\Helpers\InstanceHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $instance = collect($this->instance->toArray())
            ->only([
                'id', 'tag_name', 'aws_instance_id'
            ])
            ->toArray();

        $data = [
            'id'            => $this->id ?? '',
            'script_name'      => $instance['tag_name'] ?? '',
            'instance_id'   => $instance['aws_instance_id'] ?? '',
            'status'        => $this->status ?? null,
            'details'       => InstanceHelper::getSchedulingDetails($this->details ?? null),
        ];

        if (Auth::check()) {
            $data = array_merge($data, [
                'user' => $this->user->email ?? ''
            ]);
        }

        return $data;
    }
}

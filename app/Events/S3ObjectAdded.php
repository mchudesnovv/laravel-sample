<?php

namespace App\Events;

use App\ScriptInstance;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class S3ObjectAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $instance;

    /**
     * Create a new event instance.
     *
     * @param ScriptInstance $instance
     */
    public function __construct(ScriptInstance $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new Channel("instance.{$this->instance->id}.show");
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $s3Objects = $this->instance
            ->s3Objects()
            ->with('children')
            ->whereNull('parent_id')
            ->get();

        return [
            'objects' => $s3Objects,
        ];
    }
}

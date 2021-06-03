<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AboutInstance extends BaseModel
{
    protected $table = 'about_instances';

    protected $fillable = [
        'instance_id',
        'tag_name',
        'tag_user_email',
        'script_path',
        'script_name',
        'aws_region',
        'aws_instance_type',
        'aws_storage_gb',
        'aws_image_id',
        'params',
        's3_path',
    ];

    /**
     * @return BelongsTo
     */
    public function instance()
    {
        return $this->belongsTo(ScriptInstance::class, 'instance_id', 'id');
    }
}

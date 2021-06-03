<?php

namespace App;

use App\Helpers\QueryHelper;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property mixed name
 * @property mixed aws_custom_script
 * @property mixed path
 * @property mixed aws_custom_package_json
 * @property mixed s3_path
 */
class Script extends BaseModel
{
    const STATUS_ACTIVE     = 'active';
    const STATUS_INACTIVE   = 'inactive';

    const TYPE_PUBLIC       = 'public';
    const TYPE_PRIVATE      = 'private';

    const ORDER_FIELDS      = [
        'name' => [
            'entity'    => QueryHelper::ENTITY_SCRIPT,
            'field'     => 'name'
        ],
        'description' => [
            'entity'    => QueryHelper::ENTITY_SCRIPT,
            'field'     => 'description'
        ],
        'status' => [
            'entity'    => QueryHelper::ENTITY_SCRIPT,
            'field'     => 'status'
        ],
        'type' => [
            'entity'    => QueryHelper::ENTITY_SCRIPT,
            'field'     => 'type'
        ],
    ];

    protected $table = "scripts";

    protected $fillable = [
        'name',
        'description',
        'parameters',
        'path',
        's3_path',
        'status',
        'type'
    ];

    /**
     * @return BelongsToMany
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'script_tag');
    }

    /**
     * @return BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'script_user');
    }
}

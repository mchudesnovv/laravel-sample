<?php

namespace App\Http\Controllers\Common\Instances;

use App\ScriptInstance;
use App\Http\Resources\S3ObjectCollection;
use App\Jobs\StoreS3Objects;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FileSystemController extends InstanceController
{
    /**
     * @param Request $request
     * @param string $instance_id
     * @return JsonResponse
     */
    public function storeS3Object(Request $request, string $instance_id)
    {
        $user = Auth::user();
        $key = $request->input('key');
        dispatch(new StoreS3Objects( $user, $instance_id, $key ));
        return response()->json([], 201);
    }

    /**
     * @param Request $request
     * @param $instance_id
     * @return JsonResponse
     */
    public function getS3Objects(Request $request, string $instance_id)
    {
        $request->validate([
            'limit' => 'numeric:nullable'
        ]);

        /** @var ScriptInstance $instance */
        $instance = $this->getInstanceWithCheckUser($instance_id);

        $limit = $request->query('limit') ?? self::PAGINATE;
        $parent = $request->query('parent') ?? null;
        $parentFolder = $instance->s3Objects()->where('path', '=', "{$parent}")->first();

        if(!$parentFolder) {
            return $this->success([
                'data'  => $objects->data ?? [],
                'total' => $meta->total ?? 0
            ]);
        }
        $resource = $parentFolder->children();

        $resource = $this->applyBlackList($resource);

        $resource = $resource->latest();

        $objects = (new S3ObjectCollection($resource->paginate($limit)))->response()->getData();

        $meta = $objects->meta ?? null;

        $response = [
            'data'  => $objects->data ?? [],
            'total' => $meta->total ?? 0
        ];

        return $this->success($response);
    }

    /**
     * @return JsonResponse
     */
    public function getS3Object ()
    {
        return $this->success();
    }

    /**
     * @param $resource
     * @return resource
     */
    private function applyBlackList($resource)
    {
        if(Auth::check()) {
            return $resource;
        }
        $resource->where('path', 'not like');
        return $resource;
    }
}

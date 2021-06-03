<?php

namespace App\Http\Controllers\Common\Instances;

use App\AwsRegion;
use App\ScriptInstance;
use App\Events\InstanceStatusUpdated;
use App\Helpers\InstanceHelper;
use App\Helpers\QueryHelper;
use App\Http\Controllers\AppController;
use App\Http\Resources\ScriptInstanceCollection;
use App\Http\Resources\ScriptInstanceResource;
use App\Jobs\UpdateInstanceSecurityGroup;
use App\S3Object;
use App\Services\Aws;
use App\Services\GitHub;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstanceController extends AppController
{
    const PAGINATE = 1;

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return ScriptInstanceCollection|JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->query('limit') ?? self::PAGINATE;
            $search = $request->input('search');
            $sort = $request->input('sort');
            $order = $request->input('order') ?? 'asc';
            $list = $request->input('list') ?? 'all';
            $resource = ScriptInstance::withTrashed();
            if ( $list === 'my') {
                $resource->findByUserId(Auth::id());
            }
            if (!empty($search)) {
                $resource->where('script_instances.tag_name', 'like', "%{$search}%")
                    ->orWhere('script_instances.tag_user_email', 'like', "%{$search}%");
            }

            $resource->when($sort, function ($query, $sort) use ($order) {
                if (!empty(ScriptInstance::ORDER_FIELDS[$sort])) {
                    return QueryHelper::orderScriptInstance($query, ScriptInstance::ORDER_FIELDS[$sort], $order);
                } else {
                    return $query->orderBy('aws_status', 'asc')->orderBy('start_time', 'desc');
                }
            }, function ($query) {
                return $query->orderBy('aws_status', 'asc')->orderBy('start_time', 'desc');
            });

            $scripts = (new ScriptInstanceCollection($resource->paginate($limit)))->response()->getData();
            $meta = $scripts->meta ?? null;

            $response = [
                'data' => $scripts->data ?? [],
                'total' => $meta->total ?? 0
            ];

            return $this->success($response);

        } catch (Throwable $throwable) {
            return $this->error(__('keywords.server_error'), $throwable->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function regions(Request $request)
    {
        $regions = AwsRegion::onlyEc2()->pluck('id', 'name')->toArray();
        $result = [];

        foreach ($regions as $name => $id) {
            array_push($result, ['name' => $name, 'id' => $id]);
        }

        return $this->success([
            'data' => $result
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function show(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $resource = ScriptInstance::withTrashed()->find($id);
        if(!$resource) {
            $this->error('Not found', __('user.scripts.not_found'));
        }

        $ip = $this->getIp();
        dispatch(new UpdateInstanceSecurityGroup($user, $ip, $resource));
        return $this->success((new ScriptInstanceResource($resource))->toArray($request));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {

            $instance = $this->getInstanceWithCheckUser($id);

            if (empty($instance)) {
                return $this->notFound(__('user.not_found'), __('user.instances.not_found'));
            }

            $running = ScriptInstance::STATUS_RUNNING;
            $stopped = ScriptInstance::STATUS_STOPPED;
            $terminated = ScriptInstance::STATUS_TERMINATED;

            if (!empty($request->input('update'))) {
                $updateData = $request->validate([
                    'update.status' => "in:{$running},{$stopped},{$terminated}"
                ]);

                foreach ($updateData['update'] as $key => $value) {
                    switch ($key) {
                        case 'status':
                            $user_id = Auth::id();

                            if (InstanceHelper::changeInstanceStatus($value, $id, $user_id)) {

                                $instance = new ScriptInstanceResource(ScriptInstance::withTrashed()
                                    ->where('id', '=', $id)->first());

                                broadcast(new InstanceStatusUpdated($user_id));

                                return $this->success($instance->toArray($request));
                            } else {
                                return $this->error(__('user.server_error'), __('user.instances.not_updated'));
                            }
                        default:
                            return $this->error(__('user.server_error'), __('user.instances.not_updated'));
                    }
                }

            }

            return $this->error(__('user.server_error'), __('user.instances.not_updated'));

        } catch (Throwable $throwable) {
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
    }

    /**
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function reportIssue(Request $request, $id)
    {
        $screenshots = $request->input('screenshots');
        $message = $request->input('message');
        $instance = ScriptInstance::withTrashed()->find($id);

        if (empty($instance)) {
            return $this->error(__('keywords.not_found'), __('keywords.scripts.not_found'));
        }

        if (empty($screenshots)) {
            return $this->error(__('keywords.error'), __('keywords.scripts.error_screenshots'));
        }

        try {

            Log::info("Report Issue");

            $objects = S3Object::whereIn('id', $screenshots)->get();

            if ($objects->isNotEmpty()) {

                $sources = [];

                foreach ($objects as $object) {
                    $pathInfo = pathinfo($object->path);
                    $sources[] = [
                        'source' => $object->getS3Path(),
                        'path' => "screenshots/{$object->instance->aws_instance_id}/{$pathInfo['basename']}"
                    ];
                }

                $aws = new Aws();
                $urls = $aws->copyIssuedObject($sources);

                $body = "User: {$request->user()->email}\nInstance ID: {$instance->aws_instance_id}\nScript Name: {$instance->script->name}
                \nMessage: {$message}";

                Log::debug($body);

                if (!empty($urls)) {
                    $screenshots = '';
                    foreach ($urls as $url) {
                        $pathInfo = pathinfo($url);
                        $screenshots .= " ![{$pathInfo['basename']}]({$url})\n";
                    }
                    $body = $body . "\n{$screenshots}";
                }

                Log::debug($body);

                GitHub::createIssue('Issue Report', $body);

                return $this->success([]);
            }

            return $this->error(__('keywords.error'), __('keywords.scripts.not_found_screenshots'));

        } catch (Throwable $throwable) {
            Log::error($throwable->getMessage());
            return $this->error(__('keywords.server_error'), $throwable->getMessage());
        }
    }

    /**
     * @param string|null $id
     * @param bool $withTrashed
     * @return ScriptInstance|null
     */
    public function getInstanceWithCheckUser(?string $id, $withTrashed = false): ?ScriptInstance
    {
        /** @var ScriptInstance $query */
        $query = ScriptInstance::where('id', '=', $id)->orWhere('aws_instance_id', '=', $id);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->first();
    }
}

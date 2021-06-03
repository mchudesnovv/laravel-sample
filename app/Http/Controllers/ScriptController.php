<?php

namespace App\Http\Controllers;

use App\Script;
use App\Helpers\GeneratorID;
use App\Helpers\S3BucketHelper;
use App\Http\Requests\ScriptCreateRequest;
use App\Http\Requests\ScriptUpdateRequest;
use App\Http\Resources\ScriptCollection;
use App\Http\Resources\ScriptResource;
use App\Http\Resources\TagCollection;
use App\Jobs\SyncLocalScripts;
use App\Tag;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Throwable;

class ScriptController extends AppController
{
    const PAGINATE = 1;

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $limit      = $request->query('limit') ?? self::PAGINATE;
            $search     = $request->input('search');
            $sort       = $request->input('sort');
            $order      = $request->input('order') ?? 'asc';

            $resource = Script::query();

            if (! empty($search)) {
                $resource->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }

            if (! empty($sort)) {
                $resource->orderBy($sort, $order);
            }

            $scripts   = (new ScriptCollection($resource->paginate($limit)))->response()->getData();
            $meta   = $scripts->meta ?? null;

            $response = [
                'data'  => $scripts->data ?? [],
                'total' => $meta->total ?? 0
            ];

            return $this->success($response);

        } catch (Throwable $throwable) {
            return $this->error(__('auth.forbidden'), $throwable->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ScriptCreateRequest $request
     * @return JsonResponse
     */
    public function store(ScriptCreateRequest $request)
    {
        try{
            $data                   = $request->validated();
            $name                   = $data['name'];
            $path                   = $data['path'] ?? null;
            $custom_script          = $data['aws_custom_script'];
            $parameters             = $data['parameters'] ?? null;

            $random                 = GeneratorID::generate();
            $folderName             = "scripts/{$random}";

            if(!empty($custom_script)) {
                $parameters = S3BucketHelper::extractParamsFromScript($custom_script);
            }

            if(empty($path)) {
                $path = Str::slug($name, '_') . '.custom.js';
            }

            $script = Script::create([
                'name'              => $name,
                'description'       => $data['description'],
                'parameters'        => $parameters,
                'path'              => $path,
                's3_path'           => $folderName,
                'type'              => $data['type'],
            ]);

            if (empty($script)) {
                return $this->error(__('user.server_error'), __('user.scripts.error_create'));
            }

            S3BucketHelper::updateOrCreateFilesS3(
                $script,
                Storage::disk('s3'),
                $custom_script,
                $data['aws_custom_package_json'],
            );

            $this->addTagsToScript($script, $data['tags']);
            $this->addUsersToScript($script, $data['users']);

            return $this->success([
                'id'                => $script->id ?? null
            ], __('user.scripts.success_create'));

        } catch(Throwable $throwable) {
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ScriptUpdateRequest $request
     * @param $id
     * @return JsonResponse
     */
    public function update(ScriptUpdateRequest $request, $id)
    {
        try{
            $script = Script::find($id);

            if (empty($script)) {
                return $this->notFound(__('user.not_found'), __('user.scripts.not_found'));
            }

            $data                   = $request->validated();
            $updateData             = $data['update'];
            $custom_script          = $updateData['aws_custom_script'];
            $name                   = $updateData['name'];
            $path                   = $updateData['path'] ?? null;
            $parameters             = $updateData['parameters'] ?? null;
            $tags                   = $updateData['tags'];
            $users                  = $updateData['users'];
            $folderName             = $script->s3_path;

            if(!empty($custom_script)) {
                $parameters = S3BucketHelper::extractParamsFromScript($custom_script);
            }

            if(empty($path)) {
                $path = Str::slug($name, '_') . '.custom.js';
            }

            $script->fill([
                'name'              => $name,
                'description'       => $updateData['description'],
                'parameters'        => $parameters,
                'path'              => $path,
                's3_path'           => $folderName,
                'status'            => $updateData['status'],
                'type'              => $updateData['type'],
            ]);

            if ($script->save()) {

                S3BucketHelper::updateOrCreateFilesS3(
                    $script,
                    Storage::disk('s3'),
                    $custom_script,
                    $updateData['aws_custom_package_json']
                );

                if(!empty($tags)) $this->addTagsToScript($script, $tags);
                if(!empty($users)) $this->addUsersToScript($script, $users);
                return $this->success((new ScriptResource($script))->toArray($request));
            }
        } catch (Throwable $throwable){
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
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
        try{
            $script = Script::findOrFail($id);
            if(!$script) {
                $this->error('Not found', __('scripts.not_found'));
            }

            return $this->success((new ScriptResource($script))->toArray($request));
        } catch (Throwable $throwable){
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
    }

    /**
     * Update status the specified resource in storage.
     *
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        try{
            $script = Script::find($id);

            if (empty($script)) {
                return $this->notFound(__('user.not_found'), __('user.scripts.not_found'));
            }

            $script->fill($request['update']);

            if ($script->save()) {
                return $this->success((new ScriptResource($script))->toArray($request));
            }
        } catch (Throwable $throwable) {
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param $id
     * @return JsonResponse|Response
     */
    public function destroy($id)
    {
        try{
            $script = Script::find($id);

            if (empty($script)) {
                return $this->notFound(__('user.not_found'), __('user.scripts.not_found'));
            }

            if ($script->delete()) {
                S3BucketHelper::deleteFilesS3(
                    $script->s3_path
                );
                return $this->success(null, __('user.scripts.success_delete'));
            }

            return $this->error(__('user.error'), __('user.scripts.not_deleted'));

        } catch (Throwable $throwable){
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getTags(Request $request)
    {
        try {
            $limit  = $request->query('limit') ?? self::PAGINATE;
            $search = $request->input('search');
            $sort   = $request->input('sort');
            $order  = $request->input('order') ?? 'asc';

            $resource = Tag::where('status', '=', 'active');

            if (!empty($search)) {
                $resource->where('name', 'like', "%{$search}%");
            }

            if (!empty($sort)) {
                $resource->orderBy($sort, $order);
            }

            $scripts   = (new TagCollection($resource->paginate($limit)))->response()->getData();
            $meta   = $scripts->meta ?? null;

            $response = [
                'data'  => $scripts->data ?? [],
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
    public function syncScripts(Request $request)
    {
        try {
            dispatch(new SyncLocalScripts($request->user()));
            return $this->success([], __('user.instances.success_sync'));
        } catch (Throwable $throwable) {
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
    }

    /**
     * @param Script $script
     * @param array|null $tags
     */
    private function addTagsToScript(Script $script, ?array $tags): void
    {
        if (! empty($script) && ! empty($tags)) {

            $script->tags()->detach();

            $tagsIds = [];

            foreach ($tags as $tag){

                $tagObj = Tag::findByName($tag);

                if (empty($tagObj)) {
                    $tagObj = Tag::create([
                        'name' => $tag
                    ]);
                }

                $tagsIds[] = $tagObj->id ?? null;
            }

            $script->tags()->attach($tagsIds);
        }
    }

    /**
     * @param Script $script
     * @param array|null $input
     */
    private function addUsersToScript(Script $script, ?array $input): void
    {
        if (! empty($script) && ! empty($input)) {
            $script->users()->detach();
            $users  = User::whereIn('id', $input)->pluck('id')->toArray();
            $script->users()->sync($users);
        }
    }
}

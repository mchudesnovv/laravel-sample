<?php

namespace App\Http\Controllers;

use App\Helpers\QueryHelper;
use App\Http\Resources\ScheduleCollection;
use App\Http\Resources\ScheduleResource;
use App\SchedulingInstance;
use App\SchedulingInstancesDetails;
use App\ScriptInstance;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ScheduleController extends AppController
{
    const PAGINATE = 1;

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        try {

            $limit  = $request->query('limit') ?? self::PAGINATE;
            $list   = $request->input('list');
            $search = $request->input('search');
            $sort   = $request->input('sort');
            $order  = $request->input('order') ?? 'asc';

            $resource = SchedulingInstance::query();

            if ($list === 'my') {
                $resource->findByUserId(Auth::id());
            }

            if (!empty($search)) {
                $resource->whereHas('instance', function (Builder $query) use ($search) {
                    $query->where('tag_name', 'like', "%{$search}%");
                });
            }

            $resource->when($sort, function ($query, $sort) use ($order) {
                if (!empty(SchedulingInstance::ORDER_FIELDS[$sort])) {
                    return QueryHelper::orderScriptScheduling($query, SchedulingInstance::ORDER_FIELDS[$sort], $order);
                } else {
                    return $query->orderBy('created_at', 'desc');
                }
            }, function ($query) {
                return $query->orderBy('created_at', 'desc');
            });

            $instances  = (new ScheduleCollection($resource->paginate($limit)))->response()->getData();
            $meta       = $instances->meta ?? null;

            $response = [
                'data'  => $instances->data ?? [],
                'total' => $meta->total ?? 0
            ];

            return $this->success($response);

        } catch (Throwable $throwable) {
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteSchedulerDetails(Request $request): JsonResponse
    {
        if (!empty($request->input('ids'))) {

            try {

                $count = SchedulingInstancesDetails::whereIn('id', $request->input('ids'))->delete();

                if ($count) {
                    return $this->success();
                }

                return $this->error(__('user.error'), __('user.delete_error'));
            } catch(Throwable $throwable) {
                return $this->error(__('user.server_error'), $throwable->getMessage());
            }
        }

        return $this->error(__('user.error'), __('user.parameters_incorrect'));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        try {

            $data = $request->validate([
                'instance_id' => 'required|string'
            ]);

            $instance = ScriptInstance::findByInstanceId($data['instance_id'])->first();

            if (empty($instance)) {
                return $this->error(__('user.server_error'), 'Such script does not exist');
            }

            $schedule = SchedulingInstance::findByUserInstanceId($instance->id, Auth::id())
                ->first();

            if (empty($schedule)) {
                $schedule = SchedulingInstance::create([
                    'user_id'       => Auth::id(),
                    'instance_id'   => $instance->id,
                ]);

                if ($schedule) {
                    return $this->success();
                }
            }

            return $this->error(__('user.error'), __('user.parameters_incorrect'));

        } catch (Throwable $throwable) {
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return JsonResponse|Response
     */
    public function show($id)
    {
        if (!empty($id)) {
            try {

                $instance = SchedulingInstance::with('userInstance')
                    ->where('id', '=', $id)->first();

                if (!empty($instance)) {
                    $resource = new ScheduleResource($instance);

                    return $this->success([
                        'instance' => $resource->response()->getData(),
                    ]);
                }

                return $this->notFound(__('user.not_found'), __('user.not_found'));

            } catch (Throwable $throwable) {
                return $this->error(__('user.server_error'), $throwable->getMessage());
            }
        }

        return $this->error(__('user.error'), __('user.parameters_incorrect'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param  int  $id
     * @return JsonResponse|Response
     */
    public function update(Request $request, $id)
    {
        try{
            $instance = SchedulingInstance::find($id);

            if (empty($instance)) {
                return $this->notFound(__('user.not_found'), __('user.scheduling.not_found'));
            }

            $active     = SchedulingInstance::STATUS_ACTIVE;
            $inactive   = SchedulingInstance::STATUS_INACTIVE;

            if (!empty($request->input('update'))) {
                $updateData = $request->validate([
                    'update.status'     => "in:{$active},{$inactive}",
                    'update.details'    => 'array',
                ]);
                return $this->updateSimpleInfo($request, $updateData, $instance);
            } else {
                return $this->updateFullInfo($request, $instance);
            }

        } catch (Throwable $throwable) {
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
    }

    /**
     * @param Request $request
     * @param array $updateData
     * @param SchedulingInstance $instance
     * @return JsonResponse
     * @throws Exception
     */
    private function updateSimpleInfo(Request $request, array $updateData, SchedulingInstance $instance)
    {
        foreach ($updateData['update'] as $key => $value) {
            switch ($key) {
                case 'status':
                    $instance->fill(['status' => $value]);
                    if ($instance->save()) {
                        return $this->success((new ScheduleResource($instance))->toArray($request));
                    } else {
                        return $this->error(__('user.server_error'), __('user.scheduling.not_updated'));
                    }
                case 'details':
                    $this->updateOrCreateSchedulingInstancesDetails($instance, $value,
                        $request->user()->timezone->timezone ?? '+00:00');
                    return $this->success((new ScheduleResource($instance))->toArray($request));
                default:
                    return $this->error(__('user.server_error'), __('user.scheduling.not_updated'));
            }
        }

        return $this->error(__('user.error'), __('user.parameters_incorrect'));
    }

    /**
     * @param Request $request
     * @param SchedulingInstance $instance
     * @return JsonResponse
     */
    private function updateFullInfo(Request $request, SchedulingInstance $instance)
    {
        return $this->error(__('user.error'), __('user.parameters_incorrect'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return JsonResponse|Response
     */
    public function destroy($id)
    {
        try {

            $instance = SchedulingInstance::find($id);

            if (empty($instance)) {
                return $this->notFound(__('user.not_found'), __('user.scheduling.not_found'));
            }

            if ($instance->delete()) {
                return $this->success();
            }

            return $this->error(__('user.error'), __('user.scheduling.not_deleted'));

        } catch (Throwable $throwable) {
            return $this->error(__('user.server_error'), $throwable->getMessage());
        }
    }

    /**
     * @param SchedulingInstance $instance
     * @param array $details
     * @param string $timezone
     * @return void
     * @throws Exception
     */
    private function updateOrCreateSchedulingInstancesDetails(SchedulingInstance $instance, array $details, $timezone): void
    {
        // Delete all
        SchedulingInstancesDetails::where('scheduling_id', '=', $instance->id ?? null)->delete();

        /**
         * details[0][status] = running | stopped
         * details[0][time] = 6:00 PM
         * details[0][day] = Friday
         */

        foreach ($details as $detail) {

            switch ($detail['status']) {
                case SchedulingInstancesDetails::STATUS_RUNNING:
                case SchedulingInstancesDetails::STATUS_STOPPED:
                    $status = $detail['status'];
                    break;
                default:
                    $status = SchedulingInstancesDetails::STATUS_STOPPED;
                    break;
            }

            SchedulingInstancesDetails::updateOrCreate([
                'scheduling_id' => $instance->id ?? null,
                'day'           => $detail['day'],
                'time'          => $detail['time'],
                'time_zone'     => $timezone,
                'status'        => $status,
            ]);
        }
    }
}

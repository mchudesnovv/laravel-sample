<?php

namespace App\Http\Controllers;

use App\InstanceSessionsHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstanceSessionHistoriesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request)
    {
        return view('user.scripts.running.session-history', [
            'sessions' => InstanceSessionsHistory::with('schedulingInstance.instance')->paginate(5),
        ]);
    }
}

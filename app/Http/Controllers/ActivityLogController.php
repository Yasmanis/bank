<?php

namespace App\Http\Controllers;

use Spatie\Activitylog\Models\Activity;

class ActivityLogController
{
    public function index()
    {
        $logs = Activity::latest()->take(2)->get();
        return response()->json($logs);
    }

}

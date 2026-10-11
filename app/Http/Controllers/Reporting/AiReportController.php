<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Services\Reporting\AiReportAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiReportController extends Controller
{
    use AuthorizesPermissions;

    public function suggest(Request $request, AiReportAssistant $assistant): JsonResponse
    {
        $this->authorizePermission('reporting.ai.use');

        $data = $request->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        return response()->json(['data' => $assistant->suggest($data['prompt'])]);
    }
}

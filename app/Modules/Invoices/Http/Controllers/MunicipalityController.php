<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoices\Http\Requests\SearchMunicipalitiesRequest;
use App\Modules\Invoices\Services\Nfse\Municipalities;
use Illuminate\Http\JsonResponse;

class MunicipalityController extends Controller
{
    public function index(SearchMunicipalitiesRequest $request): JsonResponse
    {
        return response()->json(['data' => Municipalities::search($request->term())]);
    }
}

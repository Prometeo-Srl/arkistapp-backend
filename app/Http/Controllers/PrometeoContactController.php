<?php

namespace App\Http\Controllers;

use App\Http\Resources\PrometeoContactResource;
use App\Models\PrometeoContact;
use Illuminate\Http\Request;

class PrometeoContactController extends Controller
{
    /** Staff directory: visible contacts, any authenticated user. */
    public function index(Request $request)
    {
        return PrometeoContactResource::collection(
            PrometeoContact::query()
                ->where('is_visible', true)
                ->orderBy('position')
                ->get()
        );
    }
}

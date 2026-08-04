<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccessGrantRequest;
use App\Http\Resources\AccessGrantResource;
use App\Models\AccessGrant;
use App\Models\Category;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Granular sharing ("Gestisci accesso" / "Condividi"): one grant covers a
 * category, folder or file, for a user or an org chart role.
 */
class AccessGrantController extends Controller
{
    public function store(StoreAccessGrantRequest $request)
    {
        $data = $request->validated();

        $grantable = $this->resolveGrantable($data['grantable_type'], $data['grantable_id']);
        $this->authorize('update', $grantable);

        $grant = AccessGrant::updateOrCreate(
            [
                'grantable_type' => $data['grantable_type'],
                'grantable_id' => $data['grantable_id'],
                'grantee_type' => $data['grantee_type'],
                'grantee_id' => $data['grantee_id'],
            ],
            [
                'permission' => $data['permission'],
                'granted_by_id' => $request->user()->getKey(),
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        return (new AccessGrantResource($grant))->response()
            ->setStatusCode($grant->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, AccessGrant $grant)
    {
        $grantable = $grant->grantable;
        abort_unless($grantable, 404);

        $this->authorize('update', $grantable);

        $grant->delete();

        return response()->noContent();
    }

    private function resolveGrantable(string $type, int $id): Model
    {
        return match ($type) {
            'category' => Category::query()->findOrFail($id),
            'folder' => Folder::query()->findOrFail($id),
            'file' => File::query()->findOrFail($id),
        };
    }
}

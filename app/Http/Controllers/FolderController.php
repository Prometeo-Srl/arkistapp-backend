<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Http\Resources\FolderResource;
use App\Models\Company;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->authorize('view', $company);

        $folders = Folder::query()
            ->whereHas('category', fn (Builder $q) => $q->where('company_id', $company->getKey()))
            ->when(
                $request->filled('category_id'),
                fn (Builder $q) => $q->where('category_id', $request->integer('category_id'))
            )
            ->where(fn (Builder $q) => $this->scopeVisibleFolders($q, $request->user(), $company))
            ->with(['parent', 'children'])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return FolderResource::collection($folders);
    }

    public function store(StoreFolderRequest $request, Company $company)
    {
        $this->authorize('create', [Folder::class, $company]);

        $folder = $company->categories()
            ->findOrFail($request->validated('category_id'))
            ->folders()
            ->create([
                ...$request->validated(),
                'created_by_id' => $request->user()->getKey(),
            ]);

        return (new FolderResource($folder->load(['parent', 'children'])))->response()->setStatusCode(201);
    }

    public function update(UpdateFolderRequest $request, Folder $folder)
    {
        $this->authorize('update', $folder);

        $folder->update($request->safe()->only(['name', 'icon', 'position', 'parent_folder_id']));

        return new FolderResource($folder->fresh(['parent', 'children']));
    }

    public function destroy(Request $request, Folder $folder)
    {
        $this->authorize('delete', $folder);

        $folder->delete();

        return response()->noContent();
    }

    /** Everyone sees shared folders; personal folders only their owner and the admins. */
    private function scopeVisibleFolders(Builder $q, $user, Company $company): Builder
    {
        if ($user->isAdminOf($company)) {
            return $q;
        }

        return $q->whereNull('is_personal_of_user_id')
            ->orWhere('is_personal_of_user_id', $user->getKey());
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Http\Resources\FolderResource;
use App\Models\Company;
use App\Models\Folder;
use App\Models\User;
use App\Support\EffectiveAccess;
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
            ->with(['parent', 'children', 'createdBy'])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return FolderResource::collection($folders);
    }

    public function store(StoreFolderRequest $request, Company $company)
    {
        // The parent decides whether a non-admin may create here: inside their own
        // personal branch, or wherever they hold an editor grant.
        $parent = $request->validated('parent_folder_id')
            ? Folder::query()->findOrFail($request->validated('parent_folder_id'))
            : null;

        $this->authorize('create', [Folder::class, $company, $parent]);

        $folder = $company->categories()
            ->findOrFail($request->validated('category_id'))
            ->folders()
            ->create([
                ...$request->validated(),
                'created_by_id' => $request->user()->getKey(),
            ]);

        return (new FolderResource($folder->load(['parent', 'children', 'createdBy'])))->response()->setStatusCode(201);
    }

    public function update(UpdateFolderRequest $request, Folder $folder)
    {
        $this->authorize('update', $folder);

        $folder->update($request->safe()->only(['name', 'icon', 'position', 'parent_folder_id']));

        return new FolderResource($folder->fresh(['parent', 'children', 'createdBy']));
    }

    public function destroy(Request $request, Folder $folder)
    {
        $this->authorize('delete', $folder);

        $folder->delete();

        return response()->noContent();
    }

    /**
     * A worker sees their own personal branch and whatever the company shared with
     * them; the admins see the whole archive.
     */
    private function scopeVisibleFolders(Builder $q, User $user, Company $company): Builder
    {
        if ($user->isOperator() || $user->isAdminOf($company)) {
            return $q;
        }

        return $q->whereIn('id', EffectiveAccess::visibleFolderIds($user, $company));
    }
}

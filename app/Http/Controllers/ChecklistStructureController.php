<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsChecklistDrafts;
use App\Http\Requests\SaveChecklistStructureRequest;
use App\Http\Requests\StoreChecklistImageRequest;
use App\Http\Resources\ChecklistResource;
use App\Models\Checklist;
use App\Support\ChecklistStructure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChecklistStructureController extends Controller
{
    use GuardsChecklistDrafts;

    private const IMAGE_DIRECTORY = 'checklist-images';

    /**
     * The builder's single `salva`: sections, questions and options as one nested
     * payload, reconciled against what is stored (ADR-0004).
     *
     * The payload is authoritative, so what it omits is deleted - which is safe
     * only because a shared checklist is refused below.
     */
    public function update(SaveChecklistStructureRequest $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);
        $this->guardDraft($checklist);

        ChecklistStructure::save($checklist, $request->validated('sections'));

        return new ChecklistResource($checklist->fresh(['sections.questions.options', 'createdBy']));
    }

    /**
     * An image for a question or an option, uploaded before the tree that
     * references it: the builder holds the returned path and sends it with the
     * next structure save.
     *
     * Orphans are possible - an image uploaded for a question the DDL then deleted
     * - and are left alone deliberately: a stored path costs less than a reference
     * count that has to survive an abandoned editing session.
     */
    public function storeImage(StoreChecklistImageRequest $request, Checklist $checklist)
    {
        $this->authorize('manage', $checklist);
        $this->guardDraft($checklist);

        $path = $request->file('image')->store(self::IMAGE_DIRECTORY);

        return response()->json(['data' => ['image_path' => $path]], 201);
    }

    /**
     * The image back out, as an attachment with nosniff like every other download
     * here. `store()` writes a hashed basename, so the name is reduced to one
     * before it is joined to the directory: a path in it is the only way this
     * could read somewhere else.
     */
    public function showImage(Request $request, Checklist $checklist, string $name)
    {
        $this->authorize('view', $checklist);

        $path = self::IMAGE_DIRECTORY.'/'.basename($name);

        abort_unless(Storage::exists($path), 404);

        return Storage::download($path, basename($name), ['X-Content-Type-Options' => 'nosniff']);
    }
}

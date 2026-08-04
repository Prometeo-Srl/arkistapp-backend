<?php

namespace App\Http\Controllers;

use App\Enums\SupportMessageKind;
use App\Http\Requests\StoreSupportMessageRequest;
use App\Http\Requests\StoreSupportThreadRequest;
use App\Http\Resources\SupportMessageResource;
use App\Http\Resources\SupportThreadResource;
use App\Models\Company;
use App\Models\SupportThread;
use Illuminate\Http\Request;

class SupportThreadController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->authorize('viewAny', [SupportThread::class, $company]);

        $threads = SupportThread::query()
            ->where('company_id', $company->getKey())
            ->with(['openedBy', 'messages.sender'])
            ->latest('last_message_at')
            ->get();

        return SupportThreadResource::collection($threads);
    }

    /** Opens a thread together with its first message. */
    public function store(StoreSupportThreadRequest $request, Company $company)
    {
        $this->authorize('create', [SupportThread::class, $company]);

        $thread = SupportThread::create([
            'company_id' => $company->getKey(),
            'opened_by_id' => $request->user()->getKey(),
            'subject' => $request->validated('subject'),
            'channel' => $request->validated('channel') ?? 'chat',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $thread->messages()->create([
            'sender_id' => $request->user()->getKey(),
            'body' => $request->validated('body'),
            'kind' => SupportMessageKind::Text,
        ]);

        return (new SupportThreadResource($thread->load(['openedBy', 'messages.sender'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, SupportThread $thread)
    {
        $this->authorize('view', $thread);

        return new SupportThreadResource($thread->load(['openedBy', 'assignedOperator', 'messages.sender', 'messages.attachments']));
    }

    public function storeMessage(StoreSupportMessageRequest $request, SupportThread $thread)
    {
        $this->authorize('message', $thread);

        $message = $thread->messages()->create([
            'sender_id' => $request->user()->getKey(),
            'body' => $request->validated('body'),
            'kind' => $request->validated('kind') ?? SupportMessageKind::Text,
            'call_duration_seconds' => $request->validated('call_duration_seconds'),
        ]);

        $thread->update(['last_message_at' => now()]);

        return (new SupportMessageResource($message->load('sender')))
            ->response()
            ->setStatusCode(201);
    }

    /** Marks every message from someone else as read. */
    public function markRead(Request $request, SupportThread $thread)
    {
        $this->authorize('read', $thread);

        $thread->messages()
            ->where('sender_id', '!=', $request->user()->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function close(Request $request, SupportThread $thread)
    {
        $this->authorize('close', $thread);

        $thread->update(['status' => 'closed', 'closed_at' => now()]);

        return new SupportThreadResource($thread->fresh()->load(['openedBy', 'messages.sender']));
    }

    /** Open threads across every company; operators are global. */
    public function operatorIndex(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);

        $threads = SupportThread::query()
            ->where('status', 'open')
            ->with(['company', 'openedBy', 'messages.sender'])
            ->latest('last_message_at')
            ->get();

        return SupportThreadResource::collection($threads);
    }
}

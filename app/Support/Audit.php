<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes the audit trail behind "cronologia" (prototype 234) and the admin-only
 * company audit log.
 *
 * The table has existed since the first migration but nothing ever wrote to it,
 * so every screen over it was empty by construction. Recording happens in model
 * observers rather than in the controllers: a mutation reaches the trail because
 * it touched the model, not because a particular endpoint remembered to log it.
 */
final class Audit
{
    public static function record(string $action, Model $auditable, ?int $companyId): void
    {
        AuditLog::create([
            'company_id' => $companyId,
            // Null for anything the console does — seeders, imports, scheduled jobs.
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'ip_address' => request()->ip(),
            // The column is a plain string; a long UA would blow the row up.
            'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
        ]);
    }
}

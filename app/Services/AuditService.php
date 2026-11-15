<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    public function log(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
        ?int $adminId = null,
        ?int $storeId = null,
        ?Request $request = null
    ): AuditLog {
        return AuditLog::create([
            'admin_user_id' => $adminId ?? auth()->id(),
            'store_id' => $storeId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\AgeAssuranceResult;
use App\Models\Store;

class AgeAssuranceService
{
    public function __construct(private StoreContext $storeContext) {}

    /**
     * Record a trusted age-assurance result.
     * A frontend checkbox or localStorage flag is never accepted.
     */
    public function recordTrustedResult(
        string $subjectType,
        string $subjectIdentifier,
        string $result,
        ?string $provider = null,
        ?string $assertion = null,
        ?\DateTimeInterface $expiresAt = null
    ): AgeAssuranceResult {
        $store = $this->storeContext->get();

        if ($store->code !== 'adult') {
            throw new \RuntimeException('Age assurance is only required for the adult store.');
        }

        return AgeAssuranceResult::updateOrCreate(
            [
                'store_id' => $store->id,
                'subject_type' => $subjectType,
                'subject_identifier' => $subjectIdentifier,
            ],
            [
                'provider' => $provider,
                'result' => $result,
                'assertion_hash' => $assertion ? hash('sha256', $assertion) : null,
                'verified_at' => $result === AgeAssuranceResult::RESULT_ELIGIBLE ? now() : null,
                'expires_at' => $expiresAt,
            ]
        );
    }

    /**
     * Check whether the current subject has a valid eligible result.
     */
    public function isEligible(string $subjectType, string $subjectIdentifier): bool
    {
        $store = $this->storeContext->get();

        // Non-adult stores do not require age assurance
        if ($store->code !== 'adult') {
            return true;
        }

        $record = AgeAssuranceResult::where('store_id', $store->id)
            ->where('subject_type', $subjectType)
            ->where('subject_identifier', $subjectIdentifier)
            ->first();

        return $record && $record->isValid();
    }
}

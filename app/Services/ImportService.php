<?php

namespace App\Services;

use App\Models\ImportJob;
use App\Models\ImportRow;
use App\Models\ProductVariant;
use App\Models\ProductAttributeDefinition;
use Illuminate\Support\Facades\Storage;

class ImportService
{
    public function __construct(private StoreContext $storeContext) {}

    /**
     * Generate a store-specific CSV/Excel template based on the active store's attribute definitions.
     */
    public function generateTemplate(): string
    {
        $store = $this->storeContext->get();

        $definitions = ProductAttributeDefinition::where('store_id', $store->id)
            ->orderBy('sort_order')
            ->get();

        $headers = [
            'name',
            'slug',
            'category_slug',
            'brand_slug',
            'description',
            'sku',
            'variant_name',
            'price',
            'stock',
        ];

        foreach ($definitions as $def) {
            $headers[] = 'attr_' . $def->code;
        }

        $headers[] = 'image_filename'; // matched against ZIP
        $headers[] = 'image_url'; // direct image link

        $csv = implode(',', $headers) . "\n";
        $csv .= implode(',', array_fill(0, count($headers), '')) . "\n";

        return $csv;
    }

    /**
     * Validate uploaded rows (create-only). Existing SKU is always rejected.
     * Returns preview data; does not create products yet.
     */
    public function validateJob(ImportJob $job, array $rows): ImportJob
    {
        $this->assertSameStore($job);

        $job->update(['status' => ImportJob::STATUS_VALIDATING]);

        $valid = 0;
        $invalid = 0;

        foreach ($rows as $index => $row) {
            $errors = [];
            $sku = $row['sku'] ?? null;

            if (empty($sku)) {
                $errors[] = 'SKU is required.';
            } elseif (ProductVariant::where('store_id', $job->store_id)->where('sku', $sku)->exists()) {
                $errors[] = 'SKU already exists (create-only import).';
            }

            if (empty($row['name'])) {
                $errors[] = 'Name is required.';
            }

            if (empty($row['price']) || !is_numeric($row['price'])) {
                $errors[] = 'Valid price is required.';
            }

            $status = empty($errors) ? ImportRow::STATUS_VALID : ImportRow::STATUS_INVALID;

            ImportRow::create([
                'payload' => $row,
                'store_id' => $job->store_id,
                'import_job_id' => $job->id,
                'row_number' => $index + 2, // header is row 1
                'sku' => $sku,
                'status' => $status,
                'error_codes' => empty($errors) ? null : ['validation'],
                'error_messages' => empty($errors) ? null : $errors,
            ]);

            if (empty($errors)) {
                $valid++;
            } else {
                $invalid++;
            }
        }

        $job->update([
            'status' => ImportJob::STATUS_READY,
            'total_rows' => count($rows),
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
        ]);

        return $job->fresh();
    }

    /**
     * Mark job for asynchronous processing (queue job will create products).
     * Must be idempotent: repeated confirm does not duplicate.
     */
    public function confirm(ImportJob $job): ImportJob
    {
        $this->assertSameStore($job);

        if ($job->status !== ImportJob::STATUS_READY) {
            throw new \RuntimeException('Job is not ready for confirmation.');
        }

        $job->update([
            'status' => ImportJob::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        // In production: dispatch ProcessImportJob::dispatch($job)
        return $job;
    }

    private function assertSameStore(ImportJob $job): void
    {
        if ($job->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }
    }
}

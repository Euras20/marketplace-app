<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\ImportRow;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ImportJob $job) {}

    public function handle(): void
    {
        $rows = ImportRow::where('import_job_id', $this->job->id)
            ->where('status', ImportRow::STATUS_VALID)
            ->get();

        foreach ($rows as $row) {
            // Idempotent: skip if already processed
            if ($row->status === ImportRow::STATUS_PROCESSED) {
                continue;
            }

            try {
                // Create product + variant (simplified; real implementation maps all columns)
                $product = Product::create([
                    'store_id' => $this->job->store_id,
                    'name' => 'Imported ' . $row->sku,
                    'slug' => 'imported-' . $row->sku,
                    'status' => Product::STATUS_DRAFT,
                ]);

                $payload = $row->payload ?? [];
                $imageUrl = $payload['image_url'] ?? null;
                if ($imageUrl) {
                    \App\Models\ProductMedia::create([
                        'store_id' => $this->job->store_id,
                        'product_id' => $product->id,
                        'storage_disk' => 'url',
                        'storage_path' => $imageUrl,
                        'is_primary' => true,
                        'sort_order' => 0,
                    ]);
                }

                ProductVariant::create([
                    'store_id' => $this->job->store_id,
                    'product_id' => $product->id,
                    'sku' => $row->sku,
                    'price' => 0, // real price from parsed row
                    'is_active' => true,
                ]);

                $row->update([
                    'status' => ImportRow::STATUS_PROCESSED,
                    'created_product_id' => $product->id,
                ]);
            } catch (\Throwable $e) {
                $row->update([
                    'status' => ImportRow::STATUS_FAILED,
                    'error_messages' => [$e->getMessage()],
                ]);
            }
        }

        $failed = ImportRow::where('import_job_id', $this->job->id)
            ->where('status', ImportRow::STATUS_FAILED)
            ->exists();

        $this->job->update([
            'status' => $failed ? ImportJob::STATUS_COMPLETED_WITH_ERRORS : ImportJob::STATUS_COMPLETED,
            'processed_rows' => $rows->count(),
            'completed_at' => now(),
        ]);
    }
}

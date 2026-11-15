<?php

namespace App\Http\Controllers\Admin;

use App\Models\ImportJob;
use App\Services\ImportService;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class ImportController
{
    public function __construct(
        private StoreContext $storeContext,
        private ImportService $imports
    ) {}

    public function template()
    {
        $csv = $this->imports->generateTemplate();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="import-template.csv"',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'data_file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
            'image_archive' => 'nullable|file|mimes:zip|max:51200',
        ]);

        $store = $this->storeContext->get();

        $dataPath = $request->file('data_file')->store('imports', 'private');
        $zipPath = $request->hasFile('image_archive')
            ? $request->file('image_archive')->store('imports', 'private')
            : null;

        $job = ImportJob::create([
            'store_id' => $store->id,
            'uploaded_by_admin_id' => auth()->id(),
            'data_file_path' => $dataPath,
            'image_archive_path' => $zipPath,
            'status' => ImportJob::STATUS_UPLOADED,
        ]);

        // In production: parse file and call validateJob
        return response()->json($job, 201);
    }

    public function show(ImportJob $job)
    {
        if ($job->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }

        return response()->json($job->load('rows'));
    }

    public function preview(ImportJob $job)
    {
        if ($job->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }

        return response()->json([
            'total' => $job->total_rows,
            'valid' => $job->valid_rows,
            'invalid' => $job->invalid_rows,
            'rows' => $job->rows,
        ]);
    }

    public function confirm(ImportJob $job)
    {
        $job = $this->imports->confirm($job);

        // Dispatch queue job
        \App\Jobs\ProcessImportJob::dispatch($job);

        return response()->json($job);
    }
}

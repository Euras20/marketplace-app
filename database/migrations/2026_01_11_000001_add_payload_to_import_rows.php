<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('import_rows', 'payload')) {
                $table->json('payload')->nullable()->after('sku');
            }
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};

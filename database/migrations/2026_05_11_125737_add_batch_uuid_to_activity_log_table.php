<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            if (!Schema::hasColumn(config('activitylog.table_name'), 'batch_uuid')) {
                $table->uuid('batch_uuid')->nullable()->after('properties');
            }
            if (!Schema::hasColumn(config('activitylog.table_name'), 'event')) {
                $table->string('event')->nullable()->after('subject_type');
            }
        });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->dropColumn(['batch_uuid', 'event']);
        });
    }
};

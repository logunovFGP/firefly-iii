<?php

/*
 * 2026_03_31_000001_create_import_sources.php
 * Copyright (c) 2026 james@firefly-iii.org.
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('transaction_journals', 'import_source_id')) {
            try {
                Schema::table('transaction_journals', static function (Blueprint $table): void {
                    $table->dropForeign(['import_source_id']);
                    $table->dropIndex('idx_tj_import_source_deleted');
                    $table->dropColumn('import_source_id');
                });
            } catch (QueryException $e) {
                app('log')->error(sprintf('Could not drop import_source_id from transaction_journals: %s', $e->getMessage()));
                app('log')->error('If the column does not exist (see error), this is not a problem. Otherwise, please open a GitHub discussion.');
            }
        }

        Schema::dropIfExists('import_sources');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('import_sources')) {
            Schema::create('import_sources', static function (Blueprint $table): void {
                $table->id();
                $table->timestamps();
                $table->bigInteger('user_group_id', false, true);
                $table->string('name', 64);

                $table->foreign('user_group_id')->references('id')->on('user_groups')->onDelete('cascade');
                $table->unique(['user_group_id', 'name'], 'unique_import_source_per_group');
            });
        }

        if (!Schema::hasColumn('transaction_journals', 'import_source_id')) {
            try {
                Schema::table('transaction_journals', static function (Blueprint $table): void {
                    $table->bigInteger('import_source_id', false, true)->nullable()->after('bill_id');
                    $table->foreign('import_source_id')->references('id')->on('import_sources')->onDelete('set null');
                    $table->index(['import_source_id', 'deleted_at'], 'idx_tj_import_source_deleted');
                });
            } catch (QueryException $e) {
                app('log')->error(sprintf('Could not add import_source_id to transaction_journals: %s', $e->getMessage()));
                app('log')->error('If the column already exists (see error), this is not a problem. Otherwise, please open a GitHub discussion.');
            }
        }
    }
};

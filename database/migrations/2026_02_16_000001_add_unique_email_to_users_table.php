<?php

declare(strict_types=1);

/*
 * 2026_02_16_000001_add_unique_email_to_users_table.php
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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a unique constraint to users.email with safe guards for legacy duplicates.
 */
class AddUniqueEmailToUsersTable extends Migration
{
    public function down(): void
    {
        if (Schema::hasColumn('users', 'email')) {
            try {
                Schema::table('users', static function (Blueprint $table): void {
                    $table->dropUnique(['email']);
                });
            } catch (QueryException $e) {
                app('log')->error(sprintf('Could not execute query: %s', $e->getMessage()));
                app('log')->error('If the index does not exist (see error), this is not a problem. Otherwise, please open a GitHub discussion.');
            }
        }
    }

    public function up(): void
    {
        if (!Schema::hasColumn('users', 'email')) {
            return;
        }

        $duplicateCount = DB::table('users')
            ->selectRaw('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicateCount > 0) {
            app('log')->error(
                sprintf('Skipping users.email unique constraint: %d duplicate email value(s) already exist. Please deduplicate users before rerunning this migration.', $duplicateCount)
            );
            return;
        }

        try {
            Schema::table('users', static function (Blueprint $table): void {
                $table->unique('email');
            });
        } catch (QueryException $e) {
            app('log')->error(sprintf('Could not execute query: %s', $e->getMessage()));
            app('log')->error('If this index already exists (see error), this is not a problem. Otherwise, please open a GitHub discussion.');
        }
    }
}

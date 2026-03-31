<?php

/*
 * ImportSourceController.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace FireflyIII\Api\V1\Controllers\System;

use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Enums\UserRoleEnum;
use FireflyIII\Models\ImportSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ImportSourceController
 *
 * Manages import source labels used for deduplication scoping.
 */
class ImportSourceController extends Controller
{
    protected array $acceptedRoles = [UserRoleEnum::MANAGE_TRANSACTIONS];

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function (Request $request, $next) {
            $this->validateUserGroup($request);

            return $next($request);
        });
    }

    /**
     * List all import sources for the current user group.
     */
    public function index(): JsonResponse
    {
        $sources = ImportSource::where('user_group_id', $this->userGroup->id)
            ->orderBy('name')
            ->get(['id', 'name', 'created_at']);

        return response()->json(['data' => $sources]);
    }

    /**
     * Find or create an import source by name (idempotent).
     */
    public function findOrCreate(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|min:1|max:64']);
        $name   = strtolower(trim($request->input('name')));
        $source = ImportSource::findOrCreateByName($this->userGroup->id, $name);

        return response()->json(['data' => ['id' => $source->id, 'name' => $source->name]], 200);
    }
}

<?php

/*
 * ExternalIdController.php
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

namespace FireflyIII\Api\V1\Controllers\Search;

use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Api\V1\Requests\Search\ExternalIdSearchRequest;
use FireflyIII\Enums\UserRoleEnum;
use FireflyIII\Models\ImportSource;
use FireflyIII\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class ExternalIdController
 *
 * Batch lookup of external_ids against journal_meta.
 * Returns a map of external_id => transaction summary or null.
 */
class ExternalIdController extends Controller
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
     * Search for transactions by external_id.
     *
     * Uses a single SQL query with WHERE IN for performance.
     * External IDs are JSON-encoded to match the storage format in journal_meta.data
     * (Eloquent model uses json_encode() without flags when setting the data attribute).
     */
    public function search(ExternalIdSearchRequest $request): JsonResponse
    {
        Log::debug('Now in API ExternalIdController::search()');

        $params      = $request->getSearchParameters();
        $externalIds = $params['external_ids'];
        $startDate   = $params['start_date'];
        $endDate     = $params['end_date'];

        /** @var User $admin */
        $admin       = auth()->user();

        // JSON-encode each ID to match the storage format in journal_meta.data.
        // The TransactionJournalMeta model stores data via json_encode($value) without flags.
        $jsonEncodedIds = array_map(
            static fn (string $id): string => json_encode($id),
            $externalIds
        );

        $query = DB::table('journal_meta')
            ->join(
                'transaction_journals',
                'journal_meta.transaction_journal_id',
                '=',
                'transaction_journals.id'
            )
            ->join(
                'transaction_groups',
                'transaction_journals.transaction_group_id',
                '=',
                'transaction_groups.id'
            )
            ->join('transactions as t_source', function ($join): void {
                $join->on('t_source.transaction_journal_id', '=', 'transaction_journals.id')
                    ->where('t_source.amount', '<', 0);
            })
            ->join('transactions as t_dest', function ($join): void {
                $join->on('t_dest.transaction_journal_id', '=', 'transaction_journals.id')
                    ->where('t_dest.amount', '>', 0);
            })
            ->where('journal_meta.name', '=', 'external_id')
            ->whereIn('journal_meta.data', $jsonEncodedIds)
            ->whereNull('journal_meta.deleted_at')
            ->whereNull('transaction_journals.deleted_at')
            ->whereNull('t_source.deleted_at')
            ->whereNull('t_dest.deleted_at')
            ->where('transaction_journals.user_group_id', '=', $this->userGroup->id)
        ;

        // Optional date range filter.
        if (null !== $startDate) {
            $query->where('transaction_journals.date', '>=', $startDate);
        }
        if (null !== $endDate) {
            $query->where('transaction_journals.date', '<=', $endDate);
        }

        // Optional import source filter.
        $sourceName = $params['source'] ?? null;
        if (null !== $sourceName && '' !== $sourceName) {
            $sourceRecord = ImportSource::where('user_group_id', $this->userGroup->id)
                ->where('name', strtolower(trim($sourceName)))
                ->first();
            if (null !== $sourceRecord) {
                $query->where('transaction_journals.import_source_id', '=', $sourceRecord->id);
            } else {
                // Source doesn't exist — no transactions can match.
                $resultMap = [];
                foreach ($externalIds as $id) {
                    $resultMap[$id] = null;
                }

                return response()->json(['results' => $resultMap]);
            }
        }

        $rows = $query->select([
            'journal_meta.data as meta_data',
            'transaction_journals.transaction_group_id',
            'transaction_journals.description',
            't_dest.amount',
            't_source.account_id as source_account_id',
            't_dest.account_id as destination_account_id',
        ])->get();

        // Build the result map: external_id => summary or null.
        // Decode the JSON wrapper from meta_data to get the original external_id string.
        $found   = [];
        foreach ($rows as $row) {
            $originalId          = json_decode((string) $row->meta_data, true);
            $found[$originalId]  = [
                'transaction_group_id'   => (int) $row->transaction_group_id,
                'description'            => $row->description,
                'amount'                 => $row->amount,
                'source_account_id'      => (int) $row->source_account_id,
                'destination_account_id' => (int) $row->destination_account_id,
            ];
        }

        // Fill nulls for IDs that were not found.
        $resultMap = [];
        foreach ($externalIds as $id) {
            $resultMap[$id] = $found[$id] ?? null;
        }

        Log::debug(sprintf('ExternalIdController: searched %d IDs, found %d.', count($externalIds), count($found)));

        return response()->json(['results' => $resultMap]);
    }
}

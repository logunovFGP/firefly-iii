<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Data\Bulk;

use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Api\V1\Requests\Data\Bulk\CategorizeRequest;
use FireflyIII\Enums\UserRoleEnum;
use FireflyIII\Events\Model\TransactionGroup\TransactionGroupEventFlags;
use FireflyIII\Events\Model\TransactionGroup\TransactionGroupEventObjects;
use FireflyIII\Events\Model\TransactionGroup\UpdatedSingleTransactionGroup;
use FireflyIII\Services\Internal\Update\JournalUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkCategorizeController extends Controller
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

    public function categorize(CategorizeRequest $request): JsonResponse
    {
        $items   = $request->getTransactions();
        $applied = 0;
        $failed  = 0;
        $errors  = [];
        $objects = new TransactionGroupEventObjects();

        // Phase 1: Optimistic bulk transaction — try all at once
        try {
            DB::transaction(function () use ($items, &$applied, &$objects): void {
                foreach ($items as $item) {
                    $this->processItem($item, $objects);
                    ++$applied;
                }
            });
        } catch (\Throwable $e) {
            // Phase 2: Bulk failed — fall back to per-item processing
            Log::warning(sprintf('Bulk categorize failed, falling back to per-item: %s', $e->getMessage()));
            $applied = 0;
            $objects = new TransactionGroupEventObjects();

            foreach ($items as $item) {
                try {
                    DB::transaction(function () use ($item, &$objects): void {
                        $this->processItem($item, $objects);
                    });
                    ++$applied;
                } catch (\Throwable $itemError) {
                    ++$failed;
                    $errors[] = sprintf(
                        'transaction_group_id %d: %s',
                        $item['transaction_group_id'],
                        $itemError->getMessage()
                    );
                    Log::error(sprintf(
                        'Bulk categorize item %d failed: %s',
                        $item['transaction_group_id'],
                        $itemError->getMessage()
                    ));
                }
            }
        }

        // Fire a single batch event for all successfully updated groups
        if ($applied > 0) {
            $flags = new TransactionGroupEventFlags();
            event(new UpdatedSingleTransactionGroup($flags, $objects));
        }

        return response()->json([
            'applied' => $applied,
            'failed'  => $failed,
            'errors'  => $errors,
        ]);
    }

    /**
     * Process a single item within a DB transaction.
     *
     * @param array{transaction_group_id: int, category_name: string, tag: string|null} $item
     */
    private function processItem(array $item, TransactionGroupEventObjects $objects): void
    {
        // User-scoped lookup — prevents authorization bypass
        $group = $this->userGroup->transactionGroups()->find($item['transaction_group_id']);
        if (null === $group) {
            throw new \RuntimeException(sprintf('Transaction group %d not found', $item['transaction_group_id']));
        }

        foreach ($group->transactionJournals as $journal) {
            // Build combined data for single JournalUpdateService call
            $data = ['category_name' => $item['category_name']];

            // Tag append: fetch existing, merge, deduplicate
            if (null !== $item['tag'] && '' !== $item['tag']) {
                $existingTags = $journal->tags->pluck('tag')->toArray();
                $data['tags'] = array_values(array_unique(array_merge($existingTags, [$item['tag']])));
            }

            /** @var JournalUpdateService $service */
            $service = app(JournalUpdateService::class);
            $service->setTransactionJournal($journal);
            $service->setData($data);
            $service->update();
            $journal->refresh();
        }

        $objects->appendFromTransactionGroup($group);
    }
}

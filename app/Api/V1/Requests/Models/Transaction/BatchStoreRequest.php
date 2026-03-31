<?php

/*
 * BatchStoreRequest.php
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

namespace FireflyIII\Api\V1\Requests\Models\Transaction;

use FireflyIII\Rules\IsBoolean;
use FireflyIII\Rules\IsDateOrTime;
use FireflyIII\Rules\IsValidPositiveAmount;
use FireflyIII\Rules\IsValidZeroOrMoreAmount;
use FireflyIII\Support\NullArrayObject;
use FireflyIII\Support\Request\ChecksLogin;
use FireflyIII\Support\Request\ConvertsDataTypes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class BatchStoreRequest
 *
 * Light structural validation for batch transaction creation.
 * Per-item semantic validation (accounts, currencies, duplicates) is deferred
 * to the controller loop so one bad item does not reject the entire batch.
 */
class BatchStoreRequest extends FormRequest
{
    use ChecksLogin;
    use ConvertsDataTypes;

    /**
     * Extract all batch items into a controller-ready format.
     *
     * @return array{items: array<int, array>}
     */
    public function getAll(): array
    {
        $items = [];
        foreach ($this->get('batch') as $index => $entry) {
            $object = new NullArrayObject($entry);
            $item   = [
                'group_title'             => $this->clearString($object['group_title'] ?? ''),
                'error_if_duplicate_hash' => $this->convertBoolean((string) ($object['error_if_duplicate_hash'] ?? 'false')),
                'apply_rules'             => $this->convertBoolean((string) ($object['apply_rules'] ?? 'false')),
                'fire_webhooks'           => $this->convertBoolean((string) ($object['fire_webhooks'] ?? 'false')),
                'transactions'            => $this->getTransactionData($object['transactions'] ?? []),
            ];
            $items[] = $item;
        }

        return ['items' => $items];
    }

    /**
     * Structural validation rules only.
     */
    public function rules(): array
    {
        return [
            'batch'                                => 'required|array|max:100',
            'batch.*.transactions'                 => 'required|array|min:1',
            'batch.*.error_if_duplicate_hash'      => ['nullable', new IsBoolean()],
            'batch.*.apply_rules'                  => ['nullable', new IsBoolean()],
            'batch.*.fire_webhooks'                => ['nullable', new IsBoolean()],
            'batch.*.group_title'                  => 'min:1|max:1000|nullable',

            // Per-split structural validation (light):
            'batch.*.transactions.*.type'          => 'required|in:withdrawal,deposit,transfer,opening-balance,reconciliation',
            'batch.*.transactions.*.date'          => ['required', new IsDateOrTime()],
            'batch.*.transactions.*.amount'        => ['required', new IsValidPositiveAmount()],
            'batch.*.transactions.*.description'   => 'nullable|min:1|max:1000',
            'batch.*.transactions.*.foreign_amount' => ['nullable', new IsValidZeroOrMoreAmount()],
        ];
    }

    /**
     * Parse transaction split data from a single batch item.
     *
     * Follows the same field extraction pattern as StoreRequest::getTransactionData()
     * to ensure compatibility with the repository store method.
     *
     * @return array<int, array>
     */
    private function getTransactionData(mixed $transactions): array
    {
        $return = [];
        if (!is_array($transactions)) {
            return $return;
        }

        foreach ($transactions as $transaction) {
            $object   = new NullArrayObject($transaction);
            $return[] = [
                'type'                  => $this->clearString($object['type']),
                'date'                  => $this->dateFromValue($object['date']),
                'order'                 => $this->integerFromValue((string) $object['order']),

                'currency_id'           => $this->integerFromValue((string) $object['currency_id']),
                'currency_code'         => $this->clearString((string) $object['currency_code']),

                'foreign_currency_id'   => $this->integerFromValue((string) $object['foreign_currency_id']),
                'foreign_currency_code' => $this->clearString((string) $object['foreign_currency_code']),

                'amount'                => $this->clearString((string) $object['amount']),
                'foreign_amount'        => $this->clearString((string) $object['foreign_amount']),

                'description'           => $this->clearString($object['description']),

                'source_id'             => $this->integerFromValue((string) $object['source_id']),
                'source_name'           => $this->clearString((string) $object['source_name']),
                'source_iban'           => $this->clearIban((string) $object['source_iban']),
                'source_number'         => $this->clearString((string) $object['source_number']),
                'source_bic'            => $this->clearString((string) $object['source_bic']),

                'destination_id'        => $this->integerFromValue((string) $object['destination_id']),
                'destination_name'      => $this->clearString((string) $object['destination_name']),
                'destination_iban'      => $this->clearIban((string) $object['destination_iban']),
                'destination_number'    => $this->clearString((string) $object['destination_number']),
                'destination_bic'       => $this->clearString((string) $object['destination_bic']),

                'budget_id'             => $this->integerFromValue((string) $object['budget_id']),
                'budget_name'           => $this->clearString((string) $object['budget_name']),

                'category_id'           => $this->integerFromValue((string) $object['category_id']),
                'category_name'         => $this->clearString((string) $object['category_name']),

                'bill_id'               => $this->integerFromValue((string) $object['bill_id']),
                'bill_name'             => $this->clearString((string) $object['bill_name']),

                'piggy_bank_id'         => $this->integerFromValue((string) $object['piggy_bank_id']),
                'piggy_bank_name'       => $this->clearString((string) $object['piggy_bank_name']),

                'reconciled'            => $this->convertBoolean((string) $object['reconciled']),
                'notes'                 => $this->clearStringKeepNewlines((string) $object['notes']),
                'tags'                  => $this->arrayFromValue($object['tags']),

                'internal_reference'    => $this->clearString((string) $object['internal_reference']),
                'external_id'           => $this->clearString((string) $object['external_id']),
                'import_source'         => $this->clearString((string) ($object['import_source'] ?? '')),
                'original_source'       => sprintf('ff3-v%s|batch', config('firefly.version')),
                'recurrence_id'         => $this->integerFromValue($object['recurrence_id']),
                'bunq_payment_id'       => $this->clearString((string) $object['bunq_payment_id']),
                'external_url'          => $this->clearString((string) $object['external_url']),

                'sepa_cc'               => $this->clearString((string) $object['sepa_cc']),
                'sepa_ct_op'            => $this->clearString((string) $object['sepa_ct_op']),
                'sepa_ct_id'            => $this->clearString((string) $object['sepa_ct_id']),
                'sepa_db'               => $this->clearString((string) $object['sepa_db']),
                'sepa_country'          => $this->clearString((string) $object['sepa_country']),
                'sepa_ep'               => $this->clearString((string) $object['sepa_ep']),
                'sepa_ci'               => $this->clearString((string) $object['sepa_ci']),
                'sepa_batch_id'         => $this->clearString((string) $object['sepa_batch_id']),

                'interest_date'         => $this->dateFromValue($object['interest_date']),
                'book_date'             => $this->dateFromValue($object['book_date']),
                'process_date'          => $this->dateFromValue($object['process_date']),
                'due_date'              => $this->dateFromValue($object['due_date']),
                'payment_date'          => $this->dateFromValue($object['payment_date']),
                'invoice_date'          => $this->dateFromValue($object['invoice_date']),
            ];
        }

        return $return;
    }
}

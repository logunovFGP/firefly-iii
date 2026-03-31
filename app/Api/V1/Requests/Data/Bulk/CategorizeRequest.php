<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Requests\Data\Bulk;

use FireflyIII\Support\Request\ChecksLogin;
use FireflyIII\Support\Request\ConvertsDataTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class CategorizeRequest extends FormRequest
{
    use ChecksLogin;
    use ConvertsDataTypes;

    public function rules(): array
    {
        return [
            'transactions'                        => ['required', 'array', 'min:1', 'max:100'],
            'transactions.*.transaction_group_id'  => ['required', 'integer'],
            'transactions.*.category_name'         => ['required', 'string', 'max:255'],
            'transactions.*.tag'                   => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, array{transaction_group_id: int, category_name: string, tag: string|null}>
     */
    public function getTransactions(): array
    {
        $items = $this->validated('transactions');

        return array_map(static function (array $item): array {
            return [
                'transaction_group_id' => (int) $item['transaction_group_id'],
                'category_name'        => (string) $item['category_name'],
                'tag'                  => $item['tag'] ?? null,
            ];
        }, $items);
    }

    public function withValidator($validator): void
    {
        if ($validator->fails()) {
            Log::channel('audit')->error(sprintf('Validation errors in %s', self::class), $validator->errors()->toArray());
        }
    }
}

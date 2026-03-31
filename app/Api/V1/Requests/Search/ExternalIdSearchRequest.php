<?php

/*
 * ExternalIdSearchRequest.php
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

namespace FireflyIII\Api\V1\Requests\Search;

use FireflyIII\Support\Request\ChecksLogin;
use FireflyIII\Support\Request\ConvertsDataTypes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class ExternalIdSearchRequest
 *
 * Validates a batch lookup of external_ids against journal_meta.
 */
class ExternalIdSearchRequest extends FormRequest
{
    use ChecksLogin;
    use ConvertsDataTypes;

    /**
     * Validation rules for the external ID search.
     */
    public function rules(): array
    {
        return [
            'external_ids'   => 'required|array|min:1|max:500',
            'external_ids.*' => 'required|string|min:1|max:255',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'source'         => 'nullable|string|min:1|max:64',
        ];
    }

    /**
     * Extract validated input for the controller.
     *
     * @return array{external_ids: array<int, string>, start_date: ?string, end_date: ?string, source: ?string}
     */
    public function getSearchParameters(): array
    {
        return [
            'external_ids' => $this->validated('external_ids'),
            'start_date'   => $this->validated('start_date'),
            'end_date'     => $this->validated('end_date'),
            'source'       => $this->validated('source'),
        ];
    }
}

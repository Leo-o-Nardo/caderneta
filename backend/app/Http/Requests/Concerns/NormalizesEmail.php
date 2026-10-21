<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;

trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }
}

<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    /**
     * Always treat API form requests as JSON requests.
     */
    public function expectsJson(): bool
    {
        return true;
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(config('lab_events.types'))],
            'payload' => ['required', 'array'],
            'metadata' => ['required', 'array'],
            'metadata.correlation_id' => ['required', 'uuid'],
            'metadata.timestamp' => ['required', 'date'],
        ];
    }

    /**
     * @return array{type: string, payload: array<string, mixed>, metadata: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $this->validatePayloadShape($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validatePayloadShape(array $data): void
    {
        $type = $data['type'];

        $rules = match ($type) {
            'ORDER_CONFIRMED' => [
                'payload.order_id' => ['required', 'string', 'max:128'],
                'payload.user_email' => ['nullable', 'email'],
            ],
            'USER_REGISTERED' => [
                'payload.user_id' => ['required', 'string', 'max:128'],
                'payload.email' => ['required', 'email'],
            ],
            'PASSWORD_RESET' => [
                'payload.user_id' => ['required', 'string', 'max:128'],
                'payload.email' => ['required', 'email'],
            ],
            default => [],
        };

        if ($rules === []) {
            return;
        }

        Validator::make($data, $rules)->validate();
    }
}

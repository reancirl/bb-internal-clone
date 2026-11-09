<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            'email_notifications' => ['sometimes', 'boolean'],

            // International (E.164) format so any SMS provider can use it as-is.
            'phone' => [
                Rule::requiredIf(fn () => $this->boolean('sms_notifications')),
                'nullable',
                'string',
                'regex:/^\+[1-9]\d{7,14}$/',
            ],

            'sms_notifications' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Use international format with the country code, e.g. +639171234567.',
            'phone.required' => 'Add a mobile number to turn on text messages.',
        ];
    }

    /**
     * Accept "+63 917-123-4567" or "+63 (917) 123 4567" and store "+639171234567".
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $phone = preg_replace('/[\s().-]/', '', (string) $this->input('phone'));
            $this->merge(['phone' => $phone === '' ? null : $phone]);
        }
    }
}

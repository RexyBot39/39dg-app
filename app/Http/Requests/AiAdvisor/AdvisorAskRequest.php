<?php

namespace App\Http\Requests\AiAdvisor;

use Illuminate\Foundation\Http\FormRequest;

class AdvisorAskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — rate limiting is handled via middleware
    }

    public function rules(): array
    {
        return [
            'site'             => ['sometimes', 'string', 'max:50'],
            'page_context'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'question'         => ['required', 'string', 'min:2', 'max:500'],
            'brand'            => ['sometimes', 'string', 'in:39dg,ocusafe,ocusleep,onlinecontacts'],
            'selected_filters' => ['sometimes', 'nullable', 'array'],
            'selected_filters.frame_shape'          => ['sometimes', 'string', 'in:round,oval,rectangular,cat-eye,aviator,wayfarer,rimless,semi-rimless'],
            'selected_filters.frame_material'       => ['sometimes', 'string', 'in:titanium,acetate,metal,tr90,mixed'],
            'selected_filters.frame_size_category'  => ['sometimes', 'string', 'in:small,medium,large,x-large'],
            'selected_filters.lightweight'          => ['sometimes', 'boolean'],
            'selected_filters.progressive_friendly' => ['sometimes', 'boolean'],
            'selected_filters.strong_rx_friendly'   => ['sometimes', 'boolean'],
            'selected_filters.budget_tier'          => ['sometimes', 'string', 'in:budget,mid,premium'],
            'selected_filters.gender'               => ['sometimes', 'string', 'in:male,female,unisex'],
            'session_id'       => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'Please enter a question.',
            'question.max'      => 'Questions must be 500 characters or less.',
        ];
    }
}

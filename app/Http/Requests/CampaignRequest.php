<?php

declare(strict_types = 1);

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        $step = $this->input('step');

        if (! in_array($step, [1, 2, 3])) {
            return [
                'step' => ['required', Rule::in([1, 2, 3])],
            ];
        }

        if ($step == 1) {
            $rules = [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('campaigns', 'name'),
                ],
                'subject'       => ['required', 'string', 'max:255'],
                'email_list_id' => [
                    'required',
                    'integer',
                    'exists:email_lists,id',
                ],
                'template_id' => [
                    'required',
                    'integer',
                    'exists:templates,id',
                ],
                'track_click' => [
                    'required', 'boolean',
                ],
                'track_open' => [
                    'required', 'boolean',
                ],
            ];
        }

        if ($step == 2) {
            $rules = [
                'body' => ['required'],
            ];
        }

        if ($step == 3) {
            $rules = [
                'name' => [
                    'required',
                    'string', 'max:255',
                    Rule::unique('campaigns', 'name'),
                ],
                'subject'       => ['required', 'string', 'max:255'],
                'email_list_id' => [
                    'required',
                    'integer',
                    'exists:email_lists,id',
                ],
                'template_id' => [
                    'required',
                    'integer',
                    'exists:templates,id',
                ],
                'track_click' => ['required', 'boolean'],
                'track_open'  => ['required', 'boolean'],
                'body'        => ['required'],
            ];

            if ($this->input('draft_mode') === false || $this->input('draft_mode') === 'false') {
                $rules['send_at'] = [
                    'required',
                    'date',
                    'after_or_equal:' . Carbon::now('America/Sao_Paulo')->toDateTimeString(),
                ];
                $rules['customize_send_at'] = ['required', 'boolean'];
            }
        }

        return $rules;
    }
}

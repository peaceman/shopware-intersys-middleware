<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManufacturerSizeMappingStore extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $gender = $this->input('gender');

        $uniqueRule = Rule::unique('manufacturer_size_mappings', 'source_size')
            ->where('manufacturer_id', $this->route('manufacturer')->id)
            ->whereNull('deleted_at');

        if (!empty($gender)) $uniqueRule->where('gender', $gender);

        return [
            'gender' => ['required', 'string'],
            'source_size' => [
                'required',
                'string',
                $uniqueRule,
            ],
            'target_size' => ['required', 'string']
        ];
    }
}

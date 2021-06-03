<?php

namespace App\Http\Requests;

use App\Script;
use Illuminate\Foundation\Http\FormRequest;

class ScriptUpdateRequest extends FormRequest
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
        $active     = Script::STATUS_ACTIVE;
        $inactive   = Script::STATUS_INACTIVE;

        return [
            'update.name'                       => 'string',
            'update.description'                => 'string|nullable',
            'update.status'                     => "in:{$active},{$inactive}",
            'update.tags'                       => 'array',
            'update.type'                       => 'in:private,public',
            'update.users'                      => 'array',
            'update.aws_custom_script'          => 'nullable|string',
            'update.aws_custom_package_json'    => 'nullable|json'
        ];
    }

    /**
     * Custom message for validation
     *
     * @return array
     */
    public function messages()
    {
        return [
            'update.aws_custom_package_json.unique' => 'The package.json must be a valid JSON string.',
        ];
    }
}

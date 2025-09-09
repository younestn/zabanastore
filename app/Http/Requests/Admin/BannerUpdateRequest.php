<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property int $id
 * @property string $url
 * @property int $status
 */
class BannerUpdateRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => 'required_if:resource_type,custom|nullable|url',
        ];
    }

    public function messages(): array
    {
        return [
            'url.required_if' => translate('the_url_field_is_required'),
        ];
    }

}

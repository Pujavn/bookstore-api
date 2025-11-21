<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BooksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'         => 'sometimes|array',
            'ids.*'       => 'integer|min:1',

            'language'    => 'sometimes|array',
            'language.*'  => 'string|size:2|alpha',

            'mime_type'   => 'sometimes|array',
            'mime_type.*' => 'string|regex:#^[\w-]+/?$#',

            'topic'       => 'sometimes|array',
            'topic.*'     => 'string|max:100',

            'author'      => 'sometimes|array',
            'author.*'    => 'string|max:150',

            'title'       => 'sometimes|array',
            'title.*'     => 'string|max:200',

            'page_size'   => 'sometimes|integer|min:1|max:25',
            'page'      => 'sometimes|integer|min:1',
        ];
    }

    protected function prepareForValidation()
    {
        // Default page_size
        $this->merge(['page_size' => $this->page_size ?? 25]);

        $fields = ['ids', 'language', 'mime_type', 'topic', 'author', 'title'];

        foreach ($fields as $field) {
            if (!$this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            // If it's already an array (from Vue params serializer) → use as-is
            if (is_array($value)) {
                $this->merge([$field => $value]);
                continue;
            }

            // If it's a string
            if (is_string($value)) {
                // Split by comma ONLY if comma exists
                $values = str_contains($value, ',')
                    ? array_filter(array_map('trim', explode(',', $value)))
                    : [trim($value)];

                $values = array_values($values);

                // Convert IDs to integers
                if ($field === 'ids') {
                    $values = array_map('intval', $values);
                }

                $this->merge([$field => $values]);
            }
        }
    }
}

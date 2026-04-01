<?php

namespace App\Http\Requests;

use App\Services\GoogleDocsService;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'google_doc_url' => ['required', 'url', 'regex:#/document/d/[a-zA-Z0-9_-]+#'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'poster_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'google_doc_url.regex' => 'Valid Google Docs URL required.',
        ];
    }

    public function docId(): ?string
    {
        return GoogleDocsService::extractDocId($this->input('google_doc_url'));
    }
}

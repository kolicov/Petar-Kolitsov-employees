<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class UploadCsvRequest extends FormRequest
{
    public const int MAX_KILOBYTES = 20 * 1024;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'bail',
                'required',
                'file',
                $this->notEmpty(...),
                'extensions:csv,txt',
                'mimes:csv,txt',
                'max:'.self::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a CSV file to upload.',
            'file.file' => 'The file could not be uploaded. Please try again.',
            'file.uploaded' => sprintf(
                'The file could not be uploaded. It may be larger than the server allows (%s).',
                ini_get('upload_max_filesize'),
            ),
            'file.extensions' => 'The file must be a .csv or .txt file.',
            'file.mimes' => 'The file must be a plain-text CSV file.',
            'file.max' => 'The file may not be larger than 20 MB.',
        ];
    }

    private function notEmpty(string $attribute, UploadedFile $file, Closure $fail): void
    {
        if ($file->getSize() === 0) {
            $fail('The selected file is empty.');
        }
    }
}

<?php

namespace App\Modules\Signatures\Http\Requests\Api;

use App\Modules\Signatures\Models\Document;
use App\Modules\Signatures\Models\DocumentSigner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreDocumentApiRequest extends FormRequest
{
    /** Mesmo limite do upload pelo painel (25 MB). */
    private const MAX_BYTES = 25 * 1024 * 1024;

    private ?UploadedFile $decodedPdf = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required_without:file_base64|file|mimetypes:application/pdf|max:25600',
            'file_base64' => 'required_without:file|string',
            'name' => 'required_with:file_base64|nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
            'sign_order' => 'nullable|in:'.Document::SIGN_ORDER_PARALLEL.','.Document::SIGN_ORDER_SEQUENTIAL,
            'expires_at' => 'nullable|date|after:now',
            'external_id' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'send' => 'nullable|boolean',
            'signers' => 'nullable|array|max:50',
            'signers.*.name' => 'required|string|max:255',
            'signers.*.role' => 'nullable|string|max:60',
            'signers.*.email' => 'required|email|max:255|distinct:ignore_case',
            'signers.*.cpf' => 'nullable|string|max:14',
            'signers.*.cnpj' => 'nullable|string|max:18',
            'signers.*.phone' => 'nullable|string|max:32',
            'signers.*.auth_method' => 'nullable|in:'.implode(',', [
                DocumentSigner::AUTH_EMAIL,
                DocumentSigner::AUTH_SMS,
                DocumentSigner::AUTH_EMAIL_AND_SMS,
            ]),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->hasFile('file') || ! is_string($this->input('file_base64'))) {
                    return;
                }

                $binary = base64_decode(preg_replace('#^data:application/pdf;base64,#', '', $this->input('file_base64')), true);

                if ($binary === false || ! str_starts_with($binary, '%PDF-')) {
                    $validator->errors()->add('file_base64', 'O conteúdo não é um PDF válido em base64.');
                } elseif (strlen($binary) > self::MAX_BYTES) {
                    $validator->errors()->add('file_base64', 'O PDF pode ter no máximo 25 MB.');
                } else {
                    $path = tempnam(sys_get_temp_dir(), 'mdhb_pdf_');
                    file_put_contents($path, $binary);
                    $this->decodedPdf = new UploadedFile($path, ($this->input('name') ?: 'documento').'.pdf', 'application/pdf', null, true);
                }
            },
        ];
    }

    public function pdf(): UploadedFile
    {
        return $this->file('file') ?? $this->decodedPdf;
    }

    public function attrs(): array
    {
        return $this->safe()->only(['name', 'message', 'sign_order', 'expires_at', 'external_id', 'metadata']);
    }

    /** @return array<int, array<string, mixed>> */
    public function signers(): array
    {
        return $this->validated('signers') ?? [];
    }

    public function shouldSend(): bool
    {
        return $this->boolean('send');
    }
}

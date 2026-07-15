<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'preheader',
        'preview_text',
        'template_type',
        'body',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function render(array $data = []): array
    {
        $payload = [
            'subject' => $this->subject,
            'preheader' => $this->preheader,
            'preview_text' => $this->preview_text,
            'body' => $this->body,
        ];

        foreach ($data as $key => $value) {
            $replacement = is_scalar($value) ? (string) $value : json_encode($value);

            foreach ($payload as $field => $content) {
                if (! is_string($content)) {
                    continue;
                }

                $payload[$field] = str_replace('{{' . $key . '}}', $replacement, $content);
            }
        }

        return $payload;
    }

    public static function getByName(string $name): ?self
    {
        return self::where('name', $name)->where('is_active', true)->first();
    }
}

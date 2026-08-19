<?php

namespace App\Services\Kommo\DTO;

final readonly class ContactData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $phone,
        public ?string $email,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromApi(array $raw): self
    {
        $phone = null;
        $email = null;

        foreach ($raw['custom_fields_values'] ?? [] as $field) {
            $code = $field['field_code'] ?? null;
            $value = $field['values'][0]['value'] ?? null;

            if ($code === 'PHONE' && $phone === null) {
                $phone = (string) $value;
            }

            if ($code === 'EMAIL' && $email === null) {
                $email = (string) $value;
            }
        }

        return new self(
            id: (int) $raw['id'],
            name: (string) ($raw['name'] ?? ''),
            phone: $phone,
            email: $email,
        );
    }
}

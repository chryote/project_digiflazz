<?php

namespace App\DTOs\Auth;

readonly class LoginDTO
{
    public function __construct(
        public string $login,
        public string $password,
        public ?string $deviceName = 'API Client',
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            login: $validated['username'] ?? $validated['email'] ?? $validated['login'],
            password: $validated['password'],
            deviceName: $validated['device_name'] ?? 'API Client',
        );
    }
}

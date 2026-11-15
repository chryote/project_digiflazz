<?php

namespace App\Services\Digiflazz;

class DigiflazzSignatureService
{
    /**
     * Generate MD5 signature based on Digiflazz formula.
     */
    public function generate(string $username, string $apiKey, string $suffix): string
    {
        return md5($username . $apiKey . $suffix);
    }

    /**
     * Verify signature against customer_no or ref_id.
     */
    public function verify(string $username, string $apiKey, string $providedSign, array $possibleSuffixes): bool
    {
        foreach ($possibleSuffixes as $suffix) {
            if ($suffix && hash_equals($this->generate($username, $apiKey, (string) $suffix), strtolower($providedSign))) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace SymSvel\Module\Settings\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SettingSecretCipher
{
    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $kernelSecret)
    {
        if ($kernelSecret === '') {
            throw new \LogicException('APP_SECRET must be configured before encrypting settings.');
        }
        $this->key = sodium_crypto_generichash($kernelSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plainText): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce.sodium_crypto_secretbox($plainText, $nonce, $this->key));
    }

    public function decrypt(string $cipherText): string
    {
        $decoded = base64_decode($cipherText, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted setting.');
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plainText = sodium_crypto_secretbox_open(substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plainText === false) {
            throw new \RuntimeException('Unable to decrypt setting.');
        }
        return $plainText;
    }
}

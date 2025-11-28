<?php

namespace App\Service;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Algorithm\RS256;

class JwtService
{
    private JWK $privateKey;
    private JWK $publicKey;
    private AlgorithmManager $algorithmManager;
    private CompactSerializer $serializer;

    public function __construct(string $jwtPrivateKeyPath)
    {
        // Charger la clé privée (.pem)
        $this->privateKey = JWKFactory::createFromKeyFile(
            $jwtPrivateKeyPath,
            null,
            [
                'alg' => 'RS256',
                'use' => 'sig'
            ]
        );

        // Charger la clé publique (public.pem)
        $this->publicKey = JWKFactory::createFromKeyFile(
            dirname($jwtPrivateKeyPath) . '/public.pem'
        );

        // Manager d'algorithmes
        $this->algorithmManager = new AlgorithmManager([
            new RS256()
        ]);

        // Serializer compact (AAA.BBB.CCC)
        $this->serializer = new CompactSerializer();
    }

    public function generate(array $payload): string
    {
        $jwsBuilder = new JWSBuilder($this->algorithmManager);

        // Construire le JWS
        $jws = $jwsBuilder
            ->create()
            ->withPayload(JsonConverter::encode($payload))
            ->addSignature($this->privateKey, ['alg' => 'RS256'])
            ->build();

        // Convertir en JWT compact
        return $this->serializer->serialize($jws, 0);
    }

    public function getPublicKey(): JWK
    {
        return $this->publicKey;
    }
}

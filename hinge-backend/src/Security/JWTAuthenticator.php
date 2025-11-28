<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Service\JwtService;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JWTAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UserRepository $userRepository,
        private JwtService $jwtService
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationException('No Bearer token provided');
        }

        $token = substr($authHeader, 7);

        $serializer = new CompactSerializer();
        $jws = $serializer->unserialize($token);

        $algorithmManager = new AlgorithmManager([new RS256()]);
        $jwsVerifier = new JWSVerifier($algorithmManager);

        if (!$jwsVerifier->verifyWithKey($jws, $this->jwtService->getPublicKey(), 0)) {
            throw new AuthenticationException('Invalid token');
        }

        $payload = JsonConverter::decode($jws->getPayload());

        if (!isset($payload['user_id'])) {
            throw new AuthenticationException('Invalid token payload');
        }

        return new SelfValidatingPassport(
            new UserBadge($payload['user_id'], function($id) {
                return $this->userRepository->find($id);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?JsonResponse
    {
        return null; // laisser l'API continuer normalement
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?JsonResponse
    {
        return new JsonResponse(['error' => $exception->getMessage()], 401);
    }
}

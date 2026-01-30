<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\LoginRequest;
use App\Dto\RegisterRequest;
use App\Entity\User;
use App\Entity\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
#[OA\Tag(name: 'Authentification')]
final class RegisterController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    /**
     * Inscription d'un nouvel utilisateur.
     *
     * Crée un compte avec email et mot de passe, puis retourne un token JWT.
     */
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            ref: new Model(type: RegisterRequest::class),
            example: ['email' => 'user@example.com', 'password' => 'motdepasse']
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Utilisateur créé, token JWT retourné',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', description: 'Token JWT'),
                new OA\Property(
                    property: 'user',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    ],
                    type: 'object'
                ),
            ]
        )
    )]
    #[OA\Response(response: 422, description: 'Email déjà utilisé ou validation échouée')]
    public function register(
        #[MapRequestPayload] RegisterRequest $request,
    ): JsonResponse {
        if ($this->userRepository->findOneBy(['email' => $request->email])) {
            return new JsonResponse(
                ['message' => 'Un utilisateur avec cet email existe déjà.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user = new User();
        $user->setEmail($request->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));
        $user->setRole(UserRole::USER);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Connexion : authentification par email et mot de passe.
     *
     * Retourne uniquement le token JWT.
     */
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            ref: new Model(type: LoginRequest::class),
            example: ['email' => 'user@example.com', 'password' => 'motdepasse']
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Authentification réussie',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', description: 'Token JWT'),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Identifiants invalides')]
    public function login(
        #[MapRequestPayload] LoginRequest $request,
    ): JsonResponse {
        $user = $this->userRepository->findOneBy(['email' => $request->email]);
        if (null === $user || !$this->passwordHasher->isPasswordValid($user, $request->password)) {
            return new JsonResponse(
                ['message' => 'Identifiants invalides.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $token = $this->jwtManager->create($user);

        return new JsonResponse(['token' => $token]);
    }
}

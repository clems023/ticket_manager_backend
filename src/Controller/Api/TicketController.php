<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CreateTicketRequest;
use App\Dto\UpdateTicketRequest;
use App\Entity\Ticket;
use App\Entity\TicketPriority;
use App\Entity\TicketStatus;
use App\Entity\User;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
#[OA\Tag(name: 'Tickets')]
final class TicketController extends AbstractController
{
    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Liste paginée des tickets avec filtres (status, priority) et tri.
     *
     */
    #[Route('/tickets', name: 'api_tickets_list', methods: ['GET'])]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Filtrer par statut', schema: new OA\Schema(type: 'string', enum: ['OPEN', 'IN_PROGRESS', 'DONE']))]
    #[OA\Parameter(name: 'priority', in: 'query', description: 'Filtrer par priorité', schema: new OA\Schema(type: 'string', enum: ['LOW', 'MEDIUM', 'HIGH']))]
    #[OA\Parameter(name: 'sort', in: 'query', description: 'Champ de tri', schema: new OA\Schema(type: 'string', enum: ['createdAt', 'priority'], default: 'createdAt'))]
    #[OA\Parameter(name: 'order', in: 'query', description: 'Ordre de tri', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc'))]
    #[OA\Parameter(name: 'page', in: 'query', description: 'Numéro de page', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Nombre d\'éléments par page (max 100)', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Response(
        response: 200,
        description: 'Liste paginée des tickets',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'description', type: 'string'),
                        new OA\Property(property: 'status', type: 'string'),
                        new OA\Property(property: 'priority', type: 'string'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'createdBy', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'email', type: 'string'),
                        ]),
                    ],
                    type: 'object'
                )),
                new OA\Property(property: 'meta', properties: [
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'page', type: 'integer'),
                    new OA\Property(property: 'limit', type: 'integer'),
                    new OA\Property(property: 'totalPages', type: 'integer'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    public function list(
        #[MapQueryParameter] ?string $status = null,
        #[MapQueryParameter] ?string $priority = null,
        #[MapQueryParameter] string $sort = 'createdAt',
        #[MapQueryParameter] string $order = 'desc',
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 20,
    ): JsonResponse {
        $statusEnum = null;
        if (null !== $status && $status !== '') {
            $statusEnum = TicketStatus::tryFrom($status);
            if (null === $statusEnum) {
                return new JsonResponse(
                    ['message' => 'Valeur de status invalide. Valeurs acceptées : OPEN, IN_PROGRESS, DONE.'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        $priorityEnum = null;
        if (null !== $priority && $priority !== '') {
            $priorityEnum = TicketPriority::tryFrom($priority);
            if (null === $priorityEnum) {
                return new JsonResponse(
                    ['message' => 'Valeur de priority invalide. Valeurs acceptées : LOW, MEDIUM, HIGH.'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        [$tickets, $total] = $this->ticketRepository->findPaginated(
            $statusEnum,
            $priorityEnum,
            $sort,
            $order,
            $page,
            $limit,
        );

        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;

        $data = array_map(
            fn(Ticket $t) => [
                'id' => $t->getId(),
                'title' => $t->getTitle(),
                'description' => $t->getDescription(),
                'status' => $t->getStatus()->value,
                'priority' => $t->getPriority()->value,
                'createdAt' => $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $t->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                'createdBy' => [
                    'id' => $t->getCreatedBy()->getId(),
                    'email' => $t->getCreatedBy()->getEmail(),
                ],
            ],
            $tickets,
        );

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    /**
     * Récupérer un ticket par son ID.
     *
     */
    #[Route('/tickets/{id}', name: 'api_tickets_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID du ticket', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Détail du ticket',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'priority', type: 'string'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
                new OA\Property(property: 'createdBy', type: 'object', properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'email', type: 'string'),
                ]),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Ticket non trouvé')]
    public function show(int $id): JsonResponse
    {
        $ticket = $this->ticketRepository->find($id);
        if (null === $ticket) {
            return new JsonResponse(
                ['message' => 'Ticket non trouvé.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return new JsonResponse([
            'id' => $ticket->getId(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'status' => $ticket->getStatus()->value,
            'priority' => $ticket->getPriority()->value,
            'createdAt' => $ticket->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $ticket->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'createdBy' => [
                'id' => $ticket->getCreatedBy()->getId(),
                'email' => $ticket->getCreatedBy()->getEmail(),
            ],
        ]);
    }

    /**
     * Modifier un ticket (mise à jour partielle).
     */
    #[Route('/tickets/{id}', name: 'api_tickets_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID du ticket', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            ref: new Model(type: UpdateTicketRequest::class),
            example: [
                'title' => 'Titre modifié',
                'description' => 'Description modifiée',
                'status' => 'IN_PROGRESS',
                'priority' => 'HIGH',
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Ticket modifié',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'priority', type: 'string'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
                new OA\Property(property: 'createdBy', type: 'object', properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'email', type: 'string'),
                ]),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 400, description: 'Données invalides (titre trop court, status/priority non autorisés)')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Ticket non trouvé')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateTicketRequest $request,
    ): JsonResponse {
        $ticket = $this->ticketRepository->find($id);
        if (null === $ticket) {
            return new JsonResponse(
                ['message' => 'Ticket non trouvé.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        if (null !== $request->title && $request->title !== '') {
            $ticket->setTitle($request->title);
        }

        if (null !== $request->description) {
            $ticket->setDescription($request->description);
        }

        if (null !== $request->status && $request->status !== '') {
            $status = TicketStatus::tryFrom($request->status);
            if (null === $status) {
                return new JsonResponse(
                    ['message' => 'Valeur de status invalide. Valeurs acceptées : OPEN, IN_PROGRESS, DONE.'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
            $ticket->setStatus($status);
        }

        if (null !== $request->priority && $request->priority !== '') {
            $priority = TicketPriority::tryFrom($request->priority);
            if (null === $priority) {
                return new JsonResponse(
                    ['message' => 'Valeur de priority invalide. Valeurs acceptées : LOW, MEDIUM, HIGH.'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
            $ticket->setPriority($priority);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $ticket->getId(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'status' => $ticket->getStatus()->value,
            'priority' => $ticket->getPriority()->value,
            'createdAt' => $ticket->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $ticket->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'createdBy' => [
                'id' => $ticket->getCreatedBy()->getId(),
                'email' => $ticket->getCreatedBy()->getEmail(),
            ],
        ]);
    }

    /**
     * Supprimer un ticket.
     *
     * Seul le créateur du ticket ou un administrateur (ROLE_ADMIN) peut supprimer.
     */
    #[Route('/tickets/{id}', name: 'api_tickets_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID du ticket', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Ticket supprimé')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 403, description: 'Non autorisé (seul le créateur ou un admin peut supprimer)')]
    #[OA\Response(response: 404, description: 'Ticket non trouvé')]
    public function delete(int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(
                ['message' => 'Authentification requise.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $ticket = $this->ticketRepository->find($id);
        if (null === $ticket) {
            return new JsonResponse(
                ['message' => 'Ticket non trouvé.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        $isCreator = $ticket->getCreatedBy()->getId() === $user->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        if (!$isCreator && !$isAdmin) {
            return new JsonResponse(
                ['message' => 'Seul le créateur du ticket ou un administrateur peut le supprimer.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        $this->entityManager->remove($ticket);
        $this->entityManager->flush();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Créer un ticket rattaché à l'utilisateur connecté.
     *
     */
    #[Route('/tickets', name: 'api_tickets_create', methods: ['POST'])]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            ref: new Model(type: CreateTicketRequest::class),
            example: [
                'title' => 'Mon ticket',
                'description' => 'Description optionnelle',
                'status' => 'OPEN',
                'priority' => 'MEDIUM',
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Ticket créé',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'priority', type: 'string'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
                new OA\Property(property: 'createdBy', type: 'object', properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'email', type: 'string'),
                ]),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(response: 400, description: 'Données invalides (titre trop court, status/priority non autorisés)')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    public function create(
        #[MapRequestPayload] CreateTicketRequest $request,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(
                ['message' => 'Authentification requise.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $status = TicketStatus::OPEN;
        if (null !== $request->status && $request->status !== '') {
            $status = TicketStatus::tryFrom($request->status);
            if (null === $status) {
                return new JsonResponse(
                    ['message' => 'Valeur de status invalide. Valeurs acceptées : OPEN, IN_PROGRESS, DONE.'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        $priority = TicketPriority::MEDIUM;
        if (null !== $request->priority && $request->priority !== '') {
            $priority = TicketPriority::tryFrom($request->priority);
            if (null === $priority) {
                return new JsonResponse(
                    ['message' => 'Valeur de priority invalide. Valeurs acceptées : LOW, MEDIUM, HIGH.'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        $ticket = new Ticket();
        $ticket->setTitle($request->title);
        $ticket->setDescription($request->description);
        $ticket->setStatus($status);
        $ticket->setPriority($priority);
        $ticket->setCreatedBy($user);

        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $ticket->getId(),
            'title' => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'status' => $ticket->getStatus()->value,
            'priority' => $ticket->getPriority()->value,
            'createdAt' => $ticket->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $ticket->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'createdBy' => [
                'id' => $ticket->getCreatedBy()->getId(),
                'email' => $ticket->getCreatedBy()->getEmail(),
            ],
        ], Response::HTTP_CREATED);
    }
}

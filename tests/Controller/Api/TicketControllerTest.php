<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TicketControllerTest extends WebTestCase
{
    private const API_TICKETS = '/api/tickets';

    public function testCreateTicketSuccess(): void
    {
        $client = static::createClient();
        $this->ensureSchemaCreated($client);
        $token = $this->getAuthenticatedToken($client);

        $client->request(
            Request::METHOD_POST,
            self::API_TICKETS,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'title' => 'Mon ticket de test',
                'description' => 'Description du ticket',
                'status' => 'OPEN',
                'priority' => 'HIGH',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $data = $this->getResponseData($client);
        self::assertArrayHasKey('id', $data);
        self::assertIsInt($data['id']);
        self::assertSame('Mon ticket de test', $data['title']);
        self::assertSame('Description du ticket', $data['description']);
        self::assertSame('OPEN', $data['status']);
        self::assertSame('HIGH', $data['priority']);
        self::assertArrayHasKey('createdAt', $data);
        self::assertArrayHasKey('updatedAt', $data);
        self::assertArrayHasKey('createdBy', $data);
        self::assertSame('test-ticket@example.com', $data['createdBy']['email']);
    }

    public function testCreateTicketWithoutAuthReturns401(): void
    {
        $client = static::createClient();

        $client->request(
            Request::METHOD_POST,
            self::API_TICKETS,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'title' => 'Ticket sans auth',
                'description' => '',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $data = $this->getResponseData($client);
        self::assertArrayHasKey('message', $data);
        self::assertNotEmpty($data['message']);
    }

    public function testCreateTicketWithTitleTooShortReturns400(): void
    {
        $client = static::createClient();
        $this->ensureSchemaCreated($client);
        $token = $this->getAuthenticatedToken($client);

        $client->request(
            Request::METHOD_POST,
            self::API_TICKETS,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'title' => 'Ab',
                'description' => '',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateTicketWithInvalidStatusReturns400(): void
    {
        $client = static::createClient();
        $this->ensureSchemaCreated($client);
        $token = $this->getAuthenticatedToken($client);

        $client->request(
            Request::METHOD_POST,
            self::API_TICKETS,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'title' => 'Ticket avec mauvais status',
                'description' => '',
                'status' => 'INVALID_STATUS',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = $this->getResponseData($client);
        self::assertArrayHasKey('message', $data);
        self::assertStringContainsString('status', $data['message']);
    }

    /**
     * Test liste tickets : pagination, filtre (status, priority), tri (sort, order).
     */
    public function testListTicketsPaginationFilterAndSort(): void
    {
        $client = static::createClient();
        $this->ensureSchemaCreated($client);
        $token = $this->getAuthenticatedToken($client);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ];

        $ticketsPayloads = [
            ['title' => 'Ticket Open High', 'description' => 'A', 'status' => 'OPEN', 'priority' => 'HIGH'],
            ['title' => 'Ticket Open Medium', 'description' => 'B', 'status' => 'OPEN', 'priority' => 'MEDIUM'],
            ['title' => 'Ticket In Progress Low', 'description' => 'C', 'status' => 'IN_PROGRESS', 'priority' => 'LOW'],
            ['title' => 'Ticket Done High', 'description' => 'D', 'status' => 'DONE', 'priority' => 'HIGH'],
            ['title' => 'Ticket Open Low', 'description' => 'E', 'status' => 'OPEN', 'priority' => 'LOW'],
        ];
        foreach ($ticketsPayloads as $payload) {
            $client->request(Request::METHOD_POST, self::API_TICKETS, [], [], $headers, json_encode($payload, \JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }

        $client->request(Request::METHOD_GET, self::API_TICKETS . '?page=1&limit=2', [], [], $headers);
        self::assertResponseIsSuccessful();
        $list = $this->getResponseData($client);
        self::assertArrayHasKey('data', $list);
        self::assertArrayHasKey('meta', $list);
        self::assertCount(2, $list['data']);
        self::assertSame(1, $list['meta']['page']);
        self::assertSame(2, $list['meta']['limit']);
        foreach ($list['data'] as $item) {
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('title', $item);
            self::assertArrayHasKey('status', $item);
            self::assertArrayHasKey('priority', $item);
            self::assertArrayHasKey('createdAt', $item);
            self::assertArrayHasKey('createdBy', $item);
        }

        $client->request(Request::METHOD_GET, self::API_TICKETS . '?status=OPEN', [], [], $headers);
        self::assertResponseIsSuccessful();
        $list = $this->getResponseData($client);

        foreach ($list['data'] as $item) {
            self::assertSame('OPEN', $item['status']);
        }

        $client->request(Request::METHOD_GET, self::API_TICKETS . '?priority=HIGH', [], [], $headers);
        self::assertResponseIsSuccessful();
        $list = $this->getResponseData($client);

        foreach ($list['data'] as $item) {
            self::assertSame('HIGH', $item['priority']);
        }

        $client->request(Request::METHOD_GET, self::API_TICKETS . '?status=OPEN&priority=LOW', [], [], $headers);
        self::assertResponseIsSuccessful();
        $list = $this->getResponseData($client);

        self::assertSame('OPEN', $list['data'][0]['status']);
        self::assertSame('LOW', $list['data'][0]['priority']);

        $client->request(Request::METHOD_GET, self::API_TICKETS . '?sort=createdAt&order=desc&limit=5', [], [], $headers);
        self::assertResponseIsSuccessful();
        $list = $this->getResponseData($client);
        $dates = array_column($list['data'], 'createdAt');
        $datesSorted = $dates;
        rsort($datesSorted, \SORT_STRING);
        self::assertSame($datesSorted, $dates, 'Liste doit être triée par createdAt desc');

        $client->request(Request::METHOD_GET, self::API_TICKETS . '?sort=priority&order=asc&limit=5', [], [], $headers);
        self::assertResponseIsSuccessful();
        $list = $this->getResponseData($client);
        $priorities = array_column($list['data'], 'priority');
        $prioritiesSorted = $priorities;
        sort($prioritiesSorted, \SORT_STRING);
        self::assertSame($prioritiesSorted, $priorities, 'Liste doit être triée par priority asc ');
    }

    /**
     * Crée le schéma si la base est vide
     */
    private function ensureSchemaCreated(KernelBrowser $client): void
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        try {
            (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        } catch (ToolsException $e) {
            $cause = $e->getPrevious();
            if ($cause instanceof TableExistsException || ($cause !== null && str_contains($cause->getMessage(), 'already exists'))) {
                return;
            }
            throw $e;
        }
    }

    private function getAuthenticatedToken(KernelBrowser $client): string
    {
        $client->request(
            Request::METHOD_POST,
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test-ticket@example.com',
                'password' => 'password',
            ], \JSON_THROW_ON_ERROR),
        );

        if ($client->getResponse()->getStatusCode() === Response::HTTP_CREATED) {
            $data = $this->getResponseData($client);
            self::assertArrayHasKey('token', $data);
            return $data['token'];
        }

        $client->request(
            Request::METHOD_POST,
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test-ticket@example.com',
                'password' => 'password',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $data = $this->getResponseData($client);
        self::assertArrayHasKey('token', $data);
        return $data['token'];
    }

    private function getResponseData(KernelBrowser $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        return $data;
    }
}

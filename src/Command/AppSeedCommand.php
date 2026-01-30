<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Ticket;
use App\Entity\TicketPriority;
use App\Entity\TicketStatus;
use App\Entity\User;
use App\Entity\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:db:seed',
    description: 'Crée 10 utilisateurs et 20 tickets de démonstration.',
)]
final class AppSeedCommand extends Command
{
    private const DEFAULT_PASSWORD = 'password';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'purge',
            null,
            InputOption::VALUE_NONE,
            'Vider les tables user et ticket avant de seed',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('purge')) {
            $io->warning('Purge des tables user et ticket...');
            $this->purge();
        }

        $users = $this->createUsers();
        $io->success(sprintf('%d utilisateurs créés.', \count($users)));

        $this->createTickets($users);
        $io->success('20 tickets créés.');

        $io->info('Mot de passe par défaut pour tous les utilisateurs : ' . self::DEFAULT_PASSWORD);

        return Command::SUCCESS;
    }

    /**
     * @return list<User>
     */
    private function createUsers(): array
    {
        $users = [];
        $roles = [UserRole::ADMIN, UserRole::ADMIN, ...array_fill(0, 8, UserRole::USER)];

        for ($i = 1; $i <= 10; $i++) {
            $email = sprintf('user%d@example.com', $i);
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD));
            $user->setRole($roles[$i - 1]);
            $this->entityManager->persist($user);
            $users[] = $user;
        }

        $this->entityManager->flush();
        return $users;
    }

    /**
     * @param list<User> $users
     */
    private function createTickets(array $users): void
    {
        $titles = [
            'Bug affichage formulaire',
            'Demande d\'évolution export PDF',
            'Problème de performance sur la liste',
            'Ajouter un filtre par date',
            'Correction typo page d\'accueil',
            'Migration base de données',
            'Refonte interface utilisateur',
            'Documentation API manquante',
            'Erreur 500 sur le rapport',
            'Intégration SSO',
            'Optimisation requêtes',
            'Sécurité : mise à jour dépendances',
            'Nouvelle fonctionnalité notifications',
            'Support navigateur Safari',
            'Amélioration messages d\'erreur',
            'Backup automatique',
            'Dashboard analytics',
            'Export Excel',
            'Validation formulaire inscription',
            'Tests unitaires manquants',
        ];

        $statuses = [TicketStatus::OPEN, TicketStatus::IN_PROGRESS, TicketStatus::DONE];
        $priorities = [TicketPriority::LOW, TicketPriority::MEDIUM, TicketPriority::HIGH];
        $userCount = \count($users);

        for ($i = 0; $i < 20; $i++) {
            $ticket = new Ticket();
            $ticket->setTitle($titles[$i]);
            $ticket->setDescription(sprintf('Description du ticket "%s" pour démonstration.', $titles[$i]));
            $ticket->setStatus($statuses[$i % 3]);
            $ticket->setPriority($priorities[$i % 3]);
            $ticket->setCreatedBy($users[$i % $userCount]);
            $this->entityManager->persist($ticket);
        }

        $this->entityManager->flush();
    }

    private function purge(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\Ticket t')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User u')->execute();
        $this->entityManager->clear();
    }
}

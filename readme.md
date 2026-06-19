# Ticket Manager API

> API REST Symfony — [github.com/clems023/ticket_manager_backend](https://github.com/clems023/ticket_manager_backend)

API REST Symfony pour la gestion de tickets avec authentification JWT, rôles utilisateur (USER / ADMIN) et documentation OpenAPI (Swagger).

## Fonctionnalités

- **Authentification JWT** : inscription, connexion, routes protégées par Bearer token
- **Utilisateurs** : rôles `USER` et `ADMIN`
- **Tickets** : CRUD avec statuts (`OPEN`, `IN_PROGRESS`, `DONE`) et priorités (`LOW`, `MEDIUM`, `HIGH`)
- **Liste paginée** : filtres (`status`, `priority`), tri (`createdAt`, `priority`), pagination (`page`, `limit`)
- **Autorisations** : suppression réservée au créateur du ticket ou à un admin
- **Documentation interactive** : Swagger UI sur `/api/doc`
- **Données de démo** : commande de seed (10 users, 20 tickets)
- **Tests** : suite PHPUnit sur les endpoints tickets

## Stack technique

| Composant | Version |
|-----------|---------|
| PHP | ≥ 8.2 |
| Symfony | 7.4 |
| Doctrine ORM | 3.x |
| Lexik JWT Authentication Bundle | 3.x |
| Nelmio ApiDocBundle | 5.x |
| MySQL / MariaDB | 8.0+ |

## Prérequis

- PHP 8.2+ avec extensions `ctype`, `iconv`, `openssl`
- [Composer](https://getcomposer.org/)
- MySQL ou MariaDB
- (Recommandé) [Symfony CLI](https://symfony.com/download)

## Installation

```bash
git clone https://github.com/clems023/ticket_manager_backend.git
cd ticket_manager_backend

composer install
```

### Configuration

1. Copier le fichier d'environnement et l'adapter :

```bash
cp .env.example .env.local
```

2. Éditer `.env.local` : `DATABASE_URL`, `APP_SECRET`, identifiants MySQL.

3. Générer les clés JWT :

```bash
php bin/console lexik:jwt:generate-keypair
```

4. Créer la base de données `ticket_manager`, puis lancer les migrations :

```bash
php bin/console doctrine:migrations:migrate
```

5. (Optionnel) Insérer des données de démonstration :

```bash
php bin/console app:db:seed
```

Mot de passe par défaut des utilisateurs seedés : **`password`**  
Emails : `user1@example.com` … `user10@example.com` (les deux premiers sont `ADMIN`).

### Démarrer le serveur

```bash
symfony serve
```

Alternative sans Symfony CLI :

```bash
php -S localhost:8000 -t public
```

Documentation Swagger : **http://127.0.0.1:8000/api/doc**

## Endpoints API

| Méthode | Route | Auth | Description |
|---------|-------|------|-------------|
| `POST` | `/api/register` | Non | Inscription (email, password) → token JWT |
| `POST` | `/api/login` | Non | Connexion → token JWT |
| `GET` | `/api/tickets` | Oui | Liste paginée (filtres, tri) |
| `POST` | `/api/tickets` | Oui | Créer un ticket |
| `GET` | `/api/tickets/{id}` | Oui | Détail d'un ticket |
| `PATCH` | `/api/tickets/{id}` | Oui | Modifier un ticket (partiel) |
| `DELETE` | `/api/tickets/{id}` | Oui | Supprimer (créateur ou admin) |

### Authentification

Après `POST /api/register` ou `POST /api/login`, utiliser le token dans l'en-tête :

```http
Authorization: Bearer <votre_token_jwt>
```

### Exemple : créer un ticket

```bash
curl -X POST http://127.0.0.1:8000/api/tickets \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"title":"Mon ticket","description":"Description","status":"OPEN","priority":"HIGH"}'
```

### Exemple : lister avec filtres

```bash
curl "http://127.0.0.1:8000/api/tickets?status=OPEN&sort=createdAt&order=desc&page=1&limit=10" \
  -H "Authorization: Bearer <token>"
```

## Tests

1. Créer la base de test `ticket_manager_test`
2. Migrer l'environnement test :

```bash
php bin/console doctrine:migrations:migrate --env=test
```

3. Lancer les tests :

```bash
./vendor/bin/phpunit
```

## Structure du projet

```
src/
├── Command/          # app:db:seed
├── Controller/Api/   # RegisterController, TicketController
├── Dto/              # Requêtes validées (register, login, tickets)
├── Entity/           # User, Ticket, enums (status, priority, role)
└── Repository/       # UserRepository, TicketRepository
tests/
└── Controller/Api/   # Tests fonctionnels API
```

## Licence

Projet à usage personnel / portfolio. Voir le dépôt pour les conditions d'utilisation.

# API Ticket Manager

Application Symfony (API REST) avec authentification JWT, gestion d'utilisateurs et de tickets.

---

## Installation et démarrage

1. **Créer la base de données** `ticket_manager`

2. **Lancer les migrations**

    ```bash
    php bin/console doctrine:migrations:migrate
    ```

3. **Alimenter la base** 

    ```bash
    php bin/console app:db:seed
    ```

    Option `--purge` pour vider les tables avant : `php bin/console app:db:seed --purge`

4. **Démarrer le serveur**

    ```bash
    symfony serve
    ```

    Ou avec PHP : `php -S localhost:8000 -t public`

5. **Documentation API (Swagger)**  
   Une fois le serveur lancé : **http://localhost:8000/api/doc**

---

## Tests

1. **Créer la base de test** `ticket_manager_test`

2. **Lancer les migrations** en environnement test

    ```bash
    php bin/console doctrine:migrations:migrate --env=test
    ```

3. **Exécuter les tests**
    ```bash
    ./vendor/bin/phpunit tests/Controller/Api/TicketControllerTest.php
    ```
    Ou tous les tests : `./vendor/bin/phpunit`

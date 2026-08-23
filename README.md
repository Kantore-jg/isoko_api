# Market Management API

Backend Laravel du projet Market Management.

## Installation

1. Copier `.env.example` vers `.env`
2. Installer les dépendances:
   `composer install`
3. Générer la clé applicative:
   `php artisan key:generate`
4. Exécuter les migrations et les seeds:
   `php artisan migrate --seed`

## Endpoints initiaux

- `GET /api/health`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout`
- `GET /api/dashboard/summary`

## Auth

L'API utilise un jeton `Bearer` renvoyé par `/api/auth/login`.

```http
Authorization: Bearer <access_token>
```

## Notes métier

- Le système est conçu pour un seul marché.
- L'historique des affectations, paiements et mouvements doit être conservé.
- Les paiements seront branchés ensuite sur les allocations multi-périodes.
# isoko_api

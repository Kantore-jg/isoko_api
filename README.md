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
- CRUD `blocks`, `places`, `merchants`, `banks`
- CRUD `assignments` + `POST /api/assignments/{assignment}/terminate`
- CRUD `rent-periods` + `POST /api/rent-periods/{rentPeriod}/generate-obligations`
- CRUD `rent-obligations`
- CRUD `payments` + `POST /api/payments/{payment}/void`
- `POST /api/payments/preview-allocation`
- CRUD `receipts` + `POST /api/receipts/{receipt}/cancel`

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

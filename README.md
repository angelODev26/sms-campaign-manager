# SMS Campaign Manager

A backend API for managing and dispatching bulk SMS campaigns with asynchronous processing, Redis queues, and scheduled delivery.

Built with **Laravel 9**, **MySQL**, **Redis**, and **Docker**.

---

## Features

- **JWT-based Authentication** via Laravel Sanctum
- **Bulk CSV Import** — upload contacts from CSV files (comma or semicolon separated)
- **Async Processing** — CSV reading and contact insertion runs in the background via Redis queues
- **Scheduled Delivery** — campaigns are dispatched exactly at the configured time using Laravel's delayed jobs
- **Duplicate Detection** — unique constraint on `campaign_id + phone` prevents duplicate contacts per campaign
- **Personalized Messages** — supports `{name}` variable replacement per contact, or custom messages per row in the CSV
- **Campaign Stats** — tracks `total_contacts`, `duplicate_count`, and `sent_count` per campaign
- **Memory-efficient** — processes large CSV files (10k+ records) in batches of 500 without memory issues
- **Dockerized** — fully containerized with Nginx, PHP-FPM, MySQL, Redis, and a dedicated queue worker

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 9 |
| Auth | Laravel Sanctum |
| Database | MySQL 8 |
| Queue / Cache | Redis |
| Web Server | Nginx |
| Containerization | Docker + Docker Compose |

---

## Architecture

```
HTTP Request
     │
     ▼
  Nginx
     │
     ▼
PHP-FPM (Laravel)
     │
     ├── Auth endpoints (register, login, logout)
     │
     └── Campaign endpoints
              │
              ├── Store campaign + CSV
              │        │
              │        └── Dispatch ProcessCampaignCsv Job ──► Redis Queue
              │                                                      │
              │                                              Read CSV in batches
              │                                              Insert contacts
              │                                              Dispatch SendCampaignSms
              │                                              with ->delay(scheduled_at)
              │
              └── At scheduled_at
                       │
                       └── SendCampaignSms Job ──► Process contacts in chunks of 500
                                                    Update sent/failed status
                                                    Update campaign stats
```

---

## Getting Started

### Requirements

- Docker
- Docker Compose

### Installation

```bash
# Clone the repository
git clone https://github.com/angelODev26/sms-campaign-manager.git
cd sms-campaign-manager

# Copy environment file
cp .env.example .env

# Start all services
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate --force

# Fix storage permissions
docker compose exec app chmod -R 777 storage bootstrap/cache

```

The API will be available at `http://localhost:8080`

---

## API Endpoints

### Auth

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/register` | Register a new user |
| POST | `/api/login` | Login and get token |
| POST | `/api/logout` | Logout (requires token) |

### Campaigns

All campaign endpoints require `Authorization: Bearer {token}` header.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/campaigns` | List all campaigns |
| POST | `/api/campaigns` | Create campaign + upload CSV |
| GET | `/api/campaigns/{id}` | Get campaign details |
| DELETE | `/api/campaigns/{id}` | Delete campaign |

### Create Campaign — Request

```
POST /api/campaigns
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

| Field | Type | Required | Description |
|---|---|---|---|
| name | string | ✅ | Campaign name |
| message | string | ✅ | Default message. Supports `{name}` variable |
| scheduled_at | datetime | ✅ | Scheduled send time (must be future) |
| csv_file | file | ✅ | CSV file with contacts |

### CSV Format

The CSV must include a `phone` column. `name` and `message` are optional.

```csv
phone,name,message
3001234567,Juan García,
3012345678,María López,Special offer just for you María!
3023456789,Carlos,,
```

If `message` column is empty or not present, the campaign's default message is used with `{name}` replaced automatically.

---

## Campaign Lifecycle

```
draft → processing → scheduled → running → completed
```

| Status | Description |
|---|---|
| `draft` | Campaign created, CSV upload in progress |
| `processing` | Background job reading and inserting CSV contacts |
| `scheduled` | CSV processed, waiting for scheduled_at |
| `running` | SMS dispatch in progress |
| `completed` | All contacts processed |

### Contact Status

| Status | Description |
|---|---|
| `pending` | Waiting to be sent |
| `sent` | Successfully sent |
| `failed` | Delivery failed |
| `simulated` | Reserved for provider rate-limited batches |

---

## Performance

Tested with 10,000 contact CSV files:

| Operation | Time |
|---|---|
| CSV read + insert (10k records) | ~600ms |
| SMS dispatch (9k contacts, chunked 500) | ~700ms |

Batch inserts with `insertOrIgnore` and `chunkById` processing keep memory usage flat regardless of file size.

---

## Extending with a Real SMS Provider

The `SendCampaignSms` job is ready for real provider integration. Replace the simulation logic in `app/Jobs/SendCampaignSms.php`:

```php
// Replace this simulation:
$success = rand(1, 10) <= 8;

// With your provider SDK, e.g. Twilio:
$twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
$twilio->messages->create($detail->phone, [
    'from' => config('services.twilio.from'),
    'body' => $detail->message,
]);
$success = true;
```

---

## License

MIT

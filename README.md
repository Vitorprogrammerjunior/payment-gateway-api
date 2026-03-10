# Payment Gateway Manager

> API RESTful para gerenciamento de pagamentos multi-gateway com fallback automático, controle de acesso por roles, TDD e containerização completa.

**Stack:** Laravel 11 · PHP 8.2 · MySQL 8.0 · Laravel Sanctum · Pest · Docker Compose

---

## Sumário

- [Visão Geral](#visão-geral)
- [Arquitetura](#arquitetura)
- [Pré-requisitos](#pré-requisitos)
- [Instalação com Docker (recomendado)](#instalação-com-docker-recomendado)
- [Instalação Local](#instalação-local)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Autenticação](#autenticação)
- [Roles e Permissões](#roles-e-permissões)
- [Referência da API](#referência-da-api)
  - [Auth](#auth)
  - [Compras](#compras)
  - [Transações](#transações)
  - [Clientes](#clientes)
  - [Produtos](#produtos)
  - [Gateways](#gateways)
  - [Usuários](#usuários)
- [Lógica de Fallback entre Gateways](#lógica-de-fallback-entre-gateways)
- [Estrutura do Projeto](#estrutura-do-projeto)
- [Testes](#testes)
- [Como Adicionar um Novo Gateway](#como-adicionar-um-novo-gateway)

---

## Visão Geral

O sistema permite processar pagamentos através de múltiplos gateways de forma transparente. Quando o gateway de maior prioridade falha, o sistema tenta automaticamente o próximo gateway ativo — sem intervenção manual e sem expor o erro ao cliente final.

Funcionalidades principais:

- **Compra pública** — qualquer visitante pode realizar uma compra (sem autenticação)
- **Fallback automático** — gateways ordenados por prioridade com tentativa sequencial em caso de falha
- **Controle de acesso por roles** — `admin`, `manager`, `finance`, `user`
- **Reembolso** — processado via gateway original da transação
- **Clientes automáticos** — criados ou reutilizados por email automaticamente
- **Múltiplos produtos por compra** — com controle de quantidade e cálculo automático do total

---

## Arquitetura

```
┌────────────────────────────────────────┐
│              HTTP Request              │
└──────────────────┬─────────────────────┘
                   │
       ┌───────────▼────────────┐
       │  Form Request (Validate)│
       └───────────┬────────────┘
                   │
       ┌───────────▼────────────┐
       │       Controller       │
       └───────────┬────────────┘
                   │
       ┌───────────▼────────────┐
       │    PurchaseService     │  ← orquestra o fallback
       └───────────┬────────────┘
                   │
       ┌───────────▼────────────┐
       │  GatewayDriverResolver │  ← seleciona driver pelo nome do gateway
       └──────┬─────────────────┘
              │
   ┌──────────┴──────────┐
   │                     │
┌──▼─────────────┐  ┌────▼──────────────┐
│ Gateway1Driver │  │  Gateway2Driver   │
│ (Bearer Token) │  │ (Static Headers)  │
└────────────────┘  └───────────────────┘
```

**Padrões aplicados:** Strategy (drivers), DTO (ChargeRequest/ChargeResponse), Service Layer, Form Request Validation.

---

## Pré-requisitos

**Docker (recomendado):**
- Docker 24+
- Docker Compose v2+

**Local:**
- PHP 8.2+
- Composer 2+
- MySQL 8.0+

---

## Instalação com Docker (recomendado)

```bash
# 1. Clone o repositório
git clone https://github.com/Vitorprogrammerjunior/payment-gateway-api.git
cd payment-gateway-api

# 2. Suba os três serviços (app Laravel + MySQL + mocks dos gateways)
docker compose up -d --build

# Em alguns sistemas Linux pode ser necessário usar sudo:
# sudo docker compose up -d --build
```

> **Linux sem permissão Docker:** Se precisar de `sudo`, você pode evitá-lo adicionando seu usuário ao grupo `docker`:
> ```bash
> sudo usermod -aG docker $USER
> # Faça logout e login novamente para o grupo ser aplicado
> ```

Aguarde ~20s para o container inicializar (migrations rodam automaticamente). A API estará disponível em `http://localhost:8000`.

**Serviços em execução:**

| Serviço        | URL                   | Descrição             |
|----------------|-----------------------|-----------------------|
| API Laravel    | http://localhost:8000 | Aplicação principal   |
| Gateway 1 mock | http://localhost:3001 | Mock do gateway 1     |
| Gateway 2 mock | http://localhost:3002 | Mock do gateway 2     |
| MySQL          | localhost:3306        | Banco de dados        |

**Credenciais do admin (criadas pelo seeder):**

| Campo | Valor                 |
|-------|-----------------------|
| Email | `admin@betalent.tech` |
| Senha | `password`            |
| Role  | `admin`               |

---

## Instalação Local

```bash
# 1. Clone e instale dependências
git clone https://github.com/Vitorprogrammerjunior/payment-gateway-api.git
cd payment-gateway-api
composer install

# 2. Configure o ambiente
cp .env.example .env
php artisan key:generate

# 3. Edite o .env com suas credenciais do MySQL e rode as migrations
php artisan migrate --seed

# 4. Suba os mocks dos gateways (requer Docker)
docker run -p 3001:3001 -p 3002:3002 matheusprotzen/gateways-mock

# 5. Inicie o servidor
php artisan serve
```

---

## Variáveis de Ambiente

| Variável               | Descrição                                          | Valor no Docker          |
|------------------------|----------------------------------------------------|--------------------------|
| `DB_HOST`              | Host do MySQL                                      | `mysql`                  |
| `DB_DATABASE`          | Nome do banco de dados                             | `payment_gateway`        |
| `DB_USERNAME`          | Usuário do MySQL                                   | `root`                   |
| `DB_PASSWORD`          | Senha do MySQL                                     | `root`                   |
| `GATEWAY1_URL`         | URL base do Gateway 1                              | `http://gateways:3001`   |
| `GATEWAY1_EMAIL`       | Email de autenticação do Gateway 1                 | `dev@betalent.tech`      |
| `GATEWAY1_TOKEN`       | Token de autenticação do Gateway 1                 | `FEC9BB078B...`          |
| `GATEWAY2_URL`         | URL base do Gateway 2                              | `http://gateways:3002`   |
| `GATEWAY2_AUTH_TOKEN`  | Header `Gateway-Auth-Token` do Gateway 2           | `tk_f2198cc6...`         |
| `GATEWAY2_AUTH_SECRET` | Header `Gateway-Auth-Secret` do Gateway 2          | `3d15e8ed61...`          |

> **Importante:** Dentro do Docker, use os nomes dos serviços (`mysql`, `gateways`) em vez de `localhost` para comunicação entre containers.

---

## Autenticação

A API utiliza **Bearer Tokens** via Laravel Sanctum.

1. Faça login em `POST /api/login` para obter o token
2. Inclua o token no header de todas as rotas privadas:

```
Authorization: Bearer <seu_token>
```

---

## Roles e Permissões

| Role      | Acesso                                                                |
|-----------|-----------------------------------------------------------------------|
| `admin`   | Acesso total                                                          |
| `manager` | Gerencia produtos e usuários; lê clientes e transações               |
| `finance` | Cria/edita/exclui produtos e reembolsa transações; lê clientes       |
| `user`    | Apenas leitura de clientes, transações e produtos                    |

---

## Referência da API

> Prefixo de todas as rotas: `/api`
> Headers recomendados: `Content-Type: application/json` e `Accept: application/json`

---

### Auth

#### `POST /api/login`

Autentica o usuário e retorna um Bearer token. **Rota pública.**

**Request:**
```json
{
  "email": "admin@betalent.tech",
  "password": "password"
}
```

**Response `200 OK`:**
```json
{
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@betalent.tech",
    "role": "admin",
    "created_at": "2026-03-09T19:44:32.000000Z",
    "updated_at": "2026-03-09T19:44:32.000000Z"
  },
  "token": "1|abc123xyz..."
}
```

---

#### `POST /api/logout`

Revoga o token atual. **Requer autenticação.**

**Response `200 OK`:**
```json
{
  "message": "Logged out successfully."
}
```

---

### Compras

#### `POST /api/purchase`

Processa uma compra. **Rota pública — não requer autenticação.**

O cliente é criado automaticamente caso não exista (identificado pelo email).
O sistema tenta os gateways em ordem de prioridade com fallback automático.

**Request:**
```json
{
  "client_name": "João Silva",
  "client_email": "joao@example.com",
  "card_number": "4111111111111111",
  "cvv": "123",
  "products": [
    { "id": 1, "quantity": 2 },
    { "id": 2, "quantity": 1 }
  ]
}
```

**Campos:**

| Campo                  | Tipo    | Obrigatório | Regras                              |
|------------------------|---------|-------------|-------------------------------------|
| `client_name`          | string  | sim         | máx. 255 caracteres                 |
| `client_email`         | string  | sim         | email válido                        |
| `card_number`          | string  | sim         | exatamente 16 dígitos               |
| `cvv`                  | string  | sim         | 3 ou 4 dígitos                      |
| `products`             | array   | sim         | mínimo 1 item                       |
| `products.*.id`        | integer | sim         | deve existir na tabela `products`   |
| `products.*.quantity`  | integer | sim         | mínimo 1                            |

**Response `201 Created`:**
```json
{
  "id": 1,
  "status": "paid",
  "amount": 19980,
  "card_last_numbers": "1111",
  "external_id": "txn_abc123",
  "client": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@example.com"
  },
  "gateway": {
    "id": 1,
    "name": "gateway1"
  },
  "products": [
    { "product_id": 1, "quantity": 2, "unit_amount": 9990 }
  ],
  "created_at": "2026-03-09T20:00:00.000000Z"
}
```

> `amount` e `unit_amount` são sempre em **centavos** (ex: `9990` = R$ 99,90).

---

### Transações

#### `GET /api/transactions`

Lista todas as transações. **Requer autenticação.**

**Response `200 OK`:**
```json
[
  {
    "id": 1,
    "status": "paid",
    "amount": 19980,
    "card_last_numbers": "1111",
    "external_id": "txn_abc123",
    "client": { "id": 1, "name": "João Silva", "email": "joao@example.com" },
    "gateway": { "id": 1, "name": "gateway1" },
    "products": [
      { "product_id": 1, "quantity": 2, "unit_amount": 9990 }
    ],
    "created_at": "2026-03-09T20:00:00.000000Z"
  }
]
```

---

#### `GET /api/transactions/{id}`

Detalhes de uma transação. **Requer autenticação.**

**Response `200 OK`:** mesmo formato do item acima.

---

#### `POST /api/transactions/{id}/refund`

Estorna uma transação via gateway original. **Roles:** `admin`, `finance`.

**Response `200 OK`:**
```json
{
  "id": 1,
  "status": "refunded",
  "amount": 19980,
  ...
}
```

**Erros comuns:**
- `422` — transação já reembolsada ou com status incompatível
- `403` — role sem permissão para reembolso

---

### Clientes

#### `GET /api/clients`

Lista todos os clientes. **Requer autenticação.**

**Response `200 OK`:**
```json
[
  {
    "id": 1,
    "name": "João Silva",
    "email": "joao@example.com",
    "created_at": "2026-03-09T20:00:00.000000Z"
  }
]
```

---

#### `GET /api/clients/{id}`

Detalhes do cliente com histórico de transações. **Requer autenticação.**

**Response `200 OK`:**
```json
{
  "id": 1,
  "name": "João Silva",
  "email": "joao@example.com",
  "transactions": [
    {
      "id": 1,
      "status": "paid",
      "amount": 19980,
      "card_last_numbers": "1111",
      "created_at": "2026-03-09T20:00:00.000000Z"
    }
  ]
}
```

---

### Produtos

#### `GET /api/products`

Lista todos os produtos. **Requer autenticação.**

**Response `200 OK`:**
```json
[
  {
    "id": 1,
    "name": "Produto Teste",
    "amount": 9990,
    "created_at": "2026-03-09T20:00:00.000000Z"
  }
]
```

---

#### `GET /api/products/{id}`

Detalhes de um produto. **Requer autenticação.**

---

#### `POST /api/products`

Cria um novo produto. **Roles:** `admin`, `manager`, `finance`.

**Request:**
```json
{
  "name": "Produto Teste",
  "amount": 9990
}
```

**Response `201 Created`:**
```json
{
  "id": 1,
  "name": "Produto Teste",
  "amount": 9990,
  "created_at": "2026-03-09T20:00:00.000000Z"
}
```

---

#### `PUT /api/products/{id}`

Atualiza um produto. **Roles:** `admin`, `manager`, `finance`.

**Request (campos opcionais):**
```json
{
  "name": "Nome Atualizado",
  "amount": 14990
}
```

**Response `200 OK`:** produto atualizado.

---

#### `DELETE /api/products/{id}`

Remove um produto (soft delete). **Roles:** `admin`, `manager`, `finance`.

**Response `204 No Content`**

---

### Gateways

#### `GET /api/gateways`

Lista todos os gateways configurados. **Role:** `admin`.

**Response `200 OK`:**
```json
[
  { "id": 1, "name": "gateway1", "is_active": true, "priority": 1 },
  { "id": 2, "name": "gateway2", "is_active": true, "priority": 2 }
]
```

---

#### `PATCH /api/gateways/{id}`

Ativa/desativa um gateway ou altera sua prioridade. **Role:** `admin`.

**Request (campos opcionais):**
```json
{
  "is_active": false,
  "priority": 2
}
```

**Response `200 OK`:** gateway atualizado.

> Alterações entram em vigor imediatamente nas próximas compras, sem reiniciar a aplicação.

---

### Usuários

#### `GET /api/users`

Lista todos os usuários. **Roles:** `admin`, `manager`.

**Response `200 OK`:**
```json
[
  {
    "id": 1,
    "name": "Admin",
    "email": "admin@betalent.tech",
    "role": "admin",
    "created_at": "2026-03-09T19:44:32.000000Z"
  }
]
```

---

#### `GET /api/users/{id}`

Detalhes de um usuário. **Roles:** `admin`, `manager`.

---

#### `POST /api/users`

Cria um novo usuário. **Roles:** `admin`, `manager`.

**Request:**
```json
{
  "name": "Novo Usuário",
  "email": "novo@example.com",
  "password": "senha123",
  "password_confirmation": "senha123",
  "role": "finance"
}
```

**Roles válidas:** `admin`, `manager`, `finance`, `user`.

**Response `201 Created`:**
```json
{
  "id": 2,
  "name": "Novo Usuário",
  "email": "novo@example.com",
  "role": "finance",
  "created_at": "2026-03-09T20:00:00.000000Z"
}
```

---

#### `PUT /api/users/{id}`

Atualiza um usuário. **Roles:** `admin`, `manager`.

**Request (campos opcionais):**
```json
{
  "name": "Nome Atualizado",
  "email": "atualizado@example.com",
  "password": "novasenha123",
  "password_confirmation": "novasenha123",
  "role": "manager"
}
```

---

#### `DELETE /api/users/{id}`

Remove um usuário. **Roles:** `admin`, `manager`.

**Response `204 No Content`**

---

## Lógica de Fallback entre Gateways

O `PurchaseService` consulta os gateways ativos ordenados pela coluna `priority` (menor valor = maior prioridade). Para cada gateway, tenta processar o pagamento via seu driver. Em caso de falha (`GatewayException`), avança silenciosamente para o próximo. Só retorna erro ao cliente final se **todos** os gateways ativos falharem.

```
Gateway 1 (priority=1) → FALHA
         ↓
Gateway 2 (priority=2) → SUCESSO ✓
```

**Como testar o fallback manualmente:**
```
PATCH /api/gateways/1
{ "is_active": false }
```
A próxima compra será processada automaticamente pelo Gateway 2.

---

## Estrutura do Projeto

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── ClientController.php
│   │   ├── GatewayController.php
│   │   ├── ProductController.php
│   │   ├── PurchaseController.php
│   │   └── UserController.php
│   ├── Middleware/
│   │   └── RoleMiddleware.php         # Controle de acesso por roles
│   └── Requests/
│       ├── LoginRequest.php
│       ├── PurchaseRequest.php
│       ├── StoreProductRequest.php
│       ├── UpdateProductRequest.php
│       ├── UpdateGatewayRequest.php
│       ├── StoreUserRequest.php
│       └── UpdateUserRequest.php
├── Models/
│   ├── Client.php
│   ├── Gateway.php
│   ├── Product.php
│   ├── Transaction.php
│   ├── TransactionProduct.php
│   └── User.php
└── Services/
    ├── Gateway/
    │   ├── Drivers/
    │   │   ├── Gateway1Driver.php     # Autenticação: Bearer token via POST /login
    │   │   └── Gateway2Driver.php     # Autenticação: headers estáticos
    │   ├── ChargeRequest.php          # DTO de entrada para cobranças
    │   ├── ChargeResponse.php         # DTO de saída dos gateways
    │   ├── GatewayDriverInterface.php
    │   ├── GatewayDriverResolver.php  # Resolve o driver correto por gateway
    │   └── GatewayException.php
    └── PurchaseService.php            # Orquestra compra e reembolso com fallback
database/
├── factories/
├── migrations/
└── seeders/
    └── DatabaseSeeder.php             # Admin + 2 gateways
tests/
└── Feature/
    ├── AuthTest.php
    ├── ClientTest.php
    ├── GatewayTest.php
    ├── ProductTest.php
    ├── PurchaseTest.php
    ├── RefundTest.php
    └── UserTest.php
docker/
└── app/Dockerfile
docker-compose.yml
```

---

## Testes

Os testes utilizam **SQLite in-memory** e **mocks dos drivers de gateway**, sem dependência de serviços externos.

```bash
# Dentro do Docker
docker compose exec app php artisan test

# Localmente
php artisan test

# Com output detalhado (Pest)
./vendor/bin/pest --verbose
```

**Resultado:** 38 testes · 86 assertions · 0 failures

---

## Como Adicionar um Novo Gateway

1. **Crie o driver** em `app/Services/Gateway/Drivers/NewGatewayDriver.php`:

```php
class NewGatewayDriver implements GatewayDriverInterface
{
    public function charge(ChargeRequest $request): ChargeResponse
    {
        // lógica de cobrança
    }

    public function refund(string $externalId): void
    {
        // lógica de reembolso
    }
}
```

2. **Registre no resolver** em `GatewayDriverResolver::$driverMap`:

```php
private array $driverMap = [
    'gateway1'   => Gateway1Driver::class,
    'gateway2'   => Gateway2Driver::class,
    'newgateway' => NewGatewayDriver::class, // ← adicione aqui
];
```

3. **Insira o registro** no banco via seeder ou migration:

```php
Gateway::create(['name' => 'newgateway', 'is_active' => true, 'priority' => 3]);
```

4. **Adicione as variáveis** no `.env` e em `config/gateways.php`.

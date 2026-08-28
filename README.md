<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# STTC API

API berbasis **Laravel 13** yang dibangun menggunakan **Service-Repository Pattern** untuk memaksimalkan _clean code_, _maintainability_, dan _testability_.

---

## Arsitektur

Alur request mengalir secara linear melalui beberapa lapisan yang masing-masing punya tanggung jawab tunggal:

```
Route → Controller → Service → Repository → Model (Eloquent) → Database
                ↑          ↑
          FormRequest   BusinessException
                ↓
           API Resource (response)
```

| Layer | Lokasi | Tanggung Jawab |
|---|---|---|
| **Contracts** | `app/Repositories/Contracts/` | Interface repository — controller/service hanya bergantung ke interface, bukan ke kelas konkret. |
| **Repositories** | `app/Repositories/Eloquent/` | Akses database (Eloquent). Tidak boleh ada logika bisnis di sini. |
| **Services** | `app/Services/` | Logika bisnis, validasi domain, orkestrasi beberapa repository, transaction. |
| **Form Requests** | `app/Http/Requests/` | Validasi input HTTP. Otomatis return JSON 422 saat gagal. |
| **API Resources** | `app/Http/Resources/` | Transformasi output (model → JSON). |
| **Controllers** | `app/Http/Controllers/Api/V1/` | Tipis. Hanya menerima request, panggil service, kembalikan response. |
| **ApiResponse Trait** | `app/Traits/ApiResponse.php` | Helper response konsisten: `success()`, `created()`, `error()`, `noContent()`. |
| **BusinessException** | `app/Exceptions/BusinessException.php` | Untuk pelanggaran aturan domain dari service layer. |

Binding interface ke implementasi diatur di [`app/Providers/RepositoryServiceProvider.php`](app/Providers/RepositoryServiceProvider.php).

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Backing services (PostgreSQL 17 + Redis 7). Aplikasi tetap PHP-FPM/`artisan serve` di host (ADR-0001).
docker compose up -d

php artisan migrate
php artisan serve
```

### Backing services (Docker)

| Service | Host port | Kredensial default (lokal) |
|---|---|---|
| PostgreSQL 17 | `5433` (internal 5432) | user `sttc` / pass `secret` / db `sttc_api` |
| Redis 7 | `6379` | pass `secret` (`appendonly`, `noeviction`) |

- Port PostgreSQL sengaja `5433` di host untuk menghindari bentrok dengan PostgreSQL native.
- Redis DB 1 = cache, DB 2 = throttle/lockout (store `throttle`, tidak ikut `cache:clear`).
- Session & queue tetap di database (lihat `../epics/sprint-1-plan.md` §5.3).
- Produksi: `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d` — wajib set
  `DB_PASSWORD` & `REDIS_PASSWORD` kuat via environment.
- `phpredis` tidak diasumsikan ada; klien default `predis` (`REDIS_CLIENT=predis`).

Endpoint API tersedia di prefix `/api/v1/...` dengan autentikasi **Laravel Sanctum** (token-based).

---

## Tutorial: Menambahkan Endpoint Baru (Contoh: `Product`)

Berikut langkah-langkah membuat _resource_ baru dari nol mengikuti pola Service-Repository. Kita akan membuat endpoint **CRUD Product**.

### 1. Buat Migration & Model

```bash
php artisan make:model Product -m
```

Edit migration `database/migrations/xxxx_create_products_table.php`:

```php
public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('sku')->unique();
        $table->text('description')->nullable();
        $table->unsignedBigInteger('price');
        $table->unsignedInteger('stock')->default(0);
        $table->timestamps();
    });
}
```

Edit model `app/Models/Product.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'sku', 'description', 'price', 'stock'])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
        ];
    }
}
```

Jalankan migration:

```bash
php artisan migrate
```

### 2. Buat Repository Contract (Interface)

File: `app/Repositories/Contracts/ProductRepositoryInterface.php`

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Product;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function findBySku(string $sku): ?Product;
}
```

> **Tips:** `BaseRepositoryInterface` sudah menyediakan `all`, `paginate`, `find`, `findOrFail`, `findBy`, `where`, `create`, `update`, `delete`, `with`, dan `query`. Kamu hanya perlu menambahkan method spesifik domain (mis. `findBySku`).

### 3. Buat Repository Implementation

File: `app/Repositories/Eloquent/ProductRepository.php`

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public static function modelClass(): string
    {
        return Product::class;
    }

    public function findBySku(string $sku): ?Product
    {
        /** @var Product|null $product */
        $product = $this->findBy('sku', $sku);

        return $product;
    }
}
```

### 4. Daftarkan Binding di RepositoryServiceProvider

Edit `app/Providers/RepositoryServiceProvider.php`:

```php
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Eloquent\ProductRepository;

public array $bindings = [
    UserRepositoryInterface::class => UserRepository::class,
    ProductRepositoryInterface::class => ProductRepository::class, // ← tambahkan ini
];
```

> **Penting:** Tanpa binding ini, Laravel tidak tahu implementasi mana yang harus di-_inject_ saat controller meminta `ProductRepositoryInterface`.

### 5. Buat Service

File: `app/Services/ProductService.php`

```php
<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;

class ProductService extends BaseService
{
    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function create(array $data): Product
    {
        if ($this->productRepository()->findBySku($data['sku'])) {
            throw new BusinessException('SKU sudah digunakan.', 409);
        }

        /** @var Product $product */
        $product = parent::create($data);

        return $product;
    }

    public function decrementStock(int $id, int $qty): Product
    {
        return $this->transaction(function () use ($id, $qty) {
            $product = $this->repository->findOrFail($id);

            if ($product->stock < $qty) {
                throw new BusinessException('Stok tidak mencukupi.', 422);
            }

            $product->decrement('stock', $qty);

            return $product->refresh();
        });
    }

    private function productRepository(): ProductRepositoryInterface
    {
        /** @var ProductRepositoryInterface $repo */
        $repo = $this->repository;

        return $repo;
    }
}
```

> **Konsep penting:**
> - Method CRUD standar (`list`, `paginate`, `find`, `update`, `delete`) sudah diwariskan dari `BaseService`.
> - Override method bila butuh aturan bisnis tambahan (contoh: cek SKU unik, validasi stok).
> - Gunakan `$this->transaction(fn () => ...)` saat menulis ke beberapa tabel sekaligus.
> - Throw `BusinessException` untuk pelanggaran aturan domain — akan otomatis di-render menjadi JSON oleh global exception handler.

### 6. Buat Form Request

File: `app/Http/Requests/Product/StoreProductRequest.php`

```php
<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseFormRequest;

class StoreProductRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
        ];
    }
}
```

File: `app/Http/Requests/Product/UpdateProductRequest.php`

```php
<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
```

> **Catatan:** Karena meng-extends `BaseFormRequest`, validasi yang gagal otomatis return JSON 422 dengan format konsisten — tidak perlu menulis ulang exception handler.

### 7. Buat API Resource

File: `app/Http/Resources/ProductResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

### 8. Buat Controller (Tipis)

File: `app/Http/Controllers/Api/V1/ProductController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        return $this->success(
            ProductResource::collection($this->productService->paginate($perPage))
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        return $this->created(new ProductResource($product), 'Produk berhasil dibuat.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new ProductResource($this->productService->find($id)));
    }

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = $this->productService->update($id, $request->validated());

        return $this->success(new ProductResource($product), 'Produk berhasil diperbarui.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->productService->delete($id);

        return $this->success(message: 'Produk berhasil dihapus.');
    }
}
```

> **Aturan emas controller:**
> - **Tidak ada query Eloquent.** Itu tugas repository.
> - **Tidak ada `if`/`throw` aturan bisnis.** Itu tugas service.
> - Cukup: terima request → panggil service → bungkus dengan resource → return.

### 9. Daftarkan Route

Edit `routes/api.php`, tambahkan di dalam group `auth:sanctum`:

```php
use App\Http\Controllers\Api\V1\ProductController;

Route::middleware('auth:sanctum')->group(function () {
    // ... route lain
    Route::apiResource('products', ProductController::class);
});
```

### 10. Verifikasi

Cek bahwa route terdaftar:

```bash
php artisan route:list --path=api/v1/products
```

Harus muncul 5 route: `index`, `store`, `show`, `update`, `destroy`.

Coba hit endpoint (login dulu untuk dapat token):

```bash
# Register / login dulu untuk dapat token
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"jane@example.com","password":"Secret123!"}'

# Buat product
curl -X POST http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"Kopi Arabika","sku":"KOPI-001","price":85000,"stock":100}'
```

---

## Format Response Standar

Semua endpoint API mengikuti envelope yang konsisten via `ApiResponse` trait:

**Sukses:**
```json
{
  "success": true,
  "message": "OK",
  "data": { ... }
}
```

**Sukses dengan paginasi:**
```json
{
  "success": true,
  "message": "OK",
  "data": [ ... ],
  "meta": { "current_page": 1, "per_page": 15, "total": 42 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

**Validasi gagal (422):**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field is required."] }
}
```

**Error bisnis / autentikasi / not found:** Otomatis di-render JSON oleh handler di `bootstrap/app.php`.

---

## Testing

```bash
php artisan test
```

Test menggunakan SQLite in-memory (lihat `phpunit.xml`). Contoh test alur Auth ada di `tests/Feature/Auth/AuthFlowTest.php`.

---

## Checklist Saat Menambah Resource Baru

- [ ] Migration + Model
- [ ] Repository Interface (extends `BaseRepositoryInterface`)
- [ ] Repository Implementation (extends `BaseRepository`)
- [ ] Binding di `RepositoryServiceProvider::$bindings`
- [ ] Service (extends `BaseService`)
- [ ] FormRequest Store & Update (extends `BaseFormRequest`)
- [ ] API Resource
- [ ] Controller tipis di `Api/V1/`
- [ ] Route di `routes/api.php`
- [ ] Test di `tests/Feature/`

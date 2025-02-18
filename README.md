# Ideasoft SE Case Study - E-Commerce API
[![Coverage Status](https://coveralls.io/repos/github/bilalbaraz/ideasoft-se-case-study/badge.svg?branch=main)](https://coveralls.io/github/bilalbaraz/ideasoft-se-case-study?branch=main)

Bu proje, IdeaSoft Senior PHP Developer pozisyonu için hazırlanmış örnek bir e-ticaret API projesidir.

## Demo

API'yi aşağıdaki adreslerden test edebilirsiniz:

- Production: [https://ideasoft.barazlab.com](https://ideasoft.barazlab.com)
- Local: [http://localhost:8000](http://localhost:8000)

## Postman Collection

API'yi test etmek için Postman Collection'ı kullanabilirsiniz:

[![Run in Postman](https://run.pstmn.io/button.svg)](https://github.com/bilalbaraz/ideasoft-se-case-study/tree/main/postman)

İki farklı environment bulunmaktadır:
- Production Environment (ideasoft.barazlab.com)
- Local Environment (localhost:8000)

## Teknolojiler

- PHP 8.3
- Laravel 11
- MySQL 8.0
- Docker

## Kurulum

### Gereksinimler

- Docker
- Docker Compose

### Adımlar

1. Projeyi klonlayın:
```bash
git clone https://github.com/bilalbaraz/ideasoft-se-case-study.git
cd ideasoft-se-case-study
```

2. .env dosyasını oluşturun:
```bash
cp .env.example .env
```

3. Docker container'larını başlatın:
```bash
docker compose up -d
```

4. Composer bağımlılıklarını yükleyin:
```bash
docker compose exec app composer install
```

5. Laravel key'i oluşturun:
```bash
docker compose exec app php artisan key:generate
```

6. Migration ve seeder'ları çalıştırın:
```bash
docker compose exec app php artisan migrate:fresh --seed
```

## Veritabanı Bağlantısı

MySQL veritabanına TablePlus veya benzeri bir araç ile bağlanmak için:

- Host: localhost
- Port: 3306
- Database: ideasoft_se_case_study
- Username: ideasoft
- Password: ideasoft123

## API Endpoints

### Orders

#### List Orders
- **GET** `/api/v1/orders`
- **Açıklama**: Tüm siparişleri listele
- **Response**:
```json
{
    "data": [
        {
            "id": 1,
            "customer_id": 1,
            "total": 1500.00,
            "created_at": "2024-01-30T10:00:00.000000Z",
            "updated_at": "2024-01-30T10:00:00.000000Z",
            "customer": {
                "id": 1,
                "name": "John Doe",
                "since": "2024-01-30T10:00:00.000000Z"
            },
            "items": [
                {
                    "id": 1,
                    "order_id": 1,
                    "product_id": 1,
                    "quantity": 2,
                    "unit_price": 500.00,
                    "total": 1000.00,
                    "product": {
                        "id": 1,
                        "name": "Product 1",
                        "category_id": 1,
                        "price": 500.00,
                        "stock": 8
                    }
                }
            ]
        }
    ]
}
```

#### Get Order
- **GET** `/api/v1/orders/{id}`
- **Açıklama**: Belirli bir siparişi getir
- **Response**:
```json
{
    "data": {
        "id": 1,
        "customer_id": 1,
        "total": 1500.00,
        "created_at": "2024-01-30T10:00:00.000000Z",
        "updated_at": "2024-01-30T10:00:00.000000Z",
        "customer": {
            "id": 1,
            "name": "John Doe",
            "since": "2024-01-30T10:00:00.000000Z"
        },
        "items": [
            {
                "id": 1,
                "order_id": 1,
                "product_id": 1,
                "quantity": 2,
                "unit_price": 500.00,
                "total": 1000.00,
                "product": {
                    "id": 1,
                    "name": "Product 1",
                    "category_id": 1,
                    "price": 500.00,
                    "stock": 8
                }
            }
        ]
    }
}
```

#### Create Order
- **POST** `/api/v1/orders`
- **Açıklama**: Yeni sipariş oluştur
- **Request Body**:
```json
{
    "customer_id": 1,
    "items": [
        {
            "product_id": 1,
            "quantity": 2
        },
        {
            "product_id": 2,
            "quantity": 1
        }
    ]
}
```
- **Response**:
```json
{
    "data": {
        "id": 1,
        "customer_id": 1,
        "total": 1500.00,
        "created_at": "2024-01-30T10:00:00.000000Z",
        "updated_at": "2024-01-30T10:00:00.000000Z",
        "customer": {
            "id": 1,
            "name": "John Doe",
            "since": "2024-01-30T10:00:00.000000Z"
        },
        "items": [
            {
                "id": 1,
                "order_id": 1,
                "product_id": 1,
                "quantity": 2,
                "unit_price": 500.00,
                "total": 1000.00,
                "product": {
                    "id": 1,
                    "name": "Product 1",
                    "category_id": 1,
                    "price": 500.00,
                    "stock": 8
                }
            }
        ]
    },
    "message": "Order created successfully"
}
```

- **Error Response**:
```json
{
    "message": "Error creating order",
    "error": "Insufficient stock for product 1"
}
```

#### Update Order
- **PUT** `/api/v1/orders/{id}`
- **Açıklama**: Siparişi güncelle
- **Request Body**:
```json
{
    "items": [
        {
            "product_id": 1,
            "quantity": 3
        },
        {
            "product_id": 2,
            "quantity": 2
        }
    ]
}
```
- **Response**:
```json
{
    "data": {
        "id": 1,
        "customer_id": 1,
        "total": 2500.00,
        "created_at": "2024-01-30T10:00:00.000000Z",
        "updated_at": "2024-01-30T10:00:00.000000Z",
        "customer": {
            "id": 1,
            "name": "John Doe",
            "since": "2024-01-30T10:00:00.000000Z"
        },
        "items": [
            {
                "id": 2,
                "order_id": 1,
                "product_id": 1,
                "quantity": 3,
                "unit_price": 500.00,
                "total": 1500.00,
                "product": {
                    "id": 1,
                    "name": "Product 1",
                    "category_id": 1,
                    "price": 500.00,
                    "stock": 7
                }
            },
            {
                "id": 3,
                "order_id": 1,
                "product_id": 2,
                "quantity": 2,
                "unit_price": 500.00,
                "total": 1000.00,
                "product": {
                    "id": 2,
                    "name": "Product 2",
                    "category_id": 1,
                    "price": 500.00,
                    "stock": 8
                }
            }
        ]
    },
    "message": "Order updated successfully"
}
```

- **Error Response**:
```json
{
    "message": "Error updating order",
    "error": "Insufficient stock for product 1"
}
```

#### Delete Order
- **DELETE** `/api/v1/orders/{id}`
- **Açıklama**: Siparişi sil
- **Response**:
```json
{
    "message": "Order deleted successfully"
}
```

- **Error Response**:
```json
{
    "message": "Error deleting order",
    "error": "Order not found"
}
```

#### Calculate Order Discount
- **POST** `/api/v1/orders/{id}/calculate-discount`
- **Açıklama**: Sipariş indirimlerini hesapla
- **Response**:
```json
{
    "order_id": 1,
    "subtotal": 2500.00,
    "discounts": [
        {
            "type": "category",
            "category_id": 1,
            "item_count": 6,
            "discount_rate": "10%",
            "amount": 250.00
        },
        {
            "type": "total_amount",
            "min_amount": 1000,
            "order_total": 2500.00,
            "discount_rate": "10%",
            "amount": 250.00
        }
    ],
    "total_discount": 500.00,
    "total": 2000.00
}
```

- **Error Response**:
```json
{
    "message": "Error calculating discounts",
    "error": "Order not found"
}
```

### İndirim Kuralları

1. **Kategori İndirimi**
   - Aynı kategoriden 6 veya daha fazla ürün alındığında
   - O kategorideki ürünlerin toplamı üzerinden %10 indirim
   - Her kategori için ayrı hesaplanır

2. **Toplam Tutar İndirimi**
   - Sipariş toplamı 1000 TL ve üzeri olduğunda
   - Tüm sipariş tutarı üzerinden %10 indirim

## Rate Limiting

API endpointleri için rate limiting tanımlanmıştır. Limitler `AppServiceProvider@boot` metodunda tanımlanmıştır:

```php
// Sipariş işlemleri için rate limit
RateLimiter::for('orders', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// İndirim hesaplama işlemleri için rate limit
RateLimiter::for('discounts', function (Request $request) {
    return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
});
```

Rate limit değerleri:
- Sipariş işlemleri (`/api/v1/orders`): Dakikada 60 istek
- İndirim hesaplama (`/api/v1/orders/{order}/calculate-discount`): Dakikada 5 istek

Rate limit aşıldığında API 429 (Too Many Requests) HTTP status kodu ile yanıt verecektir. Response header'ında:
- `X-RateLimit-Limit`: Toplam izin verilen istek sayısı
- `X-RateLimit-Remaining`: Kalan istek sayısı
- `Retry-After`: Yeni istekler için beklenecek süre (saniye)

## Veritabanı Şeması

### Tablolar

1. `customers`
   - id (primary key)
   - name
   - since (date)
   - deleted_at (soft delete)
   - timestamps

2. `products`
   - id (primary key)
   - name (string)
   - category_id (foreign key)
   - price (decimal)
   - stock (integer)
   - deleted_at (soft delete)
   - timestamps

3. `orders`
   - id (primary key)
   - customer_id (foreign key)
   - total (decimal)
   - deleted_at (soft delete)
   - timestamps

4. `order_items`
   - id (primary key)
   - order_id (foreign key)
   - product_id (foreign key)
   - quantity (integer)
   - unit_price (decimal)
   - total (decimal)
   - deleted_at (soft delete)
   - timestamps

## Önbellek Sistemi

Uygulama, Redis'i birincil önbellek ve veritabanını yedek olarak kullanan iki katmanlı bir önbellek sistemine sahiptir:

### Redis Yapılandırması

1. `.env` dosyanızda aşağıdaki ortam değişkenlerini ayarlayın:
```env
CACHE_DRIVER=redis
CACHE_STORE=redis
REDIS_CACHE_CONNECTION=default

REDIS_CLIENT=predis
REDIS_HOST=redis-sunucunuz
REDIS_PASSWORD=redis-şifreniz
REDIS_PORT=redis-portunuz
```

2. Redis Cloud kullanıcıları için önemli notlar:
   - `predis` client kullanılmalıdır
   - Veritabanı indeksi her zaman `0` olmalıdır
   - Bağlantı zaman aşımı 60 saniye olarak ayarlanmıştır

### Yedekleme Mekanizması

- Sistem öncelikle Redis'i önbellekleme için kullanmaya çalışır
- Redis kullanılamıyorsa veya başarısız olursa, otomatik olarak veritabanı önbelleklemesine geçer
- Her yanıt, veri kaynağını gösteren meta bilgisi içerir:
  ```json
  {
    "data": { },
    "meta": {
      "source": "redis-cache|database-cached-to-redis|database-cached-to-db",
      "cached_at": "zaman_damgası"
    }
  }
  ```

### Önbellek Yönetimi

Uygulama önbelleğini temizleme:
```bash
php artisan cache:clear
```

Yapılandırma önbelleğini temizleme:
```bash
php artisan config:clear
```

## Laravel Telescope

Laravel Telescope, uygulama performansını ve davranışını izlemek için kullanılan güçlü bir debug aracıdır.

#### Erişim

- Local: [http://localhost:8000/telescope](http://localhost:8000/telescope)
- Production: [https://ideasoft.barazlab.com/telescope](https://ideasoft.barazlab.com/telescope)

#### Konfigürasyon

1. Telescope'u aktif/pasif yapmak için `.env` dosyasında:
```
TELESCOPE_ENABLED=true
```

2. Varsayılan olarak Telescope sadece local ortamda aktiftir. Production ortamında erişimi kısıtlamak için `app/Providers/TelescopeServiceProvider.php` dosyasında gate tanımlaması yapılmıştır:

```php
protected function gate(): void
{
    Gate::define('viewTelescope', function ($user = null) {
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', [
            '127.0.0.1',
            '::1',
            // Production IP addresses
        ]);
    });
}
```

3. Telescope verileri otomatik olarak temizlenir. Veri saklama süresi `config/telescope.php` dosyasından ayarlanabilir.

## Roadmap

Projenin öncelikli geliştirme planı:

1. **Authentication & Authorization**
   - JWT tabanlı authentication sistemi
   - Role-based access control (RBAC)
   - API key yönetimi ve rotasyonu

2. **API Geliştirmeleri**
   - OpenAPI/Swagger dokümantasyonu
   - Bulk işlemler için endpointler

3. **Ödeme Sistemi Entegrasyonu**
   - Çoklu payment provider desteği
   - Async ödeme işlemleri
   - Webhook sistemi

4. **Performans İyileştirmeleri**
   - Database indexleme optimizasyonu
   - Query optimizasyonları

## Test

Unit ve feature testlerini çalıştırmak için:

```bash
docker compose exec -e XDEBUG_MODE=coverage app vendor/bin/phpunit --coverage-clover build/logs/clover.xml
```

## Lisans

Bu proje [MIT lisansı](LICENSE.md) altında lisanslanmıştır.

# Ideasoft SE Case Study - E-Commerce API

Bu proje, IdeaSoft Senior PHP Developer pozisyonu için hazırlanmış örnek bir e-ticaret API projesidir.

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

### Sipariş İşlemleri

- `GET /api/orders` - Tüm siparişleri listele
- `GET /api/orders/{id}` - Belirli bir siparişi getir
- `POST /api/orders` - Yeni sipariş oluştur
- `PUT /api/orders/{id}` - Mevcut siparişi güncelle
- `DELETE /api/orders/{id}` - Sipariş sil

### İndirim Hesaplama

- `POST /api/orders/{id}/calculate-discount` - Sipariş için indirimi hesapla

## Veritabanı Şeması

### Tablolar

1. `customers`
   - id (primary key)
   - name
   - since (date)
   - revenue (decimal)
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

## Test

Unit ve feature testlerini çalıştırmak için:

```bash
docker compose exec app php artisan test
```

## Lisans

Bu proje [MIT lisansı](LICENSE) altında lisanslanmıştır.

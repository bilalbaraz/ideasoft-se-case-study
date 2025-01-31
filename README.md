# Ideasoft SE Case Study - E-Commerce API
[![Coverage Status](https://coveralls.io/repos/github/bilalbaraz/ideasoft-se-case-study/badge.svg?branch=main)](https://coveralls.io/github/bilalbaraz/ideasoft-se-case-study?branch=main)

Bu proje, IdeaSoft Senior PHP Developer pozisyonu için hazırlanmış örnek bir e-ticaret API projesidir.

## Demo

API'yi aşağıdaki adreslerden test edebilirsiniz:

- Production: [https://ideasoft.barazlab.com](https://ideasoft.barazlab.com)
- Local: [http://localhost:8000](http://localhost:8000)

## Postman Collection

API'yi test etmek için Postman Collection'ı kullanabilirsiniz:

[![Run in Postman](https://run.pstmn.io/button.svg)](https://app.getpostman.com/run-collection/your-collection-id)

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
- **Description**: Tüm siparişleri listele
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
                "name": "John Doe"
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
                        "name": "Product 1"
                    }
                }
            ]
        }
    ]
}
```

#### Get Order
- **GET** `/api/v1/orders/{id}`
- **Description**: Belirli bir siparişi getir
- **Response**: Tek bir sipariş objesi

#### Create Order
- **POST** `/api/v1/orders`
- **Description**: Yeni sipariş oluştur
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
- **Response**: Oluşturulan sipariş objesi ve başarı mesajı

#### Update Order
- **PUT** `/api/v1/orders/{id}`
- **Description**: Siparişi güncelle
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
- **Response**: Güncellenen sipariş objesi ve başarı mesajı

#### Delete Order
- **DELETE** `/api/v1/orders/{id}`
- **Description**: Siparişi sil
- **Response**: Başarı mesajı

#### Calculate Order Discount
- **POST** `/api/v1/orders/{id}/calculate-discount`
- **Description**: Sipariş indirimlerini hesapla
- **Response**:
```json
{
    "order_id": 1,
    "subtotal": 1500.00,
    "discounts": [
        {
            "type": "category",
            "category_id": 1,
            "item_count": 8,
            "discount_rate": "10%",
            "amount": 80.00
        },
        {
            "type": "total_amount",
            "min_amount": 1000,
            "order_total": 1500.00,
            "discount_rate": "10%",
            "amount": 150.00
        }
    ],
    "total_discount": 230.00,
    "total": 1270.00
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

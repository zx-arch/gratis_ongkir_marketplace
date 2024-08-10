# Main Installation

1. Run `composer install`
2. Setup database on `.env`
3. Generate your app key by `php artisan key:generate`
4. Re-run seed task using `php artisan migrate --seed`

# Test API

## Sample Request Data

### Single Item

```json
{
    "product_id": 1,
    "quantity": 2
}
```
### Multiple Item

```json
[
    {
        "product_id": 1,
        "quantity": 2
    },
    {
        "product_id": 2,
        "quantity": 5
    }
]
```
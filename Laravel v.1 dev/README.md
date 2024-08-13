# Main Installation

1. Run `composer install`
2. Setup database on `.env`
3. Generate your app key by `php artisan key:generate`
4. Re-run seed task using `php artisan migrate --seed`

# Test API

## Sample Request Data

### Single Item (Without `data` key)

```json
{
    "product_id": 1,
    "quantity": 2
}
```
### Multiple or Single Item (With `data` key)

```json
{
    "data": [
        {
            "product_id": 1,
            "quantity": 3
        },
        {
            "product_id": 2,
            "quantity": 5
        },
        {
            "product_id": 3,
            "quantity": 2
        }
    ]
}
```
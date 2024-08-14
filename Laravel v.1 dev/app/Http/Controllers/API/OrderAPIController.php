<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Orders;
use App\Models\OrderItems;
use App\Models\Carts;
use App\Models\Products;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\API\Orders as OrdersApiRequests;

class OrderAPIController extends Controller
{
    public function index()
    {
        try {
            $orders = Orders::where('user_id', Auth::id())->with('orderItems')->get();
            $response = ['status' => 'success', 'data' => $orders];

            if ($orders->isEmpty()) {
                $response['message'] = 'Order pesanan kosong.';
            }

            return response()->json($response, 200);

        } catch (\Throwable $e) {
            // menangkap semua jenis kesalahan termasuk sprti undefined array dsb
            return response()->json([
                'status' => 'error',
                'message' => "Terjadi kesalahan internal pada server: {$e->getMessage()}"
            ], $e->getCode() ?: 500);
        }
    }

    public function show($id)
    {
        try {
            $orders = Orders::where('user_id', Auth::id())->where('id', $id)->with('order_items')->first();
            $response = ['status' => 'success', 'data' => $orders];

            if ($orders->isEmpty()) {
                $response['message'] = 'Order pesanan kosong.';
            }

            return response()->json($response, 200);

        } catch (\Throwable $e) {
            // menangkap semua jenis kesalahan termasuk sprti undefined array dsb
            return response()->json([
                'status' => 'error',
                'message' => "Terjadi kesalahan internal pada server: {$e->getMessage()}"
            ], $e->getCode() ?: 500);
        }

    }

    public function checkout(OrdersApiRequests\CheckoutRequest $request)
    {
        $data = $request->validated();
        $data = $request->has('data') ? $request->safe()->input('data') : [$request->safe()->only(['product_id', 'quantity'])];

        try {
            $totalOrderAmount = 0;
            $productIds = array_column($data, 'product_id');

            // Retrieve product and cart data from the database
            $products = Products::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
            $userId = Auth::id();
            $carts = Carts::where('user_id', $userId)->whereIn('product_id', $products->keys()->toArray())->get()->keyBy('product_id');

            // Prepare bulk insert data for order items and check stock availability
            $orderItems = [];

            foreach ($data as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                if (!isset($carts[$productId])) {
                    throw new \Exception("Produk dengan ID $productId tidak tersedia di keranjang.", 400);
                }

                $product = $products[$productId];
                if ($product->stock < $quantity) {
                    throw new \Exception("Kuantitas melebihi stock produk, saat ini stock tersisa {$product->stock} stock untuk produk ID $productId", 400);
                }

                // Update stock
                $product->stock -= $quantity;
                $product->save();

                // Calculate total amount
                $totalOrderAmount += $product->price * $quantity;

                // Prepare order items for bulk insert
                $orderItems[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => number_format($product->price * $quantity, 2, '.', ''),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            DB::transaction(function () use (&$userId, &$totalOrderAmount, &$orderItems) {
                // Create a new order
                $newOrder = Orders::create([
                    'user_id' => $userId,
                    'order_date' => now(),
                    'total_amount' => $totalOrderAmount
                ]);

                // Add order_id to each item using array_map
                $orderItems = array_map(function ($item) use ($newOrder) {
                    $item['order_id'] = $newOrder->id;
                    return $item;
                }, $orderItems);

                // Insert order items in bulk
                OrderItems::insert($orderItems);

                // Delete cart items
                Carts::where('user_id', $userId)->whereIn('product_id', array_column($orderItems, 'product_id'))->delete();

            }, 5);

            return response()->json([
                'status' => 'success',
                'message' => 'Order berhasil dibuat!'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);

        } catch (\Throwable $e) {

            return response()->json([
                'status' => 'error',
                'message' => "Gagal membuat order: {$e->getMessage()}",
            ], $e->getCode() ?: 500);
        }
    }
}
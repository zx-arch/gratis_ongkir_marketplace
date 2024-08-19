<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Orders;
use App\Models\OrderItems;
use App\Models\Carts;
use App\Models\Products;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderAPIController extends Controller
{
    public function index()
    {
        $orders = Orders::where('user_id', Auth::id())
            ->with('orderItems')->latest()
            ->get();

        return $this->formatApiResponse('success', data: $orders);
    }

    public function show($id)
    {
        $order = Orders::where('user_id', Auth::id())
            ->where('id', $id)
            ->with('orderItems')
            ->first();

        return $this->formatApiResponse('success', data: $order);
    }

    /**
     * Format the API response.
     *
     * @param string $status
     * @param string|null $message
     * @param mixed|null $data
     * @param int $errorCode
     * @return \Illuminate\Http\JsonResponse
     */
    private function formatApiResponse($status, $message = null, $data = null, $errorCode = 200)
    {
        $response = [
            'status' => $status,
        ];

        if ($status === 'success') {
            if ($data !== null) {
                $response['data'] = $this->mapData($data);
            }
            if ($message !== null) {
                $response['message'] = $message;
            }
        } elseif ($status === 'error') {
            $response['message'] = $message ?? 'An error occurred';
            $errorCode = $errorCode ?: 500;
        }

        return response()->json($response, $errorCode);
    }

    /**
     * Map the data to the desired format.
     *
     * @param mixed $data
     * @return mixed
     */
    private function mapData($data)
    {
        if ($data instanceof \Illuminate\Database\Eloquent\Collection) {
            return $data->map(function ($item) {
                return $this->formatOrder($item);
            });
        } elseif ($data instanceof \Illuminate\Database\Eloquent\Model) {
            return $this->formatOrder($data);
        }

        return $data;
    }

    /**
     * Format a single order.
     *
     * @param \App\Models\Orders $order
     * @return array
     */
    private function formatOrder($order)
    {
        return [
            'id' => $order->id,
            'order_date' => $order->order_date,
            'total_amount' => $order->total_amount,
            'order_items' => $order->orderItems->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ];
            }),
        ];
    }

    public function checkout()
    {
        try {
            $carts = Carts::select('product_id', 'quantity')->where('user_id', Auth::id());
            $productIds = $carts->pluck('product_id')->toArray();

            if (empty($productIds)) {
                throw new \Exception("Belum ada produk di keranjang!", 400);
            }

            $products = Products::whereIn('id', $productIds)->get()->keyBy('id');

            $record = [
                'totalOrderAmount' => 0,
                'orderItems' => [],
                'updatedProductsStock' => []
            ];

            $getCarts = $carts->get();

            foreach ($getCarts as $cart) {
                // Akses produk langsung dari koleksi berdasarkan ID
                if (isset($products[$cart->product_id])) {
                    $product = $products[$cart->product_id];

                    if ($product->stock < $cart->quantity) {
                        throw new \Exception("Kuantitas cart ditemukan melebihi stock produk!", 400);
                    }

                    $price = $product->price * $cart->quantity;

                    $record['orderItems'][] = [
                        'product_id' => $cart->product_id,
                        'quantity' => $cart->quantity,
                        'price' => $price,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $record['totalOrderAmount'] += $price;

                    $record['updatedProductsStock'][] = [
                        'id' => $cart->product_id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'stock' => $product->stock - $cart->quantity
                    ];
                }
            }

            DB::beginTransaction();

            // Using upsert to update product stocks
            Products::lockForUpdate()->upsert(
                $record['updatedProductsStock'],
                ['id'],
                ['stock']
            );

            $newOrder = Orders::create([
                'user_id' => Auth::id(),
                'order_date' => now(),
                'total_amount' => $record['totalOrderAmount']
            ]);

            $orderItems = array_map(function ($item) use ($newOrder) {
                $item['order_id'] = $newOrder->id;
                return $item;
            }, $record['orderItems']);

            OrderItems::insert($orderItems);

            $carts->whereIn('product_id', $productIds)->delete();

            DB::commit();
            return $this->formatApiResponse('success', 'Order berhasil dibuat!');

        } catch (\Throwable $e) {
            DB::rollBack();

            // Log error and return an error response
            Log::error('Error processing order: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);

            return $this->formatApiResponse('error', $e->getMessage() ?? 'Gagal membuat order.', 500);
        }
    }
}
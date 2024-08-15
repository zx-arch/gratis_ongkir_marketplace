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
use App\Http\Requests\API\Orders as OrdersApiRequests;

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

    public function checkout(OrdersApiRequests\CheckoutRequest $request)
    {
        $data = $request->has('data') ? $request->safe()->input('data') : [$request->safe()->all()];
        $errorMessage = '';

        try {
            $totalOrderAmount = 0;
            $productIds = array_column($data, 'product_id');

            // Retrieve product and cart data from the database efficiently
            $products = Products::whereIn('id', $productIds)->pluck('price', 'id');
            $userId = Auth::id();
            $carts = Carts::where('user_id', $userId)->whereIn('product_id', $productIds)->pluck('quantity', 'product_id');

            // Prepare bulk insert data for order items and check stock availability
            $orderItems = [];

            foreach ($data as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                // Check if the product is in the cart
                if (!isset($carts[$productId])) {
                    $errorMessage = "Produk tidak ditemukan di cart!";
                    throw new \Exception($errorMessage, 400);
                }

                // Check stock availability
                $productStock = Products::where('id', $productId)->value('stock');
                if ($productStock < $quantity) {
                    $errorMessage = "Kuantitas tidak boleh melebihi stock produk!";
                    throw new \Exception($errorMessage, 400);
                }

                // Safely decrement the product stock
                Products::where('id', $productId)->decrement('stock', $quantity);

                // Calculate total amount
                $totalOrderAmount += $products[$productId] * $quantity;

                // Prepare order items for bulk insert
                $orderItems[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $products[$productId] * $quantity,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            // Start the transaction
            DB::beginTransaction();

            $newOrder = Orders::create([
                'user_id' => $userId,
                'order_date' => now(),
                'total_amount' => $totalOrderAmount
            ]);

            // Add order_id to each item
            $orderItems = array_map(function ($item) use ($newOrder) {
                $item['order_id'] = $newOrder->id;
                return $item;
            }, $orderItems);

            // Insert order items in bulk
            OrderItems::insert($orderItems);

            // Delete cart items
            Carts::where('user_id', $userId)->whereIn('product_id', array_column($orderItems, 'product_id'))->delete();

            DB::commit();

            return $this->formatApiResponse('success', 'Order berhasil dibuat!');

        } catch (\Throwable $e) {
            DB::rollBack();

            // Log the error message
            Log::error('Error processing order: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return $this->formatApiResponse('error', $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }
}
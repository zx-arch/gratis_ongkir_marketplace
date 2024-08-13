<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Orders;
use App\Models\OrderItems;
use App\Models\Carts;
use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrdersAPIController extends Controller
{
    public function index()
    {
        $orders = Orders::where('user_id', Auth::id())->with('orderItems')->get();
        return response()->json($orders);
    }

    public function show($id)
    {
        $orders = Orders::where('user_id', Auth::id())->where('id', $id)->with('order_items')->first();
        return response()->json([$orders]);
    }

    public function checkout(Request $request)
    {
        if ($request->has('data')) {
            // Multiple items case
            $rules = [
                'data' => 'required|array',
                'data.*.product_id' => 'required|integer|exists:products,id',
                'data.*.quantity' => 'required|integer|min:1',
            ];

            // Custom validation error messages for multiple items
            $messages = [
                'data.required' => 'Data produk harus diisi.',
                'data.array' => 'Data produk harus berupa array.',
                'data.*.product_id.required' => 'ID produk harus diisi.',
                'data.*.product_id.integer' => 'ID produk harus berupa angka bulat.',
                'data.*.product_id.exists' => 'ID produk tidak ada dalam tabel produk.',
                'data.*.quantity.required' => 'Kuantitas harus diisi.',
                'data.*.quantity.integer' => 'Kuantitas harus berupa angka bulat.',
                'data.*.quantity.min' => 'Kuantitas harus lebih besar dari 0.',
            ];

            // Validate the request for multiple items
            $validator = \Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validasi gagal.',
                    'messages' => $validator->errors(),
                ], 422);
            }

            $data = $request->input('data');
        } else {
            // Single item case
            $rules = [
                'product_id' => 'required|integer|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ];

            // Custom validation error messages for single item
            $messages = [
                'product_id.required' => 'ID produk harus diisi.',
                'product_id.integer' => 'ID produk harus berupa angka bulat.',
                'product_id.exists' => 'ID produk tidak ada dalam tabel produk.',
                'quantity.required' => 'Kuantitas harus diisi.',
                'quantity.integer' => 'Kuantitas harus berupa angka bulat.',
                'quantity.min' => 'Kuantitas harus lebih besar dari 0.',
            ];

            // Validate the request for a single item
            $validator = \Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validasi gagal.',
                    'messages' => $validator->errors(),
                ], 422);
            }

            // Wrap single item in an array for consistent processing
            $data = [$request->only(['product_id', 'quantity'])];
        }

        DB::beginTransaction();

        try {
            $totalOrderAmount = 0;
            $orderItemsData = [];
            $productIds = array_column($data, 'product_id');

            // Retrieve product data from the database
            $products = Products::whereIn('id', $productIds)->get()->keyBy('id');

            // Retrieve cart items for the current user and the given product IDs
            $userId = Auth::id();
            $carts = Carts::where('user_id', $userId)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');

            // Check if any product is not in the cart
            foreach ($data as $item) {
                if (!isset($carts[$item['product_id']])) {
                    throw new \Exception('Produk dengan ID ' . $item['product_id'] . ' tidak tersedia di keranjang.', 400);
                }
            }

            // Create a new order
            $newOrder = Orders::create([
                'user_id' => $userId,
                'order_date' => now(),
                'total_amount' => $totalOrderAmount
            ]);

            foreach ($data as $item) {
                $cartItem = $carts[$item['product_id']];
                $priceProduct = $products[$item['product_id']];

                // Check if the quantity in the cart matches the quantity in the request
                if ($cartItem->quantity != $item['quantity']) {
                    throw new \Exception('Sesuaikan quantity checkout dengan keranjang untuk produk dengan ID ' . $item['product_id'], 400);
                }

                if ($priceProduct->stock < $item['quantity']) {
                    throw new \Exception('Stock tidak cukup untuk produk dengan ID ' . $item['product_id'], 400);
                }

                $itemTotalPrice = $priceProduct->price * $item['quantity'];
                $totalOrderAmount += $itemTotalPrice;

                $orderItem = OrderItems::create([
                    'order_id' => $newOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => number_format($itemTotalPrice, 2, '.', '')
                ]);

                $orderItemsData[] = $orderItem;
            }

            // Update total amount of the order
            $newOrder->total_amount = $totalOrderAmount;
            $newOrder->save();

            // Delete cart items
            Carts::where('user_id', $userId)->whereIn('product_id', $productIds)->delete();

            DB::commit();

            return response()->json([
                'success' => 'Order berhasil dibuat!',
                'data' => [
                    'order' => $newOrder,
                    'items' => $orderItemsData
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Gagal membuat order.',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }
}
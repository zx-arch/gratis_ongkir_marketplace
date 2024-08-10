<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Orders;
use App\Models\OrderItems;
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
        // Aturan validasi
        $rules = [
            'product_id' => 'required|integer',
            'quantity' => 'required|integer',
        ];

        if ($request->isJson() && is_array($request->json()->all())) {
            $data = $request->json()->all();

            if (isset($data[0]) && is_array($data[0])) {
                $rules = [
                    '*.product_id' => 'required|integer',
                    '*.quantity' => 'required|integer',
                ];
            }

            try {
                // Validasi data
                $request->validate($rules);

                DB::beginTransaction();

                $totalOrderAmount = 0;
                $orderItemsData = [];

                $productIds = array_column($data, 'product_id');
                $products = Products::whereIn('id', $productIds)->get()->keyBy('id');

                $newOrder = Orders::create([
                    'user_id' => Auth::id(),
                    'order_date' => now(),
                    'total_amount' => $totalOrderAmount
                ]);

                if (isset($data[0]) && is_array($data[0])) {
                    foreach ($data as $item) {
                        $priceProduct = $products->get($item['product_id']);

                        if (!$priceProduct) {
                            throw new \Exception('Produk tidak tersedia!', 400);
                        }

                        if ($item['quantity'] < 1) {
                            throw new \Exception('Quantity tidak valid!', 400);
                        }

                        if ($priceProduct->stock < $item['quantity']) {
                            throw new \Exception('Stock tidak cukup!', 400);
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

                    $newOrder->total_amount = $totalOrderAmount;
                    $newOrder->save();

                } else {
                    $priceProduct = Products::where('id', $data['product_id'])->first();

                    if (!$priceProduct) {
                        throw new \Exception('Produk tidak tersedia!', 400);
                    }

                    if ($data['quantity'] < 1) {
                        throw new \Exception('Quantity tidak valid!', 400);
                    }

                    if ($data['quantity'] > $priceProduct->stock) {
                        throw new \Exception('Stock tidak cukup!', 400);
                    }

                    $totalPrice = $priceProduct->price * $data['quantity'];
                    $totalOrderAmount += $totalPrice;

                    $orderItem = OrderItems::create([
                        'order_id' => $newOrder->id,
                        'product_id' => $data['product_id'],
                        'quantity' => $data['quantity'],
                        'price' => number_format($totalPrice, 2, '.', '')
                    ]);

                    $orderItemsData[] = $orderItem;

                    $newOrder->total_amount = $totalOrderAmount;
                    $newOrder->save();
                }

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
                    'error' => 'Error create order.',
                    'message' => $e->getMessage(),
                ], $e->getCode() ?: 500);
            }
        } else {
            return response()->json([
                'error' => 'Format data tidak valid. Harap kirim data dalam format JSON.',
            ], 400);
        }
    }
}
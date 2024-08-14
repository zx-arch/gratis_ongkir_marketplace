<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Carts;
use App\Models\Products;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\API\Carts as CartsApiRequests;

class CartAPIController extends Controller
{
    public function index()
    {
        $carts = Carts::with('product')->where('user_id', Auth::id())->get();

        $response = [
            'status' => 'success',
            'data' => $carts->map(function ($cart) {
                return [
                    'product_id' => $cart->product_id,
                    'quantity' => $cart->quantity,
                    'product' => [
                        'name' => $cart->product->name,
                        'price' => $cart->product->price,
                        'stock' => $cart->product->stock,
                    ],
                ];
            }),
        ];

        if ($carts->isEmpty()) {
            $response['message'] = 'Keranjang belanja kosong.';
        }

        return response()->json($response, 200);
    }

    public function store(CartsApiRequests\PostRequest $request)
    {
        try {
            $productId = $request->product_id;
            $quantity = $request->quantity;
            // Fetch the stock of the requested product
            $product = Products::where('id', $productId)->first();

            if (!$product) {
                throw new \Exception("Produk dengan ID {$productId} tidak ditemukan!");
            }

            if ($product->stock < $quantity) {
                throw new \Exception("Kuantitas tidak boleh lebih dari {$product->stock} untuk produk ID {$productId}!");
            }

            // Fetch the existing cart item for the current user
            $existingCart = Carts::where('user_id', Auth::id())
                ->where('product_id', $productId)
                ->first();

            if ($existingCart) {
                $existingCart->quantity += $quantity;
                $existingCart->save();
            } else {
                Carts::create([
                    'user_id' => Auth::id(),
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil menambah produk ke keranjang!'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);

        } catch (\Throwable $e) {
            // Log the throwable message and the request data
            Log::error('Throwable error adding product to cart: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => "Gagal menambah produk ke keranjang",
            ], 500);
        }
    }

    public function show($id)
    {
        $carts = Carts::select('product_id', 'quantity')->where('id', $id)
            ->where('user_id', Auth::id())->with('product')
            ->first();

        $response = [
            'status' => 'success',
            'data' => $carts->map(function ($cart) {
                return [
                    'product_id' => $cart->product_id,
                    'quantity' => $cart->quantity,
                    'product' => [
                        'name' => $cart->product->name,
                        'price' => $cart->product->price,
                        'stock' => $cart->product->stock,
                    ],
                ];
            }),
        ];

        if ($carts->isEmpty()) {
            $response['message'] = 'Keranjang belanja kosong.';
        }

        return response()->json($response, 200);
    }

    public function update(CartsApiRequests\UpdateRequest $request, $id)
    {
        try {
            $cart = Carts::where('user_id', Auth::id())->findOrFail($id);
            $productStock = Products::where('id', $cart->product_id)->value('stock');

            if ($productStock < $request->quantity) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Kuantitas tidak boleh lebih dari {$productStock} stock!"
                ], 401);
            }

            // Update cart with the correct quantity value
            $cart->update(['quantity' => $request->quantity]);

            return response()->json([
                'status' => 'success',
                'message' => 'Data cart berhasil diupdate!'
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error update product to cart: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => "Error update cart"
            ], 500);
        }
    }

    public function destroy($id)
    {
        $cart = Carts::where('user_id', Auth::id())->where('id', $id)->firstOrFail();

        if (!$cart) {
            return response()->json([
                'status' => 'error',
                'message' => 'ID cart tidak terbaca!'
            ], 401);
        }

        $cart->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data berhasil dihapus!'
        ], 200);

    }

}
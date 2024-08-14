<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Carts;
use App\Models\Products;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\API\Carts as CartsRequests;

class CartAPIController extends Controller
{
    public function index()
    {
        try {
            $carts = Carts::where('user_id', Auth::id())->with('product')->get();
            $response = ['status' => 'success', 'data' => $carts];

            if ($carts->isEmpty()) {
                $response['message'] = 'Keranjang belanja kosong.';
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

    public function store(CartsRequests\PostRequest $request)
    {
        // Validate and retrieve data from the request
        $data = $request->validated();
        $data = $request->has('data') ? $request->safe()->input('data') : [$request->safe()->only(['product_id', 'quantity'])];

        try {
            if (count($data) > 1) {
                // Extract product IDs from data
                $productIds = array_column($data, 'product_id');

                // Fetch all product stocks for the given product IDs
                $productStocks = Products::whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // Fetch all existing cart items for the current user and given product IDs
                $existingCartItems = Carts::where('user_id', Auth::id())
                    ->whereIn('product_id', $productStocks->keys()->toArray())
                    ->get()
                    ->keyBy('product_id');

                // Prepare arrays for bulk update and insert
                $bulkUpdates = [];
                $bulkInserts = [];

                foreach ($data as $item) {
                    $productId = $item['product_id'];
                    $quantity = $item['quantity'];

                    // Check if product stock data exists for the given product ID
                    if (!isset($productStocks[$productId])) {
                        throw new \Exception("Produk dengan ID {$productId} tidak ditemukan!");
                    }

                    $productStock = $productStocks[$productId]->stock;

                    // Check stock availability
                    if ($productStock < $quantity) {
                        throw new \Exception("Kuantitas tidak boleh lebih dari {$productStock} untuk produk ID {$productId}!");
                    }

                    if (isset($existingCartItems[$productId])) {
                        // If the item already exists in the cart, prepare it for update
                        $bulkUpdates[$existingCartItems[$productId]->id] = $quantity;
                    } else {
                        // If the item does not exist in the cart, prepare it for insertion
                        $bulkInserts[] = [
                            'user_id' => Auth::id(),
                            'product_id' => $productId,
                            'quantity' => $quantity,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }

                // Execute the bulk update with a CASE statement if any updates exist
                if (!empty($bulkUpdates)) {
                    $cases = array_map(function ($id, $quantity) {
                        return "WHEN id = {$id} THEN {$quantity}";
                    }, array_keys($bulkUpdates), $bulkUpdates);

                    $caseStatementStr = implode(' ', $cases);

                    Carts::whereIn('id', array_keys($bulkUpdates))
                        ->update(['quantity' => DB::raw("(CASE {$caseStatementStr} END)")]);
                }

                // Perform bulk inserts if there are new cart items
                if (!empty($bulkInserts)) {
                    Carts::insert($bulkInserts);
                }

            } else {
                $productId = $data[0]['product_id'];
                $quantity = $data[0]['quantity'];
                $product = Products::where('id', $productId)->lockForUpdate()->first();

                // Fetch the existing cart item for the current user
                $existingCart = Carts::where('user_id', Auth::id())
                    ->where('product_id', $productId)
                    ->first();

                // Fetch the stock of the requested product
                if ($product->stock < $quantity) {
                    throw new \Exception("Kuantitas tidak boleh lebih dari {$product->stock} stock!");
                }

                if ($existingCart) {
                    $existingCart->quantity = $quantity;
                    $existingCart->save();
                } else {
                    Carts::create([
                        'user_id' => Auth::id(),
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ]);
                }
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
            return response()->json([
                'status' => 'error',
                'message' => "Gagal menambah produk ke keranjang: {$e->getMessage()}",
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $cart = Carts::where('user_id', Auth::id())->where('id', $id)->with('product')->first();
            $response = ['status' => 'success', 'data' => $cart];

            if ($cart->isEmpty()) {
                $response['message'] = 'Keranjang belanja kosong.';
            }

            return response()->json($response, 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Terjadi kesalahan internal pada server: {$e->getMessage()}"
            ], $e->getCode() ?: 500);
        }

    }

    public function update(CartsRequests\UpdateRequest $request, $id)
    {
        $validate = $request->validated();
        $validate = $request->safe()->only('quantity');

        try {
            $cart = Carts::where('user_id', Auth::id())->lockForUpdate()->findOrFail($id);
            $productStock = Products::where('id', $cart->product_id)->value('stock');

            if ($productStock < $validate['quantity']) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Kuantitas tidak boleh lebih dari {$productStock} stock!"
                ], 401);
            }

            // Update cart with the correct quantity value
            $cart->update(['quantity' => $validate['quantity']]);

            return response()->json([
                'status' => 'success',
                'message' => 'Data cart berhasil diupdate!'
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Error update cart: {$e->getMessage()}"
            ], $e->getCode() ?: 500);
        }
    }

    public function destroy($id)
    {
        try {
            $cart = Carts::where('user_id', Auth::id())->where('id', $id)->lockForUpdate()->firstOrFail();
            $cart->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Data berhasil dihapus!'
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Error delete cart: {$e->getMessage()}",
            ], $e->getCode() ?: 500);
        }
    }

}
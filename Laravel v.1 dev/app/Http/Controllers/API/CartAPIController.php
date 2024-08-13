<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Carts;
use App\Models\Products;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\API\Carts as CartsRequests;

class CartAPIController extends Controller
{
    private $findUser;

    public function __construct()
    {
        // cek dan ambil data user autentikasi
        $this->findUser = auth()->check() ? User::find(Auth::id()) : null;
    }

    public function index()
    {
        // Periksa apakah pengguna ditemukan atau tidak di dalam constructor
        if (is_null($this->findUser)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna belum melakukan autentikasi token!'
            ], 401);
        }

        try {
            $carts = Carts::where('user_id', $this->findUser->id)->with('product')->get();
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

    public function store(CartsRequests\PostCartRequest $request)
    {
        // Validate and retrieve data from the request
        $data = $request->validated();
        $data = $request->has('data') ? $request->safe()->input('data') : [$request->safe()->only(['product_id', 'quantity'])];

        try {
            DB::transaction(function () use (&$data) {
                if (count($data) > 1) {
                    // Fetch all cart items for the current user
                    $existingCartItems = Carts::where('user_id', Auth::id())
                        ->whereIn('product_id', collect($data)->pluck('product_id'))
                        ->get()
                        ->keyBy('product_id'); // Use keyBy for easier searching

                    // Fetch all product stocks for the given product IDs
                    $productStocks = Products::whereIn('id', collect($data)->pluck('product_id'))
                        ->get()
                        ->keyBy('id');

                    foreach ($data as $item) {
                        $productId = $item['product_id'];
                        $quantity = $item['quantity'];

                        // Check if the product stock is available
                        if (!isset($productStocks[$productId]) || $productStocks[$productId]->stock < $quantity) {
                            throw new \Exception("Kuantitas tidak boleh lebih dari {$productStocks[$productId]->stock} untuk produk ID {$productId}!");
                        }

                        if (isset($existingCartItems[$productId])) {
                            // If the item already exists in the cart, update the quantity
                            $cart = $existingCartItems[$productId];
                            $cart->quantity = $quantity;
                            $cart->save();
                        } else {
                            // If the item does not exist in the cart, create a new entry
                            Carts::create([
                                'user_id' => Auth::id(),
                                'product_id' => $productId,
                                'quantity' => $quantity,
                            ]);
                        }
                    }

                } else {
                    $productId = $data[0]['product_id'];
                    $quantity = $data[0]['quantity'];

                    // Fetch the existing cart item for the current user
                    $existingCart = Carts::where('user_id', Auth::id())
                        ->where('product_id', $productId)
                        ->first();

                    // Fetch the stock of the requested product
                    $productStock = Products::where('id', $productId)->value('stock');

                    if ($productStock < $quantity) {
                        throw new \Exception("Kuantitas tidak boleh lebih dari {$productStock} stock!");
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

            }, 5);

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
        if (is_null($this->findUser)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna belum melakukan autentikasi token!'
            ], 401);
        }

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

    public function update(CartsRequests\UpdateCartRequest $request, $id)
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
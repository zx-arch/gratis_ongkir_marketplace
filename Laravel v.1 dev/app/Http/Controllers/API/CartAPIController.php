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

    public function store(CartsRequests\PostRequest $request)
    {
        // Validate and retrieve data from the request
        $data = $request->validated();
        $data = $request->has('data') ? $request->safe()->input('data') : [$request->safe()->only(['product_id', 'quantity'])];

        try {
            DB::transaction(function () use (&$data) {
                // Extract product IDs and quantities from data
                $productIds = collect($data)->pluck('product_id');
                $quantities = collect($data)->keyBy('product_id')->map(function ($item) {
                    return $item['quantity'];
                });

                // Fetch all existing cart items for the current user and given product IDs
                $existingCartItems = Carts::where('user_id', Auth::id())
                    ->whereIn('product_id', $productIds)
                    ->get()
                    ->keyBy('product_id');

                // Fetch all product stocks for the given product IDs
                $productStocks = Products::whereIn('id', $productIds)
                    ->get()
                    ->keyBy('id');

                // Prepare arrays for bulk update and insert
                $bulkUpdates = [];
                $bulkInserts = [];

                // Constructing queries
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
                            'user_id' => $this->findUser->id,
                            'product_id' => $productId,
                            'quantity' => $quantity,
                        ];
                    }
                }

                // Perform bulk updates using a single query
                if (!empty($bulkUpdates)) {
                    // Construct a CASE WHEN statement for bulk updating
                    $caseStatements = [];
                    foreach ($bulkUpdates as $id => $quantity) {
                        $caseStatements[] = "WHEN id = {$id} THEN {$quantity}";
                    }

                    // Convert the CASE statements array to a string
                    $caseStatementStr = implode(' ', $caseStatements);

                    // Execute the bulk update with a CASE statement
                    Carts::whereIn('id', array_keys($bulkUpdates))
                        ->update(['quantity' => DB::raw("(CASE {$caseStatementStr} END)")]);
                }

                // Perform bulk inserts if there are new cart items
                if (!empty($bulkInserts)) {
                    Carts::insert($bulkInserts);
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
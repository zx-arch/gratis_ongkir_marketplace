<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Carts;
use App\Models\Products;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CartAPIController extends Controller
{
    public function index()
    {
        $carts = Carts::where('user_id', Auth::id())->with('product')->get();
        return response()->json($carts);
    }

    public function store(Request $request)
    {
        // Determine if the request contains a single item or multiple items
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
            $carts = [];

            foreach ($data as $item) {
                // Check if the item already exists in the cart for the current user
                $existingCart = Carts::where('user_id', Auth::id())
                    ->where('product_id', $item['product_id'])
                    ->first();

                if ($existingCart) {
                    // Update the quantity if the item is already in the cart
                    $existingCart->quantity = $item['quantity'];
                    $existingCart->save();
                    $carts[] = $existingCart;
                } else {
                    // Create a new cart entry if the item is not in the cart
                    $cart = Carts::create([
                        'user_id' => Auth::id(),
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                    ]);
                    $carts[] = $cart;
                }
            }

            DB::commit();

            return response()->json($carts, 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Gagal membuat keranjang.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $cart = Carts::where('user_id', Auth::id())->where('id', $id)->with('product')->first();
        return response()->json([$cart]);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'quantity' => 'required|integer',
        ];

        // Pesan kesalahan validasi kustom
        $messages = [
            'quantity.required' => 'Quantity harus diisi.',
            'quantity.integer' => 'Setiap quantity harus berupa angka bulat.',
            'quantity.*.min' => 'Quantity harus minimal 1.',
            'quantity.*.max' => 'Quantity tidak boleh lebih dari 100.',
        ];

        // Validasi data permintaan
        $validator = \Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validasi gagal.',
                'messages' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $cart = Carts::where('user_id', Auth::id())->lockForUpdate()->findOrFail($id);
            $cart->update($request->all());

            DB::commit();
            return response()->json($cart);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error update cart.',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], $e->getCode() ?? 500);
        }

    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            Carts::where('user_id', Auth::id())->where('id', $id)->lockForUpdate()->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data berhasil dihapus!',
                'code' => 200
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error delete cart.',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], $e->getCode() ?? 500);

        }
    }
}
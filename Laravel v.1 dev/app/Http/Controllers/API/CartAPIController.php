<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Carts;
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
        $rules = [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer',
        ];

        // Pesan kesalahan validasi kustom
        $messages = [
            'product_id.required' => 'ID produk harus diisi.',
            'product_id.integer' => 'Setiap ID produk harus berupa angka bulat.',
            'product_id.*.exists' => 'ID produk tidak ada dalam tabel produk.',
            'quantity.required' => 'Kuantitas harus diisi.',
            'quantity.integer' => 'Setiap kuantitas harus berupa angka bulat.'
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
            $cart = Carts::create([
                'user_id' => Auth::id(),
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);

            DB::commit();

            return response()->json($cart, 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error create cart.',
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
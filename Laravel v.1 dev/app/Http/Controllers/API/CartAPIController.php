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
        $carts = Carts::where('user_id', Auth::id())->get();
        return response()->json($carts);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|integer',
        ]);

        DB::beginTransaction();

        try {
            $cart = Carts::create([
                'user_id' => Auth::id(),
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);

            DB::commit();
            return response()->json($cart, 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error create cart.',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], $e->getCode() ?? 500);
        }
    }

    public function show($id)
    {
        $cart = Carts::where('user_id', Auth::id())->findOrFail($id);
        return response()->json($cart);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer',
        ]);

        DB::beginTransaction();

        try {
            $cart = Carts::where('user_id', Auth::id())->lockForUpdate()->findOrFail($id);
            $cart->update($request->all());

            DB::commit();
            return response()->json($cart);

        } catch (\Throwable $e) {
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
            Carts::where('user_id', Auth::id())->findOrFail($id)->lockForUpdate()->delete();

            DB::commit();
            return response()->json(null, 204);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error delete cart.',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], $e->getCode() ?? 500);

        }
    }
}
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Carts;
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

        $cart = Carts::create([
            'user_id' => Auth::id(),
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
        ]);

        return response()->json($cart, 201);
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

        $cart = Carts::where('user_id', Auth::id())->findOrFail($id);
        $cart->update($request->all());
        return response()->json($cart);
    }

    public function destroy($id)
    {
        Carts::where('user_id', Auth::id())->findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
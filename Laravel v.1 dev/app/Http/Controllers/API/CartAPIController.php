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
        $carts = Carts::with('product')->where('user_id', Auth::id())->latest()->get();
        return $this->formatResponse('success', $carts, 200);
    }

    public function show($id)
    {
        $cart = Carts::select('product_id', 'quantity')->where('id', $id)
            ->where('user_id', Auth::id())->with('product')->latest()
            ->first();

        return $this->formatResponse('success', $cart, 200);
    }

    private function formatResponse($status, $message, $data = null, $errorCode = 200)
    {
        // Inisialisasi response dasar
        $response = [
            'status' => $status,
            'message' => $message ?: $this->getDefaultMessage($status, $errorCode)
        ];

        // Jika status adalah success dan data ada, tambahkan data ke response
        if ($status === 'success' && $data !== null) {
            $response['data'] = $this->formatData($data);
        }

        // Untuk status error atau jika errorCode adalah kode error yang dikenal
        if ($status === 'error' || in_array($errorCode, [400, 401, 403, 404, 429, 500])) {
            return response()->json($response, $errorCode);
        }

        // Jika status success dan message bukan string, set response data dan hapus key message
        if (gettype($response['message'] != 'string')) {
            // copy response message ke response data
            $response["data"] = $response["message"];
            // hapus data response message
            unset($response["message"]);
        }

        return response()->json($response, 200);
    }

    // Dapatkan pesan default berdasarkan status dan kode error
    private function getDefaultMessage($status, $errorCode)
    {
        if ($status === 'error') {
            return match ($errorCode) {
                400 => 'Permintaan tidak valid.',
                401 => 'Akses tidak diizinkan.',
                403 => 'Akses ditolak.',
                404 => 'Sumber daya tidak ditemukan.',
                429 => 'Terlalu banyak request data.',
                default => 'Terjadi kesalahan server',
            };
        }
        return;
    }

    // Format data untuk response
    private function formatData($data)
    {
        return $data instanceof \Illuminate\Support\Collection ?
            $data->map([$this, 'mapOrder']) :
            ($data instanceof Orders ? $this->mapOrder($data) : null);
    }

    // Format data order
    private function mapOrder($cart)
    {
        return [
            'product_id' => $cart->product_id,
            'quantity' => $cart->quantity,
            'product' => [
                'name' => $cart->product->name,
                'price' => $cart->product->price,
                'stock' => $cart->product->stock,
            ],
        ];
    }

    public function store(CartsApiRequests\PostRequest $request)
    {
        try {
            $product = Products::find($request->product_id);
            $errorMessage = '';

            if (!$product) {
                $errorMessage = "Produk tidak ditemukan!";
                throw new \Exception($errorMessage, 404);
            }

            if ($product->stock < $request->quantity) {
                $errorMessage = "Kuantitas tidak boleh lebih dari stock produk!";
                throw new \Exception($errorMessage, 400);
            }

            // Fetch the existing cart item for the current user
            $existingCart = Carts::where('user_id', Auth::id())
                ->where('product_id', $request->product_id)
                ->first();

            if ($existingCart) {
                $existingCart->quantity += $request->quantity;
                $existingCart->save();
            } else {
                Carts::create([
                    'user_id' => Auth::id(),
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            return $this->formatResponse('success', 'Berhasil menambah produk ke keranjang!', 200);

        } catch (\Throwable $e) {
            $logError = $errorMessage ?: $e->getMessage();

            Log::error("Throwable error adding product to cart: $logError", [
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return $this->formatResponse('error', $errorMessage ?: "Gagal menambah produk ke keranjang", $e->getCode());
        }
    }

    public function update(CartsApiRequests\UpdateRequest $request, $id)
    {
        try {
            $cart = Carts::where('user_id', Auth::id())->findOrFail($id);
            $productStock = Products::where('id', $cart->product_id)->value('stock');

            if ($productStock < $request->quantity) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Kuantitas tidak boleh lebih dari stock produk!"
                ], 401);
            }

            // Update cart with the correct quantity value
            $cart->update(['quantity' => $request->quantity]);

            return $this->formatResponse('success', 'Data cart berhasil diupdate!', 200);

        } catch (\Throwable $e) {
            Log::error('Error update product to cart: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return $this->formatResponse('error', 'Error update cart', $e->getCode());
        }
    }

    public function destroy($id)
    {
        $cart = Carts::where('user_id', Auth::id())->where('id', $id)->firstOrFail();

        if (!$cart) {
            return $this->formatResponse('error', 'ID cart tidak terbaca!', 401);
        }

        $cart->delete();
        return $this->formatResponse('success', 'Data berhasil dihapus!', 200);
    }

    // private function responses($data)
    // {
    //     $response = [
    //         'status' => 'success',
    //         'data' => $data->map(function ($cart) {
    //             return [
    //                 'product_id' => $cart->product_id,
    //                 'quantity' => $cart->quantity,
    //                 'product' => [
    //                     'name' => $cart->product->name,
    //                     'price' => $cart->product->price,
    //                     'stock' => $cart->product->stock,
    //                 ],
    //             ];
    //         }),
    //     ];

    //     if ($data->isEmpty()) {
    //         $response['message'] = 'Keranjang belanja kosong.';
    //     }

    //     return $response;
    // }
}
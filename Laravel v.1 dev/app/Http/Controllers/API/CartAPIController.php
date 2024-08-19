<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Carts;
use App\Models\Products;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\API\Carts as CartsApiRequests;

class CartAPIController extends Controller
{
    public function index()
    {
        $carts = Carts::with('product')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return $this->formatApiResponse('success', data: $carts);
    }

    public function show($id)
    {
        $cart = Carts::with('product')
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (!$cart) {
            return $this->formatApiResponse('error', 'Data cart tidak ditemukan!', errorCode: 404);
        }

        return $this->formatApiResponse('success', data: $cart);
    }

    /**
     * Format the API response.
     *
     * @param string $status
     * @param string|null $message
     * @param mixed|null $data
     * @param int $errorCode
     * @return \Illuminate\Http\JsonResponse
     */
    private function formatApiResponse($status, $message = null, $data = null, $errorCode = 200)
    {
        $response = ['status' => $status];

        if ($status === 'success') {
            if ($data !== null) {
                $response['data'] = $this->mapData($data);
            }
            if ($message) {
                $response['message'] = $message;
            }
        } elseif ($status === 'error') {
            $response['message'] = $message ?? $this->getDefaultMessage($errorCode);
        }

        return response()->json($response, $errorCode);
    }

    private function getDefaultMessage($errorCode)
    {
        return match ($errorCode) {
            400 => 'Permintaan tidak valid.',
            401 => 'Akses tidak diizinkan.',
            403 => 'Akses ditolak.',
            404 => 'Sumber daya tidak ditemukan.',
            429 => 'Terlalu banyak permintaan.',
            default => 'Terjadi kesalahan server.',
        };
    }

    /**
     * Map the data to the desired format.
     *
     * @param mixed $data
     * @return mixed
     */
    private function mapData($data)
    {
        if ($data instanceof \Illuminate\Database\Eloquent\Collection) {
            return $data->map(fn($item) => $this->formatCart($item));
        } elseif ($data instanceof \Illuminate\Database\Eloquent\Model) {
            return $this->formatCart($data);
        }

        return $data;
    }

    /**
     * Format a single cart item.
     *
     * @param \App\Models\Carts $cart
     * @return array
     */
    private function formatCart($cart)
    {
        return [
            'id' => $cart->id,
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

            if (!$product) {
                throw new \Exception("Produk tidak ditemukan!", 404);
            }

            if ($product->stock < $request->quantity) {
                throw new \Exception("Kuantitas tidak boleh lebih dari stock produk!", 400);
            }

            // Check if cart item exists
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

            return $this->formatApiResponse('success', message: 'Berhasil menambah produk ke keranjang!');

        } catch (\Throwable $e) {
            Log::error("Throwable error adding product to cart: " . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return $this->formatApiResponse('error', 'Gagal menambah produk ke keranjang!', errorCode: $e->getCode() ?: 500);
        }
    }

    public function update(CartsApiRequests\UpdateRequest $request, $id)
    {
        try {
            $cart = Carts::where('user_id', Auth::id())->findOrFail($id);
            $productStock = Products::where('id', $cart->product_id)->value('stock');

            if ($productStock < $request->quantity) {
                return $this->formatApiResponse('error', "Kuantitas tidak boleh lebih dari stock produk!", 400);
            }

            // Update cart with the correct quantity value
            $cart->update(['quantity' => $request->quantity]);

            return $this->formatApiResponse('success', message: 'Data cart berhasil diupdate!');

        } catch (\Throwable $e) {
            Log::error('Error updating product in cart: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return $this->formatApiResponse('error', 'Gagal memperbarui cart!', errorCode: $e->getCode() ?: 500);
        }
    }

    public function destroy($id)
    {
        $cart = Carts::where('user_id', Auth::id())->where('id', $id)->first();

        if (!$cart) {
            return $this->formatApiResponse('error', 'Gagal menghapus data!', errorCode: 500);
        }

        $cart->delete();

        return $this->formatApiResponse('success', message: 'Data berhasil dihapus!');
    }

}
<?php

namespace App\Http\Controllers;

use App\Product;
use App\Cart\Item;
use App\Cart\CashDraft;
use App\Cart\CreditDraft;
use App\Cart\CartCollection;
use Illuminate\Http\Request;

class CartController extends Controller
{   
    /*
     * Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    private $cart;
    /*
     * Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    public function __construct()
    {
        $this->cart = new CartCollection();
    }
    /*
     *  Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    public function index(Request $request)
    {
        $queriedProducts = [];
        $draft = $this->cart->content()->first();

        return view('cart.index', compact('draft', 'queriedProducts'));
    }
    /*
     * Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    public function show(Request $request, $draftKey)
    {
        $draft = $this->cart->get($draftKey);
        if (is_null($draft)) {
            flash(trans('transaction.draft_not_found'), 'danger');

            return redirect()->route('cart.index');
        }

        $query = $request->get('query');
        $queriedProducts = [];
        if ($query) {
            $queriedProducts = Product::where(function ($q) use ($query) {
                $q->where('name', 'like', '%'.$query.'%');
            })->with('unit')->get();
        }

        return view('cart.index', compact('draft', 'queriedProducts'));
    }
    /*
     * Fungsi ini untuk menambahkan konten/isi data dari cart (keranjang belanja) user
     */
    public function add(Request $request)
    {
        if ($request->has('create-cash-draft')) {
            $this->cart->add(new CashDraft());
        } else {
            $this->cart->add(new CreditDraft());
        }

        return redirect()->route('cart.show', $this->cart->content()->last()->draftKey);
    }
    /*
     * Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    public function addDraftItem(Request $request, $draftKey, Product $product)
    {
        $item = new Item($product, $request->qty);
        $this->cart->addItemToDraft($draftKey, $item);

        flash(trans('cart.item_added', [
            'product_name' => $product->name.' ('.$product->unit->name.')',
            'qty'          => $request->qty,
        ]));

        if ($request->has('query')) {
            return redirect()->route('cart.show', [$draftKey, 'query' => $request->get('query')]);
        }

        return back();
    }
    /*
     * Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    public function updateDraftItem(Request $request, $draftKey)
    {
        $this->cart->updateDraftItem($draftKey, $request->item_key, $request->only('qty', 'item_discount'));

        return back();
    }
    /*
     * Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    public function removeDraftItem(Request $request, $draftKey)
    {
        $this->cart->removeItemFromDraft($draftKey, $request->item_index);

        return back();
    }
    /*
     * Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    public function empty($draftKey)
    {
        $this->cart->emptyDraft($draftKey);

        return redirect()->route('cart.index', $draftKey);
    }
    /*
     * Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    public function remove(Request $request)
    {
        $this->cart->removeDraft($request->draft_key);

        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $lastDraft = $this->cart->content()->last();

        return redirect()->route('cart.show', $lastDraft->draftKey);
    }
    /*
     * Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    public function destroy()
    {
        $this->cart->destroy();
        flash(trans('transaction.draft_destroyed'), 'warning');

        return redirect()->route('cart.index');
    }
    /*
     * Fungsi ini untuk menampilkan konten/isi data dari cart (keranjang belanja) user
     */
    public function proccess(Request $request, $draftKey)
    {
        $this->validate($request, [
            'customer.name'  => 'required|string|max:30',
            'customer.phone' => 'nullable|string|max:20',
            'total'          => 'required|numeric',
            'payment'        => 'required|numeric|min:'.$request->get('total').'|max:'.($request->get('total') + 100000),
            'notes'          => 'nullable|string|max:100',
        ], [
            'payment.min' => 'Pembayaran minimal '.format_rp($request->get('total')).'.',
            'payment.max' => 'Pembayaran terlalu besar '.format_rp($request->get('payment')).'.',
        ]);
        $draft = $this->cart->updateDraftAttributes($draftKey, $request->only('customer', 'notes', 'payment'));

        if ($draft->getItemsCount() == 0) {
            flash(trans('transaction.item_list_empty'), 'warning')->important();

            return redirect()->route('cart.show', [$draftKey]);
        }

        flash(trans('transaction.confirm_instruction', ['back_link' => link_to_route('cart.show', trans('app.back'), $draftKey)]), 'warning')->important();

        return redirect()->route('cart.show', [$draftKey, 'action' => 'confirm']);
    }
    /*
     * Fungsi ini untuk menstor konten/isi data dari cart (keranjang belanja) user
     */
    public function store(Request $request, $draftKey)
    {
        $draft = $this->cart->get($draftKey);
        if (is_null($draft)) {
            return redirect()->route('cart.index');
        }

        $transaction = $draft->store();
        $draft->destroy();
        flash(trans('transaction.created', ['invoice_no' => $transaction->invoice_no]), 'success')->important();

        return redirect()->route('transactions.show', $transaction->invoice_no);
    }
}

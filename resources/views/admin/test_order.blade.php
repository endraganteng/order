@extends('admin.layout')

@section('title', 'Test Order - Admin')

@section('content')
    <h2 style="margin-bottom: 20px; color: #333;">Buat Test Order</h2>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('admin.test_order.create') }}" id="testOrderForm">
            @csrf

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Nama Waiter</label>
                <input type="text" name="waiter_name" value="{{ old('waiter_name', 'Admin Test') }}" required
                    style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                @error('waiter_name')
                    <span style="color: #dc3545; font-size: 12px;">{{ $message }}</span>
                @enderror
            </div>

            <h3 style="margin: 20px 0 15px; color: #555;">Produk</h3>

            <div id="productsContainer">
                <div class="product-row"
                    style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 10px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Nama
                                Produk</label>
                            <input type="text" name="products[0][name]" required
                                style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Harga
                                (Rp)</label>
                            <input type="number" name="products[0][price]" required min="0"
                                style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger remove-product"
                                style="padding: 10px; display: none;">Hapus</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" id="addProduct" class="btn btn-success" style="margin-bottom: 20px;">➕ Tambah
                Produk</button>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Buat Order</button>
                <a href="{{ route('admin.dashboard') }}" class="btn" style="background: #6c757d; color: white;">Batal</a>
            </div>
        </form>
    </div>

    <script>
        let productIndex = 1;

        document.getElementById('addProduct').addEventListener('click', function () {
            const container = document.getElementById('productsContainer');
            const productRow = document.createElement('div');
            productRow.className = 'product-row';
            productRow.style.cssText = 'background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 10px;';

            productRow.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Nama Produk</label>
                            <input type="text" name="products[${productIndex}][name]" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Harga (Rp)</label>
                            <input type="number" name="products[${productIndex}][price]" required min="0" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 4px;">
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger remove-product" style="padding: 10px;">Hapus</button>
                        </div>
                    </div>
                `;

            container.appendChild(productRow);
            productIndex++;

            updateRemoveButtons();
        });

        document.getElementById('productsContainer').addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-product')) {
                e.target.closest('.product-row').remove();
                updateRemoveButtons();
            }
        });

        function updateRemoveButtons() {
            const rows = document.querySelectorAll('.product-row');
            rows.forEach((row, index) => {
                const removeBtn = row.querySelector('.remove-product');
                if (rows.length > 1) {
                    removeBtn.style.display = 'block';
                } else {
                    removeBtn.style.display = 'none';
                }
            });
        }
    </script>
@endsection
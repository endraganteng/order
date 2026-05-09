@extends('admin.layout')

@section('title', 'Suppliers - Admin')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="color: var(--color-text-dark, #333);">Suppliers Management</h2>
        <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary">+ Tambah Supplier</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Telepon</th>
                    <th>Contact Person</th>
                    <th>Alamat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($suppliers as $supplier)
                    <tr>
                        <td>{{ $supplier['name'] ?? $supplier->name }}</td>
                        <td>{{ $supplier['phone'] ?? $supplier->phone }}</td>
                        <td>{{ $supplier['contact_person'] ?? $supplier->contact_person ?? '-' }}</td>
                        <td>{{ $supplier['address'] ?? $supplier->address ?? '-' }}</td>
                        <td>
                            <a href="{{ route('admin.suppliers.edit', $supplier['id'] ?? $supplier->id) }}" class="btn btn-sm btn-primary" style="margin-right: 5px;">Edit</a>
                            <form action="{{ route('admin.suppliers.destroy', $supplier['id'] ?? $supplier->id) }}" method="POST" style="display: inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus supplier ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">Belum ada data supplier.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@extends('admin.layout')

@section('title', 'Waiters - Admin')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="color: #333;">Waiters Management</h2>
        <a href="{{ route('admin.waiters.create') }}" class="btn btn-primary">➕ Tambah Waiter</a>
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
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Metode Login</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($waiters as $waiter)
                    <tr>
                        <td>{{ $waiter['name'] }}</td>
                        <td>{{ $waiter['email'] }}</td>
                        <td>
                            @php $waiterRole = strtolower((string) ($waiter['waiter_role'] ?? 'pelayan')); @endphp
                            @if($waiterRole === 'kasir')
                                <span class="badge" style="background:#fff7ed; color:#9a3412;">Kasir</span>
                            @else
                                <span class="badge" style="background:#ecfeff; color:#0f766e;">Pelayan</span>
                            @endif
                        </td>
                        <td>
                            @if($waiter['is_active'])
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge" style="background: #e8f0fe; color: #1e3a8a;">Google</span>
                            @if(!empty($waiter['password_hash']))
                                <span class="badge" style="background: #e8f5e9; color: #1b5e20; margin-left: 6px;">+ Password Cadangan</span>
                            @endif
                        </td>
                        <td>{{ isset($waiter['created_at']) ? date('d/m/Y H:i', $waiter['created_at']) : '-' }}</td>
                        <td>
                            <a href="{{ route('admin.waiters.edit', $waiter['id']) }}" class="btn btn-warning"
                                style="padding: 6px 12px; font-size: 12px;">Edit</a>
                            <form method="POST" action="{{ route('admin.waiters.destroy', $waiter['id']) }}"
                                style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;"
                                    onclick="return confirm('Yakin ingin menghapus?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: #999;">Belum ada waiter</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

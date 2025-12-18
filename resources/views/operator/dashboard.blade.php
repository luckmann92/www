<div class="container my-3" id="operator-dashboard">
    <div class="row mb-3">
        <div class="col-12">
            <h4>Operator Dashboard</h4>
            <hr>
        </div>
    </div>
    <div class="row">
        <div class="col-6">
            <div class="card p-3">
                <div class="card-title">Total Orders</div>
                <div class="h4">{{ $stats['orders'] }}</div>
            </div>
        </div>
        <div class="col-6">
            <div class="card p-3">
                <div class="card-title">Paid Orders</div>
                <div class="h4">{{ $stats['paid'] }}</div>
            </div>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-6">
            <div class="card p-3">
                <div class="card-title">Ready (Blurred)</div>
                <div class="h4">{{ $stats['blurred'] }}</div>
            </div>
        </div>
        <div class="col-6">
            <div class="card p-3">
                <div class="card-title">Delivered</div>
                <div class="h4">{{ $stats['delivered'] }}</div>
            </div>
        </div>
    </div>
</div>

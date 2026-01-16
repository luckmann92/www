<div class="bg-white rounded shadow-sm p-4 py-4 d-flex flex-column gap-3">
    <h5 class="mb-3">Изображения</h5>
    <div class="row">
        @php
            $photos = $order->session?->photos ?? collect();

            $originalPhoto = $photos->where('type', 'original')->first();
            $blurredPhoto = $photos->where('type', 'result')->where('blur_level', 80)->first();
            $readyPhoto = $photos->where('type', 'result')->where('blur_level', 0)->first();
        @endphp

        <div class="col-12 col-md-4 text-center mb-3">
            <p class="fw-bold mb-2">Оригинальное фото</p>
            @if($originalPhoto)
                <a href="{{ asset('storage/' . $originalPhoto->path) }}" target="_blank">
                    <img src="{{ asset('storage/' . $originalPhoto->path) }}"
                         style="max-width:250px;max-height:200px;border-radius:8px;border:1px solid #ddd;cursor:pointer;">
                </a>
            @else
                <div class="p-4 bg-light rounded text-muted">Изображение отсутствует</div>
            @endif
        </div>

        <div class="col-12 col-md-4 text-center mb-3">
            <p class="fw-bold mb-2">Размытое изображение</p>
            @if($blurredPhoto)
                <a href="{{ asset('storage/' . $blurredPhoto->path) }}" target="_blank">
                    <img src="{{ asset('storage/' . $blurredPhoto->path) }}"
                         style="max-width:250px;max-height:200px;border-radius:8px;border:1px solid #ddd;cursor:pointer;">
                </a>
            @else
                <div class="p-4 bg-light rounded text-muted">Изображение отсутствует</div>
            @endif
        </div>

        <div class="col-12 col-md-4 text-center mb-3">
            <p class="fw-bold mb-2">Готовое изображение</p>
            @if($readyPhoto)
                <a href="{{ asset('storage/' . $readyPhoto->path) }}" target="_blank">
                    <img src="{{ asset('storage/' . $readyPhoto->path) }}"
                         style="max-width:250px;max-height:200px;border-radius:8px;border:1px solid #ddd;cursor:pointer;">
                </a>
            @else
                <div class="p-4 bg-light rounded text-muted">Изображение отсутствует</div>
            @endif
        </div>
    </div>
</div>

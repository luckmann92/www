@component('mail::message')
# Ваше фото готово!

Спасибо за использование нашего сервиса. Ваше фото готово и прикреплено к этому письму.

@component('mail::button', ['url' => config('app.url')])
Перейти на сайт
@endcomponent

С уважением,<br>
{{ config('app.name') }}
@endcomponent

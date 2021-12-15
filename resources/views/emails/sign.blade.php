@component('mail::message')
Dear Mr/Mrs/Ms {{ $name }}

This document {{ $filename }}

Ready to be signed

@component('mail::button', ['url' => $url])
Sign here
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent

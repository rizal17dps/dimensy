@component('mail::message')
Dear, Mr/Mrs/Ms {{ $name }}

Thank you for your registration. 

Please click the button below to activate your account :

@component('mail::button', ['url' => $url])
Activate Account
@endcomponent

Thank you,<br>
Digital Document Security (Dimensy)
@endcomponent

@component('mail::message')
# Welcome, {{ $user->name }}

Welcome to {{ $appName }} — your employee account has been created.

@if($rawPassword)
**Your login credentials**

- **Email:** {{ $user->email }}
- **Password:** `{{ $rawPassword }}`

We recommend changing this password after your first login.
@endif

@if($inviteLink)
@component('mail::button', ['url' => $inviteLink])
Set your password & sign in
@endcomponent

If the button above doesn't work, copy and paste this link into your browser:

{{ $inviteLink }}
@endif

Thanks,<br>
{{ $appName }} Team
@endcomponent

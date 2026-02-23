@props(['url'])
<tr>
    <td class="header">
        <a href="{{ route('home') }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel')
                <img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo">
            @else
                <img src="{{ asset('images/vs-logo-email.png') }}" class="logo" alt="{{ $slot }}"
                     style="height: 75px; max-height: 75px; width: auto;">
            @endif
        </a>
    </td>
</tr>

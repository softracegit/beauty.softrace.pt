@props(['url'])
@php
  $mailBranding = \App\Support\StoreMailBranding::current();
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; line-height: 0;">
<img src="{{ $mailBranding['logo_url'] }}" class="logo-mail-brand" width="140" alt="{{ $mailBranding['name'] }}" style="display: block; width: 140px; max-width: 140px; height: auto; border: 0; outline: none;">
</a>
</td>
</tr>

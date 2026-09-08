{{-- Published from the framework to swap the app-name text for the wordmark.
     No other mail component is published, so this one is kept in sync by hand.

     `cid:logo` is embedded by the Mailable, not linked: there is no public host
     to serve an <img> from yet, and remote images are blocked by default in most
     clients anyway. Every Mailable must call HasEmailLogo::embedLogo() or the
     header renders a broken image. --}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="cid:logo" class="logo" alt="{{ config('app.name') }}">
</a>
</td>
</tr>

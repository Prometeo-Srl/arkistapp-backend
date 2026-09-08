<x-mail::message>
# Conferma la tua nuova email

Hai chiesto di usare questo indirizzo per il tuo account Prometeo. Inserisci il
codice qui sotto nell'app per confermarlo.

<x-mail::panel>
<p class="otp-code">{{ $code }}</p>
</x-mail::panel>

Il codice scade tra {{ $expiresInMinutes }} minuti e vale una sola volta.

Se il cambio email non l'hai chiesto tu, ignora questo messaggio: il tuo
indirizzo attuale resta invariato.
</x-mail::message>
